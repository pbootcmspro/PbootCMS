<?php

declare(strict_types=1);

/**
 * @suite integration
 * @covers 首次登录切换 MySQL 的导入、清理与文件锁
 *
 * 运行：
 *   set PBOOT_TEST_MYSQL_URL=mysql://root:pw@127.0.0.1:3307/pboot_init_test
 *   php tests/run.php --filter=InitMysqlSwitch
 *
 * 未设置时 SKIP（exit 0）。库名必须包含 test 或 init，且应为空库。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

function init_switch_controller(): array
{
    if (! class_exists('app\\admin\\controller\\IndexController', false)) {
        require_once APP_PATH . '/admin/controller/IndexController.php';
    }
    $ref = new ReflectionClass('app\\admin\\controller\\IndexController');
    $obj = $ref->newInstanceWithoutConstructor();
    return array($ref, $obj);
}

function init_switch_call($ref, $obj, string $name, array $args)
{
    $method = $ref->getMethod($name);
    if (PHP_VERSION_ID < 80100) {
        $method->setAccessible(true);
    }
    return $method->invokeArgs($obj, $args);
}

function init_switch_mysql_cfg(string $url)
{
    $parts = parse_url($url);
    if (! is_array($parts) || empty($parts['host']) || empty($parts['path'])) {
        return null;
    }
    $name = ltrim((string) $parts['path'], '/');
    if ($name === '' || ! preg_match('/test|init/i', $name)) {
        return null;
    }
    return array(
        'host' => $parts['host'],
        'port' => isset($parts['port']) ? (int) $parts['port'] : 3306,
        'user' => isset($parts['user']) ? rawurldecode($parts['user']) : '',
        'passwd' => isset($parts['pass']) ? rawurldecode($parts['pass']) : '',
        'dbname' => $name
    );
}

return TestAssert::runSuite(function () {
    echo "=== 持锁时第二次非阻塞加锁失败 ===\n";
    $dir = ROOT_PATH . 'runtime' . DIRECTORY_SEPARATOR . 'archive';
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $lock = $dir . DIRECTORY_SEPARATOR . 'init_db.lock';
    $fp = fopen($lock, 'c');
    TestAssert::true($fp !== false && flock($fp, LOCK_EX | LOCK_NB), 'parent acquires init lock');
    $php = (defined('PHP_BINARY') && PHP_BINARY !== '') ? PHP_BINARY : 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'init_lock_probe.php';
    $cmd = $php . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($lock);
    $pipes = array();
    $proc = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    TestAssert::true(is_resource($proc) || is_object($proc), 'lock probe started');
    $out = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    flock($fp, LOCK_UN);
    fclose($fp);
    @unlink($lock);
    TestAssert::contains((string) $out, 'BUSY', 'second lock is rejected');

    $url = getenv('PBOOT_TEST_MYSQL_URL');
    if (! is_string($url) || trim($url) === '') {
        echo "SKIP: set PBOOT_TEST_MYSQL_URL to enable MySQL switch import\n";
        return;
    }
    if (! extension_loaded('mysqli')) {
        echo "SKIP: mysqli extension required\n";
        return;
    }
    $cfg = init_switch_mysql_cfg(trim($url));
    if ($cfg === null) {
        echo "SKIP: PBOOT_TEST_MYSQL_URL database name must contain test or init\n";
        return;
    }
    if (function_exists('mysqli_report')) {
        mysqli_report(MYSQLI_REPORT_OFF);
    }
    $conn = @new mysqli($cfg['host'], $cfg['user'], $cfg['passwd'], $cfg['dbname'], $cfg['port']);
    if ($conn->connect_errno) {
        echo "SKIP: mysqli unreachable\n";
        return;
    }

    $ref = null;
    $obj = null;
    $sqlFile = ROOT_PATH . 'static/backup/sql/pbootcms.sql';
    try {
        list($ref, $obj) = init_switch_controller();
        init_switch_call($ref, $obj, 'dropAyTables', array($conn));

        echo "=== 导入成功 ===\n";
        TestAssert::true(init_switch_call($ref, $obj, 'importSql', array($conn, $sqlFile)) === true, 'importSql succeeds');
        TestAssert::same(0, $conn->errno, 'errno is 0 after import');
        $names = init_switch_call($ref, $obj, 'ayTables', array($conn));
        TestAssert::true(is_array($names) && count($names) === 30, 'import creates 30 ay_ tables');
        $nbsp = $conn->query("SELECT COUNT(*) FROM ay_content WHERE content LIKE '%&nbsp;%'");
        $nbspRow = $nbsp ? $nbsp->fetch_row() : array('0');
        if ($nbsp) {
            $nbsp->free();
        }
        TestAssert::same(12, (int) $nbspRow[0], 'ay_content keeps 12 nbsp rows');

        if (extension_loaded('pdo_sqlite')) {
            echo "=== 导入结果与 SQLite 种子一致 ===\n";
            $pdo = new PDO('sqlite:' . ROOT_PATH . 'data/pbootcms.db', null, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
            $sqliteTables = array();
            $tableRows = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tableRows as $name) {
                $cols = array();
                foreach ($pdo->query('PRAGMA table_info(' . $name . ')') as $col) {
                    $cols[] = $col['name'];
                }
                sort($cols);
                $count = (int) $pdo->query('SELECT COUNT(*) FROM "' . str_replace('"', '""', $name) . '"')->fetchColumn();
                $sqliteTables[$name] = array('cols' => $cols, 'count' => $count);
            }
            $mysqlTables = array();
            foreach ($names as $name) {
                $cols = array();
                $colRes = $conn->query('SHOW COLUMNS FROM `' . $name . '`');
                while ($col = $colRes->fetch_assoc()) {
                    $cols[] = $col['Field'];
                }
                $colRes->free();
                sort($cols);
                $countRes = $conn->query('SELECT COUNT(*) FROM `' . $name . '`');
                $countRow = $countRes->fetch_row();
                $countRes->free();
                $mysqlTables[$name] = array('cols' => $cols, 'count' => (int) $countRow[0]);
            }
            $sqliteNames = array_keys($sqliteTables);
            $mysqlNames = array_keys($mysqlTables);
            sort($sqliteNames);
            sort($mysqlNames);
            TestAssert::same($sqliteNames, $mysqlNames, 'table names match sqlite seed');
            foreach ($sqliteNames as $name) {
                TestAssert::same($sqliteTables[$name]['cols'], $mysqlTables[$name]['cols'], $name . ' columns match');
                TestAssert::same($sqliteTables[$name]['count'], $mysqlTables[$name]['count'], $name . ' row count matches');
            }
        } else {
            echo "SKIP: pdo_sqlite required for seed compare\n";
        }

        echo "=== 目标库已有 ay_ 表 ===\n";
        $kept = init_switch_call($ref, $obj, 'ayTables', array($conn));
        TestAssert::true(is_array($kept) && count($kept) > 0, 'ayTables is non-empty before reject');
        $marker = $conn->query('SELECT COUNT(*) FROM ay_content');
        $markerRow = $marker->fetch_row();
        $marker->free();
        TestAssert::true((int) $markerRow[0] > 0, 'existing ay_ rows are still present');

        init_switch_call($ref, $obj, 'dropAyTables', array($conn));
        $cleared = init_switch_call($ref, $obj, 'ayTables', array($conn));
        TestAssert::true(is_array($cleared) && count($cleared) === 0, 'dropAyTables removes ay_ tables');

        echo "=== 导入中途失败后清理 ===\n";
        $sql = file_get_contents($sqlFile);
        TestAssert::false($sql === false, 'seed sql readable');
        $pos = strpos($sql, 'CREATE TABLE `ay_content`');
        TestAssert::true($pos !== false, 'injection point found');
        $broken = substr($sql, 0, $pos) . "SELECT * FROM ay_init_missing_table;\n" . substr($sql, $pos);
        $brokenFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot_init_broken_' . getmypid() . '.sql';
        file_put_contents($brokenFile, $broken);
        TestAssert::false(init_switch_call($ref, $obj, 'importSql', array($conn, $brokenFile)) === true, 'broken import returns false');
        TestAssert::true($conn->errno !== 0, 'errno is set after broken import');
        $partial = init_switch_call($ref, $obj, 'ayTables', array($conn));
        TestAssert::true(is_array($partial) && count($partial) > 0, 'partial ay_ tables exist before cleanup');
        TestAssert::true(init_switch_call($ref, $obj, 'dropAyTables', array($conn)) === true, 'cleanup drops partial tables');
        $after = init_switch_call($ref, $obj, 'ayTables', array($conn));
        TestAssert::true(is_array($after) && count($after) === 0, 'no ay_ tables remain');
        @unlink($brokenFile);
    } finally {
        if ($conn instanceof mysqli) {
            if ($ref) {
                init_switch_call($ref, $obj, 'dropAyTables', array($conn));
            }
            $conn->close();
        }
    }
});
