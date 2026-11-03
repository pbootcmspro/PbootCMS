<?php
/**
 * ExtField MySQL 索引上限修复验收探测
 *
 * 用法:
 *   php tests/scripts/extfield_index_limit_probe.php <scenario> [args...]
 *
 * scenario:
 *   mysql_matrix        MySQL MyISAM/InnoDB x mysqli/pdo_mysql 主验收
 *   mysql_orphan_retry  物理列存在、定义缺失的同名重试
 *   mysql_preserve_idx  确认主键/contentid/既有扩展索引保留
 *   sqlite_100          sqlite 或 pdo_sqlite 新增至 100 字段并读写
 *   syntax_check        仅语法检查 ExtFieldController
 *
 * 环境变量:
 *   PBOOT_PROBE_MYSQL_HOST / USER / PASS / PORT
 *   PBOOT_PROBE_DB_PREFIX   默认 pboot_fix334_
 */

ini_set('display_errors', '1');
$errorLevel = E_ALL;
if (defined('E_STRICT') && PHP_VERSION_ID < 80400) {
    $errorLevel = $errorLevel & ~E_STRICT;
}
if (defined('E_DEPRECATED')) {
    $errorLevel = $errorLevel & ~E_DEPRECATED;
}
error_reporting($errorLevel);

$scenario = isset($argv[1]) ? (string) $argv[1] : '';
if ($scenario === '') {
    fwrite(STDERR, "missing scenario\n");
    exit(2);
}

$sourcePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
$sourcePath = realpath($sourcePath) . DIRECTORY_SEPARATOR;

define('ROOT_PATH', $sourcePath);
define('APP_PATH', $sourcePath . 'apps');
define('CORE_PATH', $sourcePath . 'core');
define('CONF_PATH', $sourcePath . 'config');
define('DOC_PATH', $sourcePath);
define('DATA_DIR', '/data');
define('SITE_DIR', '');
define('CORE_DIR', '/core');
define('APP_VERSION', 'probe');
define('RELEASE_TIME', '0');
define('START_TIME', microtime(true));

$runPath = sys_get_temp_dir() . '/pboot_extfield_probe_' . getmypid();
if (! is_dir($runPath)) {
    @mkdir($runPath, 0777, true);
}
define('RUN_PATH', $runPath);

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/function/helper.php';
require CORE_PATH . '/function/file.php';
require CORE_PATH . '/basic/Config.php';
require CORE_PATH . '/basic/Basic.php';
require CORE_PATH . '/database/Builder.php';

spl_autoload_register('core\\basic\\Basic::autoLoad', true, true);

require dirname(__DIR__) . '/support/DatabaseTestSupport.php';
require __DIR__ . '/extfield_probe_intercept.php';

function probe_print($key, $value)
{
    echo $key . '=' . $value . "\n";
}

function probe_fail($msg)
    {
        probe_print('RESULT', 'FAIL');
        probe_print('ERROR', $msg);
        exit(1);
}

function probe_ok($msg = '')
    {
        probe_print('RESULT', 'PASS');
        if ($msg !== '') {
            probe_print('NOTE', $msg);
        }
        exit(0);
}

function probe_skip($msg)
    {
        probe_print('RESULT', 'SKIP');
        probe_print('SKIP', $msg);
        exit(3);
}

function probe_mysql_cfg()
    {
        $host = getenv('PBOOT_PROBE_MYSQL_HOST');
        if ($host === false || $host === '') {
            $host = '192.168.1.4';
        }
        $user = getenv('PBOOT_PROBE_MYSQL_USER');
        if ($user === false || $user === '') {
            $user = 'root';
        }
        $pass = getenv('PBOOT_PROBE_MYSQL_PASS');
        if ($pass === false) {
            $pass = '123456';
        }
        $port = getenv('PBOOT_PROBE_MYSQL_PORT');
        if ($port === false || $port === '') {
            $port = '3306';
        }
        $prefix = getenv('PBOOT_PROBE_DB_PREFIX');
        if ($prefix === false || $prefix === '') {
            $prefix = 'pboot_fix334_';
        }
        return array(
            'host' => $host,
            'user' => $user,
            'passwd' => $pass,
            'port' => (int) $port,
            'prefix' => $prefix
        );
}

function probe_require_mysqli()
    {
        if (! extension_loaded('mysqli')) {
            probe_skip('mysqli unavailable');
        }
}

function probe_admin_mysqli($cfg)
    {
        $m = @new mysqli($cfg['host'], $cfg['user'], $cfg['passwd'], '', $cfg['port']);
        if ($m->connect_errno) {
            probe_fail('mysql connect failed: ' . $m->connect_error);
        }
        $m->set_charset('utf8');
        return $m;
}

function probe_exec_multi(mysqli $m, $sql)
    {
        if (! $m->multi_query($sql)) {
            probe_fail('multi_query failed: ' . $m->error);
        }
        do {
            if ($res = $m->store_result()) {
                $res->free();
            }
        } while ($m->more_results() && $m->next_result());
        if ($m->errno) {
            probe_fail('multi_query follow failed: ' . $m->error);
        }
}

function probe_schema_sql($engine)
    {
        $engine = strtoupper($engine) === 'INNODB' ? 'InnoDB' : 'MyISAM';
        return "
DROP TABLE IF EXISTS ay_extfield;
DROP TABLE IF EXISTS ay_content_ext;
DROP TABLE IF EXISTS ay_content;
CREATE TABLE ay_content (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  title varchar(200) NOT NULL DEFAULT '',
  PRIMARY KEY (id)
) ENGINE={$engine} DEFAULT CHARSET=utf8;
CREATE TABLE ay_content_ext (
  extid int(10) unsigned NOT NULL AUTO_INCREMENT,
  contentid int(10) unsigned NOT NULL,
  ext_price varchar(100) DEFAULT NULL COMMENT '产品价格',
  ext_type varchar(100) DEFAULT NULL COMMENT '类型',
  ext_color varchar(100) DEFAULT NULL COMMENT '颜色',
  PRIMARY KEY (extid),
  KEY ay_content_ext_contentid (contentid),
  KEY ay_content_ext_color_index (ext_color),
  KEY ay_content_ext_price_index (ext_price),
  KEY ay_content_ext_type_index (ext_type)
) ENGINE={$engine} DEFAULT CHARSET=utf8;
CREATE TABLE ay_extfield (
  id int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '编号',
  mcode varchar(20) NOT NULL COMMENT '模型编码',
  name varchar(30) NOT NULL COMMENT '字段名称',
  type char(2) NOT NULL COMMENT '字段类型',
  value varchar(500) NOT NULL COMMENT '单选或多选值',
  description varchar(30) NOT NULL COMMENT '描述文本',
  sorting int(11) NOT NULL COMMENT '排序',
  PRIMARY KEY (id),
  KEY ay_extfield_mcode (mcode)
) ENGINE={$engine} DEFAULT CHARSET=utf8;
INSERT INTO ay_extfield (id,mcode,name,type,value,description,sorting) VALUES
(1,'3','ext_price','1','','产品价格',255),
(2,'3','ext_type','4','基础版,专业版,旗舰版','类型',255),
(3,'3','ext_color','4','红色,橙色,黄色,绿色,蓝色,紫色','颜色',255);
INSERT INTO ay_content (id, title) VALUES (1, 'seed');
INSERT INTO ay_content_ext (extid,contentid,ext_price,ext_type,ext_color) VALUES
(1,1,'80','专业版','红色');
";
}

function probe_boot_app_mysql($cfg, $dbname, $type)
    {
        db_reset_all_drivers();
        db_set_database_config(array(
            'type' => $type,
            'host' => $cfg['host'],
            'user' => $cfg['user'],
            'passwd' => $cfg['passwd'],
            'port' => $cfg['port'],
            'dbname' => $dbname
        ));
        $prop = db_config_reflection();
        $configs = $prop->getValue();
        if (! is_array($configs)) {
            $configs = array();
        }
        $configs['debug'] = true;
        $configs['database']['transaction'] = false;
        $prop->setValue(null, $configs);
}

function probe_index_count(mysqli $m, $dbname)
    {
        $sql = "SELECT COUNT(DISTINCT INDEX_NAME) AS c FROM information_schema.statistics
                WHERE table_schema='" . $m->real_escape_string($dbname) . "' AND table_name='ay_content_ext'";
        $res = $m->query($sql);
        if (! $res) {
            probe_fail('index count query failed: ' . $m->error);
        }
        $row = $res->fetch_assoc();
        $res->free();
        return (int) $row['c'];
}

function probe_index_names(mysqli $m, $dbname)
    {
        $sql = "SELECT DISTINCT INDEX_NAME FROM information_schema.statistics
                WHERE table_schema='" . $m->real_escape_string($dbname) . "' AND table_name='ay_content_ext'
                ORDER BY INDEX_NAME";
        $res = $m->query($sql);
        $names = array();
        while ($row = $res->fetch_assoc()) {
            $names[] = $row['INDEX_NAME'];
        }
        $res->free();
        return $names;
}

function probe_column_count(mysqli $m)
    {
        $res = $m->query('SHOW COLUMNS FROM ay_content_ext');
        $n = 0;
        while ($res->fetch_assoc()) {
            $n++;
        }
        $res->free();
        // subtract extid, contentid
        return $n - 2;
}

function probe_def_count(mysqli $m)
    {
        $res = $m->query('SELECT COUNT(*) AS c FROM ay_extfield');
        $row = $res->fetch_assoc();
        $res->free();
        return (int) $row['c'];
}

function probe_call_add($mcode, $name, $type, $description)
    {
        $GLOBALS['__extfield_probe_result'] = null;
        $_POST = array(
            'mcode' => (string) $mcode,
            'name' => (string) $name,
            'type' => (string) $type,
            'value' => '',
            'description' => (string) $description,
            'sorting' => '255',
            'formcheck' => 'probe'
        );
        $_GET = array();
        $_SESSION = array('username' => 'probe');
        if (! isset($_SERVER['REMOTE_ADDR'])) {
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        }
        if (! isset($_SERVER['HTTP_USER_AGENT'])) {
            $_SERVER['HTTP_USER_AGENT'] = 'PbootProbe';
        }

        $ctrl = new \app\admin\controller\content\ExtFieldController();
        try {
            $ctrl->add();
        } catch (\RuntimeException $e) {
            if (strpos($e->getMessage(), 'PROBE_') !== 0) {
                throw $e;
            }
        }
        return $GLOBALS['__extfield_probe_result'];
}

function probe_assert_success($result, $context)
    {
        if (! is_array($result) || $result['type'] !== 'success') {
            $msg = is_array($result) ? ($result['type'] . ':' . $result['msg']) : 'null';
            probe_fail($context . ' expected success, got ' . $msg);
        }
}

function probe_fill_to_defs(mysqli $admin, $targetDefs, $startMcode)
    {
        $current = probe_def_count($admin);
        $i = 0;
        while ($current < $targetDefs) {
            $i++;
            $mcode = (($i % 2) === 0) ? '2' : '3'; // 多模型交替
            if ($startMcode !== null) {
                $mcode = $startMcode;
            }
            $name = 'probe_' . $i . '_' . substr(md5((string) $i . microtime(true)), 0, 6);
            $result = probe_call_add($mcode, $name, '1', 'probe' . $i);
            probe_assert_success($result, 'fill field ' . $name);
            $current = probe_def_count($admin);
        }
        return $i;
}

function probe_create_db(mysqli $admin, $dbname, $engine)
    {
        $admin->query('DROP DATABASE IF EXISTS `' . $dbname . '`');
        if (! $admin->query('CREATE DATABASE `' . $dbname . '` DEFAULT CHARACTER SET utf8')) {
            probe_fail('create db failed: ' . $admin->error);
        }
        if (! $admin->select_db($dbname)) {
            probe_fail('select db failed: ' . $admin->error);
        }
        probe_exec_multi($admin, probe_schema_sql($engine));
}

function probe_drop_db(mysqli $admin, $dbname)
    {
        $admin->query('DROP DATABASE IF EXISTS `' . $dbname . '`');
}

function probe_pad_indexes_to(mysqli $admin, $dbname, $target)
    {
        $count = probe_index_count($admin, $dbname);
        $need = $target - $count;
        if ($need <= 0) {
            return 0;
        }
        $parts = array();
        for ($n = 1; $n <= $need; $n++) {
            $col = 'pad_col_' . $n;
            $idx = 'pad_idx_' . $n;
            $parts[] = "ADD COLUMN `{$col}` varchar(20) NULL";
            $parts[] = "ADD INDEX `{$idx}` (`{$col}`)";
        }
        // 分批避免单次 DDL 过大
        $chunkSize = 20;
        $chunks = array_chunk($parts, $chunkSize);
        foreach ($chunks as $chunk) {
            $sql = 'ALTER TABLE ay_content_ext ' . implode(', ', $chunk);
            if (! $admin->query($sql)) {
                probe_fail('pad batch failed at count~' . $count . ': ' . $admin->error);
            }
            $count = probe_index_count($admin, $dbname);
        }
        if ($count !== $target) {
            probe_fail('pad to ' . $target . ' failed, got ' . $count);
        }
        return $need;
}

function scenario_mysql_matrix()
    {
        probe_require_mysqli();
        $cfg = probe_mysql_cfg();
        $admin = probe_admin_mysqli($cfg);
        $engines = array('MyISAM', 'InnoDB');
        $drivers = array();
        $drivers[] = 'mysqli';
        if (extension_loaded('pdo_mysql')) {
            $drivers[] = 'pdo_mysql';
        } else {
            probe_skip('pdo_mysql unavailable');
        }

        $cases = 0;
        foreach ($engines as $engine) {
            foreach ($drivers as $driver) {
                $cases++;
                $dbname = $cfg['prefix'] . strtolower($engine) . '_' . str_replace('_', '', $driver) . '_' . getmypid();
                probe_print('CASE', $engine . '/' . $driver . '/' . $dbname);
                probe_create_db($admin, $dbname, $engine);
                probe_boot_app_mysql($cfg, $dbname, $driver);
                $admin->select_db($dbname);

                $idx0 = probe_index_count($admin, $dbname);
                $def0 = probe_def_count($admin);
                $col0 = probe_column_count($admin);
                if ($idx0 !== 5 || $def0 !== 3 || $col0 !== 3) {
                    probe_fail('unexpected initial state idx=' . $idx0 . ' def=' . $def0 . ' col=' . $col0);
                }

                // 用旧方式把索引垫到 64，模拟触顶现场
                probe_pad_indexes_to($admin, $dbname, 64);
                $idx64 = probe_index_count($admin, $dbname);
                if ($idx64 !== 64) {
                    probe_fail('pad to 64 failed, got ' . $idx64);
                }
                $defBefore = probe_def_count($admin);
                $colBefore = probe_column_count($admin);

                // 触顶后仍能新增：定义与物理列写入，索引不增加
                $result = probe_call_add('3', 'over_limit_ok', '1', '越界新增');
                probe_assert_success($result, 'add at 64 indexes');
                $admin->select_db($dbname);
                $idxAfter = probe_index_count($admin, $dbname);
                $defAfter = probe_def_count($admin);
                $colAfter = probe_column_count($admin);
                if ($idxAfter !== 64) {
                    probe_fail('index count changed after add: ' . $idxAfter);
                }
                if ($defAfter !== $defBefore + 1) {
                    probe_fail('definition not written');
                }
                if ($colAfter !== $colBefore + 1) {
                    probe_fail('physical column not written');
                }

                // 多模型交替再新增几个
                $r2 = probe_call_add('2', 'news_field_a', '1', '新闻字段A');
                $r3 = probe_call_add('4', 'case_field_a', '1', '案例字段A');
                probe_assert_success($r2, 'multi model news');
                probe_assert_success($r3, 'multi model case');
                if (probe_index_count($admin, $dbname) !== 64) {
                    probe_fail('indexes increased after multi-model adds');
                }

                // 内容读写：写入扩展列并读回
                $val = 'v_' . substr(md5(uniqid('', true)), 0, 8);
                if (! $admin->query("UPDATE ay_content_ext SET ext_over_limit_ok='" . $admin->real_escape_string($val) . "' WHERE contentid=1")) {
                    probe_fail('update ext value failed: ' . $admin->error);
                }
                $res = $admin->query('SELECT ext_over_limit_ok FROM ay_content_ext WHERE contentid=1');
                $row = $res->fetch_assoc();
                $res->free();
                if (! $row || $row['ext_over_limit_ok'] !== $val) {
                    probe_fail('read back failed');
                }

                // 既有关键索引仍在
                $names = probe_index_names($admin, $dbname);
                foreach (array('PRIMARY', 'ay_content_ext_contentid', 'ay_content_ext_color_index', 'ay_content_ext_price_index', 'ay_content_ext_type_index') as $must) {
                    if (! in_array($must, $names, true)) {
                        probe_fail('missing preserved index ' . $must);
                    }
                }

                probe_drop_db($admin, $dbname);
                probe_print('CASE_PASS', $engine . '/' . $driver);
            }
        }
        $admin->close();
        probe_ok('mysql_matrix cases=' . $cases);
}

function scenario_mysql_orphan_retry()
    {
        probe_require_mysqli();
        $cfg = probe_mysql_cfg();
        $admin = probe_admin_mysqli($cfg);
        $dbname = $cfg['prefix'] . 'orphan_' . getmypid();
        probe_create_db($admin, $dbname, 'MyISAM');
        probe_boot_app_mysql($cfg, $dbname, 'mysqli');
        $admin->select_db($dbname);

        // 模拟历史失败：物理列已在、定义缺失
        if (! $admin->query("ALTER TABLE ay_content_ext ADD ext_orphan_x varchar(200) NULL COMMENT 'orphan'")) {
            probe_fail('prepare orphan column failed: ' . $admin->error);
        }
        // 写入数据
        $admin->query("UPDATE ay_content_ext SET ext_orphan_x='keep-me' WHERE contentid=1");
        $def0 = probe_def_count($admin);
        $idx0 = probe_index_count($admin, $dbname);

        $r1 = probe_call_add('3', 'orphan_x', '1', 'orphan retry');
        probe_assert_success($r1, 'orphan retry register');
        $admin->select_db($dbname);
        if (probe_def_count($admin) !== $def0 + 1) {
            probe_fail('orphan retry did not insert definition');
        }
        if (probe_index_count($admin, $dbname) !== $idx0) {
            probe_fail('orphan retry unexpectedly changed indexes');
        }
        $res = $admin->query("SELECT ext_orphan_x FROM ay_content_ext WHERE contentid=1");
        $row = $res->fetch_assoc();
        $res->free();
        if ($row['ext_orphan_x'] !== 'keep-me') {
            probe_fail('orphan retry lost column data');
        }

        $dup = false;
        try {
            probe_call_add('3', 'orphan_x', '1', 'orphan retry');
        } catch (\RuntimeException $e) {
            $dup = (strpos($e->getMessage(), '字段已经存在') !== false)
                || (isset($GLOBALS['__extfield_probe_result']['msg']) && strpos($GLOBALS['__extfield_probe_result']['msg'], '字段已经存在') !== false);
        }
        if (! $dup && isset($GLOBALS['__extfield_probe_result']['type']) && $GLOBALS['__extfield_probe_result']['type'] === 'alert'
            && strpos($GLOBALS['__extfield_probe_result']['msg'], '字段已经存在') !== false) {
            $dup = true;
        }
        if (! $dup) {
            probe_fail('duplicate submit not rejected');
        }
        $res = $admin->query("SELECT ext_orphan_x FROM ay_content_ext WHERE contentid=1");
        $row = $res->fetch_assoc();
        $res->free();
        if ($row['ext_orphan_x'] !== 'keep-me') {
            probe_fail('duplicate submit lost data');
        }

        probe_drop_db($admin, $dbname);
        $admin->close();
        probe_ok('orphan retry ok');
}

function scenario_mysql_preserve_idx()
    {
        probe_require_mysqli();
        $cfg = probe_mysql_cfg();
        $admin = probe_admin_mysqli($cfg);
        $dbname = $cfg['prefix'] . 'preserve_' . getmypid();
        probe_create_db($admin, $dbname, 'MyISAM');
        probe_boot_app_mysql($cfg, $dbname, 'mysqli');
        $admin->select_db($dbname);

        $before = probe_index_names($admin, $dbname);
        probe_assert_success(probe_call_add('3', 'keep_idx_f1', '1', '保索引1'), 'add1');
        probe_assert_success(probe_call_add('2', 'keep_idx_f2', '1', '保索引2'), 'add2');
        $admin->select_db($dbname);
        $after = probe_index_names($admin, $dbname);
        foreach ($before as $name) {
            if (! in_array($name, $after, true)) {
                probe_fail('lost index ' . $name);
            }
        }
        if (count($after) !== count($before)) {
            probe_fail('index set size changed from ' . count($before) . ' to ' . count($after));
        }
        probe_drop_db($admin, $dbname);
        $admin->close();
        probe_ok('preserve indexes ok');
}

function probe_sqlite_schema(PDO $pdo)
    {
        $pdo->exec('CREATE TABLE ay_content (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL DEFAULT \'\'
        )');
        $pdo->exec('CREATE TABLE ay_content_ext (
            extid INTEGER PRIMARY KEY AUTOINCREMENT,
            contentid INTEGER NOT NULL,
            ext_price TEXT,
            ext_type TEXT,
            ext_color TEXT
        )');
        $pdo->exec('CREATE INDEX ay_content_ext_contentid ON ay_content_ext(contentid)');
        $pdo->exec('CREATE TABLE ay_extfield (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            mcode TEXT NOT NULL,
            name TEXT NOT NULL,
            type TEXT NOT NULL,
            value TEXT NOT NULL,
            description TEXT NOT NULL,
            sorting INTEGER NOT NULL
        )');
        $pdo->exec("INSERT INTO ay_extfield (id,mcode,name,type,value,description,sorting) VALUES
            (1,'3','ext_price','1','','产品价格',255),
            (2,'3','ext_type','4','基础版,专业版,旗舰版','类型',255),
            (3,'3','ext_color','4','红色,橙色,黄色,绿色,蓝色,紫色','颜色',255)");
        $pdo->exec("INSERT INTO ay_content (id, title) VALUES (1, 'seed')");
        $pdo->exec("INSERT INTO ay_content_ext (extid,contentid,ext_price,ext_type,ext_color) VALUES (1,1,'80','专业版','红色')");
}

function scenario_sqlite_100()
    {
        $driver = isset($GLOBALS['argv'][2]) ? (string) $GLOBALS['argv'][2] : 'sqlite';
        if ($driver !== 'sqlite' && $driver !== 'pdo_sqlite') {
            probe_fail('sqlite_100 driver must be sqlite or pdo_sqlite');
        }
        if ($driver === 'sqlite' && ! extension_loaded('sqlite3')) {
            probe_skip('sqlite3 unavailable');
        }
        if ($driver === 'pdo_sqlite' && ! extension_loaded('pdo_sqlite')) {
            probe_skip('pdo_sqlite unavailable');
        }

        $rel = '/data/extfield_probe_' . $driver . '_' . getmypid() . '.db';
        $path = ROOT_PATH . $rel;
        if (is_file($path)) {
            @unlink($path);
        }
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        probe_sqlite_schema($pdo);
        $pdo = null;

        db_reset_all_drivers();
        db_set_database_config(array(
            'type' => $driver,
            'dbname' => $rel
        ));
        $prop = db_config_reflection();
        $configs = $prop->getValue();
        if (! is_array($configs)) {
            $configs = array();
        }
        $configs['debug'] = true;
        $prop->setValue(null, $configs);

        // 默认已有 3 个，再加到 100
        for ($i = 4; $i <= 100; $i++) {
            $mcode = ($i % 2 === 0) ? '2' : '3';
            $name = 'sq_' . $i;
            $result = probe_call_add($mcode, $name, '1', 'sqlite' . $i);
            probe_assert_success($result, 'sqlite add ' . $name);
        }

        // 提交 sqlite 事务以便另一连接可见
        if ($driver === 'sqlite') {
            db_commit_sqlite();
        } elseif (class_exists('core\\database\\Pdo', false)) {
            $instance = db_reflect_static('core\\database\\Pdo', 'pdo')->getValue();
            if ($instance) {
                $instance->commitTransaction();
            }
        }

        $check = new PDO('sqlite:' . $path);
        $check->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $defCount = (int) $check->query('SELECT COUNT(*) FROM ay_extfield')->fetchColumn();
        if ($defCount !== 100) {
            probe_fail('sqlite def count=' . $defCount);
        }
        $cols = $check->query('PRAGMA table_info(ay_content_ext)')->fetchAll(PDO::FETCH_ASSOC);
        $extCols = 0;
        foreach ($cols as $c) {
            if (strpos($c['name'], 'ext_') === 0) {
                $extCols++;
            }
        }
        if ($extCols !== 100) {
            probe_fail('sqlite physical ext cols=' . $extCols);
        }

        $val = 'sq100_' . substr(md5(uniqid('', true)), 0, 6);
        $check->exec("UPDATE ay_content_ext SET ext_sq_100='" . str_replace("'", "''", $val) . "' WHERE contentid=1");
        $got = $check->query('SELECT ext_sq_100 FROM ay_content_ext WHERE contentid=1')->fetchColumn();
        if ($got !== $val) {
            probe_fail('sqlite read/write failed');
        }
        $check = null;
        @unlink($path);
        probe_ok('sqlite_100 driver=' . $driver);
}

function scenario_syntax_check()
    {
        $file = APP_PATH . '/admin/controller/content/ExtFieldController.php';
        $src = file_get_contents($file);
        if ($src === false) {
            probe_fail('cannot read ExtFieldController');
        }
        if (stripos($src, 'create index') !== false) {
            probe_fail('create index still present in ExtFieldController');
        }
        $phpBin = (defined('PHP_BINARY') && PHP_BINARY !== '') ? PHP_BINARY : 'php';
        $cmd = escapeshellarg($phpBin) . ' -l ' . escapeshellarg($file) . ' 2>&1';
        $out = array();
        $code = 1;
        exec($cmd, $out, $code);
        if ($code !== 0) {
            $detail = implode("\n", $out);
            if ($detail === '') {
                $detail = 'php -l exit=' . $code;
            }
            probe_fail('php -l failed: ' . $detail);
        }
        probe_ok('syntax ok, no create index');
}

switch ($scenario) {
        case 'mysql_matrix':
            scenario_mysql_matrix();
            break;
        case 'mysql_orphan_retry':
            scenario_mysql_orphan_retry();
            break;
        case 'mysql_preserve_idx':
            scenario_mysql_preserve_idx();
            break;
        case 'sqlite_100':
            scenario_sqlite_100();
            break;
        case 'syntax_check':
            scenario_syntax_check();
            break;
        default:
            fwrite(STDERR, "unknown scenario: {$scenario}\n");
            exit(2);
}
