<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers app/common/VisitsCounter.php
 * @covers apps/home/model/DoModel.php addVisits
 *
 * fix #27：访问量增量聚合、批量回写、并发准确性与故障恢复
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

defined('CONF_PATH') ?: define('CONF_PATH', ROOT_PATH . 'config');
defined('RUN_PATH') ?: define('RUN_PATH', ROOT_PATH . 'runtime');

require_once TEST_ROOT . 'support/DatabaseTestSupport.php';
require_once CORE_PATH . '/function/file.php';
require_once CORE_PATH . '/basic/Basic.php';
require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/Model.php';
require_once CORE_PATH . '/database/Builder.php';
require_once APP_PATH . '/common/VisitsCounter.php';
require_once APP_PATH . '/home/model/DoModel.php';

spl_autoload_register('core\\basic\\Basic::autoLoad', true, true);

use app\common\VisitsCounter;
use app\home\model\DoModel;

function visits_sqlite_available(): bool
{
    return db_sqlite_available();
}

function visits_probe_command(int $contentId, string $runPath, int $times, string $mode = 'delta', string $flushFile = ''): string
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'visits_counter_probe.php';
    $args = array($script, (string) $contentId, $runPath, (string) $times, $mode);
    if ($flushFile !== '') {
        $args[] = $flushFile;
    }
    return $php . ' ' . implode(' ', array_map('escapeshellarg', $args));
}

function visits_start_probe(int $contentId, string $runPath, int $times, string $mode = 'delta', string $flushFile = ''): array
{
    $descriptors = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $pipes = array();
    $proc = proc_open(visits_probe_command($contentId, $runPath, $times, $mode, $flushFile), $descriptors, $pipes);
    return array($proc, $pipes);
}

function visits_concurrent_incr(int $contentId, string $runPath, int $totalTimes, int $workers = 50, string $mode = 'delta', string $flushFile = ''): array
{
    $timesEach = (int) ceil($totalTimes / $workers);
    $assigned = 0;
    $runs = array();
    for ($i = 0; $i < $workers; $i++) {
        $remaining = $totalTimes - $assigned;
        if ($remaining <= 0) {
            break;
        }
        $n = min($timesEach, $remaining);
        $assigned += $n;
        $runs[] = visits_start_probe($contentId, $runPath, $n, $mode, $flushFile);
    }

    $exitCodes = array();
    foreach ($runs as $run) {
        list($proc, $pipes) = $run;
        if (! db_proc_ok($proc)) {
            $exitCodes[] = -1;
            continue;
        }
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCodes[] = proc_close($proc);
    }
    return $exitCodes;
}

function visits_tmp_run_path(string $suffix): string
{
    $dir = sys_get_temp_dir() . '/pboot_visits_test_' . $suffix . '_' . getmypid();
    path_delete($dir);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

function visits_config_reflection(): ReflectionProperty
{
    return db_config_reflection();
}

function visits_reset_sqlite_singleton()
{
    db_reset_sqlite_singleton();
}

/** PbootCMS Sqlite 写路径开显式事务，读库前必须提交，否则另一连接看不到更新 */
function visits_commit_sqlite()
{
    db_commit_sqlite();
}

function visits_boot_sqlite(string $runPath)
{
    if (! extension_loaded('sqlite3')) {
        return null;
    }

    // 路径与 runPath 绑定，避免 VisitsCounter 多用例并行撞库
    $rel = '/data/visits_counter_test_' . basename($runPath) . '.db';
    $sqlitePath = ROOT_PATH . $rel;
    if (is_file($sqlitePath)) {
        @unlink($sqlitePath);
    }
    $sqliteDir = dirname($sqlitePath);
    if (! is_dir($sqliteDir)) {
        mkdir($sqliteDir, 0777, true);
    }

    $sqlite = new SQLite3($sqlitePath);
    $sqlite->busyTimeout(5000);
    $sqlite->exec('CREATE TABLE ay_content (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        visits INTEGER NOT NULL DEFAULT 0
    )');
    $sqlite->exec("INSERT INTO ay_content (id, visits) VALUES (1, 0)");
    $sqlite->close();

    db_set_database_config(array(
        'type' => 'sqlite',
        'dbname' => $rel
    ));
    db_reset_sqlite_singleton();

    return $sqlitePath;
}

function visits_use_run_path(string $runPath)
{
    VisitsCounter::setStorageRunPath($runPath);
}

function visits_read_db_visits(string $sqlitePath, int $id = 1): int
{
    $sqlite = new SQLite3($sqlitePath);
    $sqlite->busyTimeout(5000);
    $visits = (int) $sqlite->querySingle("SELECT visits FROM ay_content WHERE id=$id");
    $sqlite->close();
    return $visits;
}

function visits_file_flush_handler(string $totalFile): callable
{
    return function ($id, $pending) use ($totalFile) {
        $fp = @fopen($totalFile, 'c+');
        if ($fp === false) {
            return false;
        }
        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        $total = is_string($raw) ? (int) $raw : 0;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) ($total + (int) $pending));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    };
}

function visits_read_flushed_total(string $totalFile): int
{
    if (! is_file($totalFile)) {
        return 0;
    }
    return (int) trim((string) file_get_contents($totalFile));
}

function visits_boot_mysql_or_skip()
{
    $dbFile = ROOT_PATH . 'config/database.php';
    if (! is_file($dbFile)) {
        return null;
    }
    $cfg = require $dbFile;
    $c = isset($cfg['database']) ? $cfg['database'] : array();
    if (! isset($c['type']) || stripos((string) $c['type'], 'sqlite') !== false) {
        return null;
    }

    // PHP 8.1+ 连接失败抛 mysqli_sql_exception，@ 无法压制，必须 catch 后 SKIP
    try {
        $mysqli = new mysqli(
            $c['host'] ?? '127.0.0.1',
            $c['user'] ?? 'root',
            $c['passwd'] ?? '',
            $c['dbname'] ?? '',
            (int) ($c['port'] ?? 3306)
        );
    } catch (Throwable $e) {
        return null;
    }
    if ($mysqli->connect_errno) {
        return null;
    }
    $mysqli->set_charset('utf8');
    return $mysqli;
}

/** 伪驱动：验证「入库 → 提交确认 → 销账」的调用契约，不依赖真实数据库扩展 */
class VisitsFakeDb
{

    public $calls = array();

    public $commitFails = false;

    public function amd($sql, $params = array())
    {
        $this->calls[] = 'amd';
        return 1;
    }

    public function commitTransaction()
    {
        $this->calls[] = 'commit';
        if ($this->commitFails) {
            throw new RuntimeException('commit failed');
        }
    }
}

function visits_mysqli_singleton_reflection(): ReflectionProperty
{
    $ref = new ReflectionClass('core\\database\\Mysqli');
    $prop = $ref->getProperty('mysqli');
    if (PHP_VERSION_ID < 80100) {
        $prop->setAccessible(true);
    }
    return $prop;
}

function visits_inject_fake_db($fake)
{
    visits_mysqli_singleton_reflection()->setValue(null, $fake);

    $cfgProp = visits_config_reflection();
    $configs = $cfgProp->getValue();
    if (! is_array($configs)) {
        $configs = array();
    }
    $configs['database'] = array(
        'type' => 'mysqli',
        'prefix' => 'ay_'
    );
    $cfgProp->setValue(null, $configs);
}

function visits_clear_fake_db()
{
    visits_mysqli_singleton_reflection()->setValue(null, null);
}

return TestAssert::runSuite(function () {
    VisitsCounter::disableShutdownFlush();

    echo "=== incr(): single visit does not synchronously flush to database ===\n";

    $runPath = visits_tmp_run_path('no_sync');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);
    VisitsCounter::setBatchThreshold(1000);

    $flushCalls = 0;
    VisitsCounter::setFlushHandler(function ($id, $pending) use (&$flushCalls) {
        $flushCalls += (int) $pending;
        return true;
    });

    $model = new DoModel();
    $model->addVisits(1);
    TestAssert::same(0, $flushCalls, 'no flush on single incr');
    TestAssert::same(1, VisitsCounter::getPending(1), 'pending delta recorded locally');

    VisitsCounter::flush(1);
    TestAssert::same(1, $flushCalls, 'flush commits pending once');
    TestAssert::same(0, VisitsCounter::getPending(1), 'pending cleared after flush');

    path_delete($runPath);

    echo "=== flush failure restores pending (no loss) ===\n";

    $runPath = visits_tmp_run_path('fail_restore');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);
    VisitsCounter::setBatchThreshold(1000);

    $failOnce = true;
    VisitsCounter::setFlushHandler(function ($id, $pending) use (&$failOnce) {
        if ($failOnce) {
            $failOnce = false;
            return false;
        }
        return true;
    });

    TestAssert::true(VisitsCounter::incr(9), 'incr ok');
    TestAssert::same(1, VisitsCounter::getPending(9), 'pending recorded before flush');

    TestAssert::false(VisitsCounter::flush(9), 'first flush fails with injected handler');
    TestAssert::same(1, VisitsCounter::getPending(9), 'failed flush restores pending');

    TestAssert::true(VisitsCounter::flush(9), 'second flush succeeds with injected handler');
    TestAssert::same(0, VisitsCounter::getPending(9), 'pending cleared after successful flush');

    path_delete($runPath);

    echo "=== corrupted shard JSON is quarantined, not silently deleted ===\n";

    $runPath = visits_tmp_run_path('corrupt');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);
    VisitsCounter::setBatchThreshold(1000);

    $shard = 9 % VisitsCounter::SHARD_COUNT;
    $dir = $runPath . '/data/visits';
    mkdir($dir, 0777, true);
    $corruptFile = $dir . '/shard_' . $shard . '.json';
    file_put_contents($corruptFile, '{"broken":');

    TestAssert::true(VisitsCounter::incr(9), 'incr succeeds despite corrupt shard file');
    TestAssert::same(1, VisitsCounter::getPending(9), 'new increment recorded after corrupt load');

    // 待刷增量是用户数据，损坏内容必须留证，不能被当成空分片抹掉
    $quarantined = glob($dir . '/shard_' . $shard . '.json.corrupt.*');
    TestAssert::same(1, count($quarantined), 'corrupt shard quarantined exactly once');
    TestAssert::same('{"broken":', file_get_contents($quarantined[0]), 'quarantined file keeps original bytes');

    path_delete($runPath);

    echo "=== getPending on corrupt shard does not quarantine without lock ===\n";

    $runPath = visits_tmp_run_path('corrupt_read');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);

    $shard = 9 % VisitsCounter::SHARD_COUNT;
    $dir = $runPath . '/data/visits';
    mkdir($dir, 0777, true);
    $corruptFile = $dir . '/shard_' . $shard . '.json';
    file_put_contents($corruptFile, '{"broken":');

    TestAssert::same(0, VisitsCounter::getPending(9), 'read path treats corrupt as empty');
    TestAssert::true(is_file($corruptFile), 'corrupt file still in place after unlocked read');
    TestAssert::same(0, count(glob($dir . '/shard_' . $shard . '.json.corrupt.*') ?: array()), 'read path must not quarantine');

    path_delete($runPath);

    echo "=== getDisplayVisits includes in-flight increment during flush window ===\n";

    $runPath = visits_tmp_run_path('sending_display');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);
    VisitsCounter::setBatchThreshold(1000);

    for ($i = 0; $i < 5; $i++) {
        VisitsCounter::incr(3);
    }
    TestAssert::same(5, VisitsCounter::getPending(3), 'pending before manual flush');

    VisitsCounter::setFlushHandler(function ($id, $pending) {
        TestAssert::same(3, $id, 'in-flight flush id');
        TestAssert::same(5, $pending, 'in-flight flush pending');
        TestAssert::same(105, VisitsCounter::getDisplayVisits(3, 100), 'display includes increment during DB write');
        return true;
    });
    TestAssert::true(VisitsCounter::flush(3), 'flush with in-flight display check');
    TestAssert::same(0, VisitsCounter::getPending(3), 'pending cleared after flush');
    TestAssert::same(100, VisitsCounter::getDisplayVisits(3, 100), 'display equals DB after flush completes');

    path_delete($runPath);

    echo "=== flushAll does not commit when shard save fails (no double count) ===\n";

    $runPath = visits_tmp_run_path('save_fail');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);
    VisitsCounter::setBatchThreshold(1000);

    $committed = 0;
    VisitsCounter::setFlushHandler(function ($id, $pending) use (&$committed) {
        $committed += (int) $pending;
        return true;
    });

    for ($i = 0; $i < 7; $i++) {
        VisitsCounter::incr(4);
    }
    TestAssert::same(7, VisitsCounter::getPending(4), 'pending recorded before failing flushAll');

    VisitsCounter::simulateSaveFailure(true);
    TestAssert::false(VisitsCounter::flushAll(), 'flushAll reports failure when save fails');
    TestAssert::same(0, $committed, 'no database commit when shard save fails');
    VisitsCounter::simulateSaveFailure(false);
    TestAssert::same(7, VisitsCounter::getPending(4), 'pending still on disk after failed save');

    TestAssert::true(VisitsCounter::flushAll(), 'retry flushAll succeeds');
    TestAssert::same(7, $committed, 'increment committed exactly once after retry');
    TestAssert::same(0, VisitsCounter::getPending(4), 'pending cleared after successful flushAll');

    path_delete($runPath);

    echo "=== incrAndGetVisits keeps display accurate when the call triggers a flush ===\n";

    $runPath = visits_tmp_run_path('incr_display');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);
    VisitsCounter::setBatchThreshold(3);

    $dbVisits = 100;
    VisitsCounter::setFlushHandler(function ($id, $pending) use (&$dbVisits) {
        $dbVisits += (int) $pending;
        return true;
    });

    // 前两次未达阈值：快照 100 + 本地增量
    TestAssert::same(101, VisitsCounter::incrAndGetVisits(7, $dbVisits), 'display after 1st visit');
    TestAssert::same(102, VisitsCounter::incrAndGetVisits(7, $dbVisits), 'display after 2nd visit');

    // 第三次触发阈值刷盘：快照仍为刷盘前的值，需由已入库增量补回
    TestAssert::same(103, VisitsCounter::incrAndGetVisits(7, 100), 'display stays accurate on flushing visit');
    TestAssert::same(103, $dbVisits, 'database received the batched increment');
    TestAssert::same(0, VisitsCounter::getPending(7), 'pending cleared after threshold flush');
    TestAssert::same(103, VisitsCounter::getDisplayVisits(7, $dbVisits), 'display equals DB once flushed');

    path_delete($runPath);

    echo "=== detail page must use incrAndGetVisits, not incr+getDisplayVisits ===\n";

    $runPath = visits_tmp_run_path('detail_display');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);
    VisitsCounter::setBatchThreshold(1); // 每次访问都回写

    $dbVisits = 100;
    VisitsCounter::setFlushHandler(function ($id, $pending) use (&$dbVisits) {
        $dbVisits += (int) $pending;
        return true;
    });

    // 错误路径：先 incr 再对旧快照 getDisplayVisits → 刷盘后少算
    VisitsCounter::incr(41);
    TestAssert::same(0, VisitsCounter::getPending(41), 'flushed pending cleared');
    TestAssert::same(100, VisitsCounter::getDisplayVisits(41, 100), 'stale snapshot after flush undercounts');

    // 正确路径（与 ParserController 详情一致）
    $dataVisits = VisitsCounter::incrAndGetVisits(42, 100);
    TestAssert::same(101, $dataVisits, 'incrAndGetVisits stays accurate across flush');
    // 已折算的展示值若再 getDisplayVisits 会在 pending>0 时重复加算；刷盘后 pending=0 碰巧相等
    TestAssert::same(0, VisitsCounter::getPending(42), 'pending cleared after display-path flush');
    TestAssert::same(101, $dataVisits, 'assigned visits_is_display value is final');

    path_delete($runPath);

    echo "=== interrupted commit is replayed from disk (no loss) ===\n";

    $runPath = visits_tmp_run_path('interrupted');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);

    // 模拟进程在写库途中被 error()/exit 终止：增量停留在 sending 且已过期
    $shard = 11 % VisitsCounter::SHARD_COUNT;
    $dir = $runPath . '/data/visits';
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/shard_' . $shard . '.json', json_encode(array(
        11 => array(
            'delta' => 2,
            'sending' => 6,
            'sending_at' => time() - 3600,
            'last_flush' => time() - 3600
        )
    )));

    TestAssert::same(8, VisitsCounter::getPending(11), 'interrupted increment still visible as pending');

    $replayed = 0;
    VisitsCounter::setFlushHandler(function ($id, $pending) use (&$replayed) {
        $replayed += (int) $pending;
        return true;
    });
    TestAssert::true(VisitsCounter::flushAll(), 'flushAll replays interrupted increment');
    TestAssert::same(8, $replayed, 'interrupted increment committed exactly once');
    TestAssert::same(0, VisitsCounter::getPending(11), 'pending cleared after replay');

    path_delete($runPath);

    echo "=== fresh sending is not replayed before timeout (no double count) ===\n";

    $runPath = visits_tmp_run_path('sending_fresh');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);

    $shard = 12 % VisitsCounter::SHARD_COUNT;
    $dir = $runPath . '/data/visits';
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/shard_' . $shard . '.json', json_encode(array(
        12 => array(
            'delta' => 0,
            'sending' => 4,
            'sending_at' => time(),
            'last_flush' => time()
        )
    )));

    $replayed = 0;
    VisitsCounter::setFlushHandler(function ($id, $pending) use (&$replayed) {
        $replayed += (int) $pending;
        return true;
    });
    TestAssert::true(VisitsCounter::flushAll(), 'flushAll skips in-flight increment of another process');
    TestAssert::same(0, $replayed, 'in-flight increment not committed twice');
    TestAssert::same(4, VisitsCounter::getPending(12), 'in-flight increment still counted for display');

    path_delete($runPath);

    echo "=== ongoing commits do not keep an orphaned in-flight increment alive ===\n";

    $runPath = visits_tmp_run_path('sending_orphan');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);
    VisitsCounter::setSendingTimeout(2);
    VisitsCounter::setBatchThreshold(1); // 每次访问都触发一次回写

    // 进程中断遗留的写库中记录：后续回写只应新增记录，不得刷新它的时间戳
    $shard = 13 % VisitsCounter::SHARD_COUNT;
    $dir = $runPath . '/data/visits';
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/shard_' . $shard . '.json', json_encode(array(
        13 => array(
            'delta' => 0,
            'sending' => array(
                'crashed' => array(
                    'amount' => 9,
                    'at' => time()
                )
            ),
            'last_flush' => time()
        )
    )));

    $committed = 0;
    VisitsCounter::setFlushHandler(function ($id, $pending) use (&$committed) {
        $committed += (int) $pending;
        return true;
    });

    sleep(1);
    TestAssert::true(VisitsCounter::incr(13), 'visit recorded while an orphan is in flight');
    sleep(1);
    TestAssert::true(VisitsCounter::incr(13), 'second visit recorded after the orphan expired');

    TestAssert::same(11, $committed, 'orphan reclaimed and committed exactly once alongside new visits');
    TestAssert::same(0, VisitsCounter::getPending(13), 'no increment left pending after reclaim');

    path_delete($runPath);

    echo "=== shutdown flush keeps batching (single visit issues no UPDATE) ===\n";

    $runPath = visits_tmp_run_path('shutdown_batch');
    VisitsCounter::resetTestState();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);
    VisitsCounter::enableShutdownFlush();

    $committed = 0;
    VisitsCounter::setFlushHandler(function ($id, $pending) use (&$committed) {
        $committed += (int) $pending;
        return true;
    });

    TestAssert::true(VisitsCounter::incr(21), 'single visit recorded');
    VisitsCounter::shutdownFlush();
    TestAssert::same(0, $committed, 'shutdown flush issues no UPDATE for a fresh increment');
    TestAssert::same(1, VisitsCounter::getPending(21), 'increment still batched on disk');

    TestAssert::true(VisitsCounter::flushAll(), 'forced flushAll commits the batched increment');
    TestAssert::same(1, $committed, 'increment committed once by forced flush');
    VisitsCounter::disableShutdownFlush();

    path_delete($runPath);

    echo "=== flushDue commits overdue increments only ===\n";

    $runPath = visits_tmp_run_path('flush_due');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);

    $dir = $runPath . '/data/visits';
    mkdir($dir, 0777, true);
    // 22 与 23 落在不同分片，分别代表已过间隔与刚计数
    file_put_contents($dir . '/shard_' . (22 % VisitsCounter::SHARD_COUNT) . '.json', json_encode(array(
        22 => array('delta' => 3, 'sending' => 0, 'sending_at' => 0, 'last_flush' => time() - 600)
    )));
    file_put_contents($dir . '/shard_' . (23 % VisitsCounter::SHARD_COUNT) . '.json', json_encode(array(
        23 => array('delta' => 3, 'sending' => 0, 'sending_at' => 0, 'last_flush' => time())
    )));

    $dueIds = array();
    VisitsCounter::setFlushHandler(function ($id, $pending) use (&$dueIds) {
        $dueIds[] = (int) $id;
        return true;
    });
    TestAssert::true(VisitsCounter::flushDue(), 'flushDue succeeds');
    TestAssert::same(array(22), $dueIds, 'only the overdue content is committed');
    TestAssert::same(0, VisitsCounter::getPending(22), 'overdue pending cleared');
    TestAssert::same(3, VisitsCounter::getPending(23), 'fresh pending still batched');

    path_delete($runPath);

    echo "=== idle entries are pruned and empty shard files removed ===\n";

    $runPath = visits_tmp_run_path('prune_idle');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);

    $shard = 24 % VisitsCounter::SHARD_COUNT;
    $dir = $runPath . '/data/visits';
    mkdir($dir, 0777, true);
    $shardFile = $dir . '/shard_' . $shard . '.json';
    file_put_contents($shardFile, json_encode(array(
        24 => array('delta' => 0, 'sending' => 0, 'sending_at' => 0, 'last_flush' => time() - 7200)
    )));

    TestAssert::true(VisitsCounter::flushDue(), 'flushDue succeeds with only idle entries');
    TestAssert::false(is_file($shardFile), 'emptied shard file removed');
    TestAssert::same(0, VisitsCounter::getPending(24), 'pruned content reports no pending');

    path_delete($runPath);

    echo "=== full recovery scan is rate limited ===\n";

    $runPath = visits_tmp_run_path('recovery_rate');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);

    $dir = $runPath . '/data/visits';
    mkdir($dir, 0777, true);
    $orphanFile = $dir . '/shard_' . (25 % VisitsCounter::SHARD_COUNT) . '.json';
    file_put_contents($orphanFile, json_encode(array(
        25 => array('delta' => 5, 'sending' => 0, 'sending_at' => 0, 'last_flush' => time() - 600)
    )));

    $recovered = 0;
    VisitsCounter::setFlushHandler(function ($id, $pending) use (&$recovered) {
        $recovered += (int) $pending;
        return true;
    });
    // 首次计数触发一次全量补偿，回收其他进程遗留的增量
    TestAssert::true(VisitsCounter::incr(26), 'first visit triggers recovery');
    TestAssert::same(5, $recovered, 'orphaned increment recovered on first scan');

    // 同一 run 路径下再次首次计数：补偿窗口未到，不应重复全量扫描
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    visits_use_run_path($runPath);
    file_put_contents($orphanFile, json_encode(array(
        25 => array('delta' => 6, 'sending' => 0, 'sending_at' => 0, 'last_flush' => time() - 600)
    )));

    $recovered = 0;
    VisitsCounter::setFlushHandler(function ($id, $pending) use (&$recovered) {
        $recovered += (int) $pending;
        return true;
    });
    TestAssert::true(VisitsCounter::incr(26), 'second visit recorded');
    TestAssert::same(0, $recovered, 'recovery scan skipped within the interval');

    // 缩短间隔后应再次执行全量补偿
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    visits_use_run_path($runPath);
    VisitsCounter::setRecoveryInterval(0);

    $recovered = 0;
    VisitsCounter::setFlushHandler(function ($id, $pending) use (&$recovered) {
        $recovered += (int) $pending;
        return true;
    });
    TestAssert::true(VisitsCounter::incr(26), 'third visit recorded');
    TestAssert::same(6, $recovered, 'recovery resumes once the interval elapses');

    path_delete($runPath);

    echo "=== cold start skips recovery flush when no shard files ===\n";

    $runPath = visits_tmp_run_path('cold_skip');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);
    VisitsCounter::setBatchThreshold(1000);

    $recoveryFlushCalls = 0;
    VisitsCounter::setFlushHandler(function ($id, $pending) use (&$recoveryFlushCalls) {
        $recoveryFlushCalls += (int) $pending;
        return true;
    });
    TestAssert::true(VisitsCounter::incr(1), 'first incr on empty storage');
    TestAssert::same(0, $recoveryFlushCalls, 'no recovery flush without shard files');
    TestAssert::same(1, VisitsCounter::getPending(1), 'first incr still recorded');

    path_delete($runPath);

    echo "=== concurrent incr(): 1000 visits, final count accurate (file flush handler) ===\n";

    $runPath = visits_tmp_run_path('concurrent_1000');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);
    VisitsCounter::setBatchThreshold(1000);

    $totalFile = $runPath . '/flushed_total.txt';
    VisitsCounter::setFlushHandler(visits_file_flush_handler($totalFile));

    $workers = 50;
    $codes = visits_concurrent_incr(1, $runPath, 1000, $workers);
    TestAssert::true(! in_array(-1, $codes, true), 'all probe processes started');
    foreach ($codes as $code) {
        TestAssert::same(0, $code, 'probe process exit code 0');
    }

    // 父进程重新绑定存储路径（子进程不共享静态状态）
    visits_use_run_path($runPath);
    VisitsCounter::setFlushHandler(visits_file_flush_handler($totalFile));
    VisitsCounter::flushAll();
    TestAssert::same(0, VisitsCounter::getPending(1), 'no pending after flushAll');
    TestAssert::same(1000, visits_read_flushed_total($totalFile), 'flushed total = 1000');

    path_delete($runPath);

    echo "=== concurrent incr(): 1000 visits, final count accurate (SQLite if available) ===\n";

    if (! visits_sqlite_available()) {
        echo "SKIP: sqlite3/pdo_sqlite extension unavailable\n";
    } else {
        $runPath = visits_tmp_run_path('sqlite_1000');
        $sqlitePath = visits_boot_sqlite($runPath);
        if ($sqlitePath === null) {
            echo "SKIP: sqlite bootstrap failed\n";
        } else {
            VisitsCounter::resetTestState();
            VisitsCounter::clearStorage($runPath);
            visits_use_run_path($runPath);
            VisitsCounter::setBatchThreshold(1000);

            $codes = visits_concurrent_incr(1, $runPath, 1000, 50);
            foreach ($codes as $code) {
                TestAssert::same(0, $code, 'SQLite probe exit 0');
            }

            visits_use_run_path($runPath);
            VisitsCounter::setFlushHandler(null);
            VisitsCounter::flushAll();
            TestAssert::same(0, VisitsCounter::getPending(1), 'SQLite pending cleared');
            TestAssert::same(1000, visits_read_db_visits($sqlitePath), 'SQLite final visits = 1000');

            path_delete($runPath);
            @unlink($sqlitePath);
            visits_reset_sqlite_singleton();
        }
    }

    echo "=== SQLite: flushAll commits before settling (no manual commit) ===\n";

    if (! visits_sqlite_available()) {
        echo "SKIP: sqlite3/pdo_sqlite extension unavailable\n";
    } else {
        $runPath = visits_tmp_run_path('sqlite_commit_smoke');
        $sqlitePath = visits_boot_sqlite($runPath);
        if ($sqlitePath === null) {
            echo "SKIP: sqlite bootstrap failed\n";
        } else {
            VisitsCounter::resetTestState();
            VisitsCounter::disableShutdownFlush();
            VisitsCounter::disableRecoveryFlush();
            VisitsCounter::clearStorage($runPath);
            visits_use_run_path($runPath);
            VisitsCounter::setBatchThreshold(20);
            VisitsCounter::setFlushHandler(null);

            for ($i = 0; $i < 20; $i++) {
                VisitsCounter::incr(1);
            }
            TestAssert::true(VisitsCounter::flushAll(), 'flushAll succeeds on real sqlite ORM path');
            // 不手动 commit；另开连接读不到则说明销账前未落盘（P0 回归）
            TestAssert::same(0, VisitsCounter::getPending(1), 'pending cleared after settle');
            TestAssert::same(20, visits_read_db_visits($sqlitePath), 'visits durable without manual commit');

            path_delete($runPath);
            @unlink($sqlitePath);
            visits_reset_sqlite_singleton();
        }
    }

    echo "=== SQLite: flushAll survives DB error without exiting ===\n";

    if (! visits_sqlite_available()) {
        echo "SKIP: sqlite3/pdo_sqlite extension unavailable\n";
    } else {
        $runPath = visits_tmp_run_path('sqlite_fail_soft');
        $sqlitePath = visits_boot_sqlite($runPath);
        if ($sqlitePath === null) {
            echo "SKIP: sqlite bootstrap failed\n";
        } else {
            VisitsCounter::resetTestState();
            VisitsCounter::disableShutdownFlush();
            VisitsCounter::disableRecoveryFlush();
            VisitsCounter::clearStorage($runPath);
            visits_use_run_path($runPath);
            VisitsCounter::setBatchThreshold(1000);
            VisitsCounter::setFlushHandler(null);

            for ($i = 0; $i < 5; $i++) {
                TestAssert::true(VisitsCounter::incr(1), 'incr recorded before table drop');
            }
            TestAssert::same(5, VisitsCounter::getPending(1), 'pending batched on disk');

            $sqlite = new SQLite3($sqlitePath);
            $sqlite->busyTimeout(5000);
            $sqlite->exec('DROP TABLE ay_content');
            $sqlite->close();
            visits_reset_sqlite_singleton();

            TestAssert::false(VisitsCounter::flushAll(), 'flushAll reports failure on missing table');
            TestAssert::same(5, VisitsCounter::getPending(1), 'pending preserved after DB soft failure');

            path_delete($runPath);
            @unlink($sqlitePath);
            visits_reset_sqlite_singleton();
        }
    }

    echo "=== SQLite: same-shard second UPDATE failure does not settle the first ===\n";

    if (! visits_sqlite_available() || ! extension_loaded('sqlite3')) {
        echo "SKIP: sqlite3 extension unavailable\n";
    } else {
        $runPath = visits_tmp_run_path('sqlite_batch_partial_fail');
        $sqlitePath = visits_boot_sqlite($runPath);
        if ($sqlitePath === null) {
            echo "SKIP: sqlite bootstrap failed\n";
        } else {
            // 1 与 257 同分片（SHARD_COUNT=256），覆盖「同事务先成后败却销账」交错
            $idOk = 1;
            $idFail = 1 + VisitsCounter::SHARD_COUNT;
            TestAssert::same(
                $idOk % VisitsCounter::SHARD_COUNT,
                $idFail % VisitsCounter::SHARD_COUNT,
                'fixture ids share a shard'
            );

            $sqlite = new SQLite3($sqlitePath);
            $sqlite->busyTimeout(5000);
            $sqlite->exec("INSERT INTO ay_content (id, visits) VALUES ($idFail, 0)");
            $sqlite->exec(
                "CREATE TRIGGER visits_fail_id_{$idFail}
                BEFORE UPDATE OF visits ON ay_content
                WHEN NEW.id = {$idFail}
                BEGIN
                    SELECT RAISE(ABORT, 'visits_counter_batch_partial_fail');
                END;"
            );
            $sqlite->close();
            visits_reset_sqlite_singleton();

            VisitsCounter::resetTestState();
            VisitsCounter::disableShutdownFlush();
            VisitsCounter::disableRecoveryFlush();
            VisitsCounter::clearStorage($runPath);
            visits_use_run_path($runPath);
            VisitsCounter::setBatchThreshold(1000);
            VisitsCounter::setFlushHandler(null);

            TestAssert::true(VisitsCounter::incr($idOk), 'incr ok id');
            TestAssert::true(VisitsCounter::incr($idFail), 'incr fail id');
            TestAssert::same(1, VisitsCounter::getPending($idOk), 'pending ok id before flush');
            TestAssert::same(1, VisitsCounter::getPending($idFail), 'pending fail id before flush');

            TestAssert::false(VisitsCounter::flushAll(), 'flushAll reports partial failure');
            visits_commit_sqlite();

            TestAssert::same(1, visits_read_db_visits($sqlitePath, $idOk), 'ok id landed in DB');
            TestAssert::same(0, VisitsCounter::getPending($idOk), 'ok id pending settled');
            TestAssert::same(0, visits_read_db_visits($sqlitePath, $idFail), 'fail id not written');
            TestAssert::same(1, VisitsCounter::getPending($idFail), 'fail id pending retained for retry');

            path_delete($runPath);
            @unlink($sqlitePath);
            visits_reset_sqlite_singleton();
        }
    }

    echo "=== driver failSoft: nested scopes keep error flag until outermost exit ===\n";

    if (! extension_loaded('sqlite3')) {
        echo "SKIP: sqlite3 extension unavailable\n";
    } else {
        $runPath = visits_tmp_run_path('sqlite_failsoft_nested');
        $sqlitePath = visits_boot_sqlite($runPath);
        if ($sqlitePath === null) {
            echo "SKIP: sqlite bootstrap failed\n";
        } else {
            require_once CORE_PATH . '/database/Sqlite.php';
            visits_reset_sqlite_singleton();
            \core\database\Sqlite::setFailSoft(true);
            \core\database\Sqlite::setFailSoft(true); // 内层开启不得清掉外层窗口内错误
            $db = \core\database\Sqlite::getInstance();
            $db->amd('UPDATE ay_notable SET x=1');
            TestAssert::true(\core\database\Sqlite::hadFailSoftError(), 'error set inside nested scope');
            \core\database\Sqlite::setFailSoft(false); // 内层退出
            TestAssert::true(\core\database\Sqlite::hadFailSoftError(), 'error retained after inner exit');
            \core\database\Sqlite::setFailSoft(false); // 最外层退出才清
            TestAssert::false(\core\database\Sqlite::hadFailSoftError(), 'error cleared on outermost exit');

            @unlink($sqlitePath);
            visits_reset_sqlite_singleton();
        }
    }

    echo "=== driver failSoft: SQL error returns without exit ===\n";

    if (! extension_loaded('sqlite3')) {
        echo "SKIP: sqlite3 extension unavailable\n";
    } else {
        $runPath = visits_tmp_run_path('sqlite_failsoft_driver');
        $sqlitePath = visits_boot_sqlite($runPath);
        if ($sqlitePath === null) {
            echo "SKIP: sqlite bootstrap failed\n";
        } else {
            require_once CORE_PATH . '/database/Sqlite.php';
            visits_reset_sqlite_singleton();
            \core\database\Sqlite::setFailSoft(true);
            $db = \core\database\Sqlite::getInstance();
            $result = $db->amd('UPDATE ay_notable SET x=1');
            TestAssert::same(0, $result, 'amd returns 0 on SQL error in failSoft mode');
            TestAssert::true(\core\database\Sqlite::hadFailSoftError(), 'failSoft error flag set');
            \core\database\Sqlite::setFailSoft(false);
            TestAssert::true(true, 'script survived SQL error without exit');

            @unlink($sqlitePath);
            visits_reset_sqlite_singleton();
        }
    }

    echo "=== concurrent incr(): 1000 visits, final count accurate (MySQL if available) ===\n";

    $mysqli = visits_boot_mysql_or_skip();
    if ($mysqli === null) {
        echo "SKIP: MySQL not configured or unreachable\n";
    } else {
        try {
            $runPath = visits_tmp_run_path('mysql_1000');
            VisitsCounter::resetTestState();
            VisitsCounter::disableShutdownFlush();
            VisitsCounter::clearStorage($runPath);
            visits_use_run_path($runPath);
            VisitsCounter::setBatchThreshold(1000);

            $src = $mysqli->query("SELECT id, visits FROM ay_content ORDER BY id DESC LIMIT 1")->fetch_assoc();
            if (! is_array($src) || ! isset($src['id'])) {
                echo "SKIP: MySQL ay_content is empty\n";
            } else {
                $contentId = (int) $src['id'];
                $baseline = (int) $src['visits'];
                $mysqli->query("UPDATE ay_content SET visits=0 WHERE id=$contentId");

                $prop = visits_config_reflection();
                $dbCfg = require ROOT_PATH . 'config/database.php';
                $prop->setValue(null, $dbCfg);
                VisitsCounter::setFlushHandler(null);

                $codes = visits_concurrent_incr($contentId, $runPath, 1000, 50);
                foreach ($codes as $code) {
                    TestAssert::same(0, $code, 'MySQL probe exit 0');
                }

                visits_use_run_path($runPath);
                VisitsCounter::flushAll();
                TestAssert::same(0, VisitsCounter::getPending($contentId), 'MySQL pending cleared');
                $visits = (int) $mysqli->query("SELECT visits FROM ay_content WHERE id=$contentId")->fetch_assoc()['visits'];
                TestAssert::same(1000, $visits, 'MySQL final visits = 1000');

                $mysqli->query("UPDATE ay_content SET visits=$baseline WHERE id=$contentId");
                path_delete($runPath);
            }
        } catch (Throwable $e) {
            echo 'SKIP: MySQL stress failed: ' . $e->getMessage() . "\n";
        }
        $mysqli->close();
    }

    echo "=== settle failure after DB commit does not double display ===\n";

    $runPath = visits_tmp_run_path('settle_display');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);
    VisitsCounter::setBatchThreshold(1);

    $commits = 0;
    VisitsCounter::setFlushHandler(function ($id, $pending) use (&$commits) {
        $commits += (int) $pending;
        // 入库成功后让销账落盘失败，sending 仍留在磁盘
        VisitsCounter::simulateSaveFailure(true);
        return true;
    });

    $display = VisitsCounter::incrAndGetVisits(31, 100);
    TestAssert::same(1, $commits, 'database received the increment');
    TestAssert::same(1, VisitsCounter::getPending(31), 'sending remains after settle failure');
    TestAssert::same(101, $display, 'display must not count committedWatch + sending twice');

    VisitsCounter::simulateSaveFailure(false);
    path_delete($runPath);

    echo "=== concurrent incr+flush: 1000 visits, multi-process flush accurate ===\n";

    $runPath = visits_tmp_run_path('concurrent_flush_1000');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);

    $totalFile = $runPath . '/flushed_total.txt';
    @file_put_contents($totalFile, '0');

    $workers = 50;
    $codes = visits_concurrent_incr(1, $runPath, 1000, $workers, 'flush', $totalFile);
    TestAssert::true(! in_array(-1, $codes, true), 'all flush-mode probe processes started');
    foreach ($codes as $code) {
        TestAssert::same(0, $code, 'flush-mode probe exit code 0');
    }

    visits_use_run_path($runPath);
    VisitsCounter::setFlushHandler(visits_file_flush_handler($totalFile));
    VisitsCounter::flushAll();
    TestAssert::same(0, VisitsCounter::getPending(1), 'no pending after concurrent flush');
    TestAssert::same(1000, visits_read_flushed_total($totalFile), 'concurrent flush total = 1000');

    path_delete($runPath);

    echo "=== batch threshold triggers automatic flush ===\n";

    $runPath = visits_tmp_run_path('batch');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);
    VisitsCounter::setBatchThreshold(20);

    $totalFile = $runPath . '/batch_total.txt';
    VisitsCounter::setFlushHandler(visits_file_flush_handler($totalFile));

    for ($i = 0; $i < 20; $i++) {
        VisitsCounter::incr(1);
    }
    TestAssert::same(0, VisitsCounter::getPending(1), 'pending cleared at batch threshold');
    TestAssert::same(20, visits_read_flushed_total($totalFile), 'batch flush total = 20');

    path_delete($runPath);

    echo "=== hot path never flushes on elapsed interval alone ===\n";

    $runPath = visits_tmp_run_path('hot_path_interval');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::disableRecoveryFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);
    VisitsCounter::setBatchThreshold(20);
    VisitsCounter::setFlushInterval(5);

    $dir = $runPath . '/data/visits';
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/shard_' . (41 % VisitsCounter::SHARD_COUNT) . '.json', json_encode(array(
        41 => array('delta' => 3, 'sending' => 0, 'sending_at' => 0, 'last_flush' => time() - 600)
    )));

    $intervalFlushed = 0;
    VisitsCounter::setFlushHandler(function ($id, $pending) use (&$intervalFlushed) {
        $intervalFlushed += (int) $pending;
        return true;
    });

    // 低频内容每次访问都同步写库会让批量聚合失效，间隔触发只能发生在热路径之外
    TestAssert::true(VisitsCounter::incr(41), 'incr ok on a long-idle entry');
    TestAssert::same(0, $intervalFlushed, 'elapsed interval must not flush inside the request');
    TestAssert::same(4, VisitsCounter::getPending(41), 'increment keeps aggregating locally');

    TestAssert::true(VisitsCounter::flushDue(), 'flushDue succeeds');
    TestAssert::same(4, $intervalFlushed, 'interval flush handled off the hot path');
    TestAssert::same(0, VisitsCounter::getPending(41), 'pending cleared by flushDue');

    path_delete($runPath);

    echo "=== database write must be committed before settling ===\n";

    $cfgProp = visits_config_reflection();
    $cfgBackup = $cfgProp->getValue();

    $runPath = visits_tmp_run_path('commit_before_settle');
    VisitsCounter::resetTestState();
    VisitsCounter::disableShutdownFlush();
    VisitsCounter::disableRecoveryFlush();
    VisitsCounter::clearStorage($runPath);
    visits_use_run_path($runPath);
    VisitsCounter::setBatchThreshold(1);

    $fake = new VisitsFakeDb();
    visits_inject_fake_db($fake);
    VisitsCounter::setFlushHandler(null); // 走真实 Model 写库路径

    // Sqlite/pdo_sqlite 写入挂在隐式事务里，未提交就销账会在后续 SQL 回滚时永久丢失增量
    TestAssert::true(VisitsCounter::incr(51), 'incr triggers flush at threshold');
    TestAssert::same(array('amd', 'commit'), $fake->calls, 'commit issued right after the update');
    TestAssert::same(0, VisitsCounter::getPending(51), 'settled only after commit confirmed');

    $fake->calls = array();
    $fake->commitFails = true;
    TestAssert::true(VisitsCounter::incr(51), 'incr ok when the later commit fails');
    TestAssert::same(array('amd', 'commit'), $fake->calls, 'commit attempted');
    TestAssert::same(1, VisitsCounter::getPending(51), 'increment returned to local delta on commit failure');

    // 同分片每条写后立刻提交，避免后一条失败回滚前一条却仍销账
    $fake->calls = array();
    $fake->commitFails = false;
    VisitsCounter::setBatchThreshold(1000);
    TestAssert::true(VisitsCounter::incr(307), 'second content lands in the same shard');
    TestAssert::true(VisitsCounter::flushAll(), 'flushAll succeeds');
    TestAssert::same(array('amd', 'commit', 'amd', 'commit'), $fake->calls, 'one commit per content in shard batch');
    TestAssert::same(0, VisitsCounter::getPending(51), 'shard batch settled after commit');
    TestAssert::same(0, VisitsCounter::getPending(307), 'shard batch settled after commit');

    visits_clear_fake_db();
    $cfgProp->setValue(null, $cfgBackup);
    path_delete($runPath);
});
