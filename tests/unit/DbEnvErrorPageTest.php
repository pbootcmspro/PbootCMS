<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers db_env_* 当前请求元数据、连接归属与错误页降级
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

defined('SITE_DIR') || define('SITE_DIR', '');
defined('CORE_DIR') || define('CORE_DIR', '/core');
defined('APP_VERSION') || define('APP_VERSION', '3.2.28');
defined('RELEASE_TIME') || define('RELEASE_TIME', '20260928');
defined('CONF_PATH') || define('CONF_PATH', ROOT_PATH . 'config');
defined('RUN_PATH') || define('RUN_PATH', ROOT_PATH . 'runtime');

require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/Basic.php';
require_once CORE_PATH . '/database/Builder.php';
require_once CORE_PATH . '/database/Sqlite.php';
require_once CORE_PATH . '/database/Mysqli.php';
require_once CORE_PATH . '/database/Pdo.php';
require_once TEST_ROOT . 'support/ConfigStub.php';
require_once TEST_ROOT . 'support/DatabaseTestSupport.php';

spl_autoload_register('core\\basic\\Basic::autoLoad', true, true);

use core\database\Sqlite;
use core\database\Mysqli;
use core\database\Pdo;

return TestAssert::runSuite(function () {
    echo "=== label mapping for four drivers ===\n";

    $cases = array(
        'sqlite' => 'SQLite',
        'pdo_sqlite' => 'SQLite',
        'mysqli' => 'MySQL',
        'pdo_mysql' => 'MySQL',
        'pdo_pgsql' => 'PostgreSQL'
    );
    foreach ($cases as $driver => $label) {
        db_env_reset();
        ConfigStub::set(array('database' => array('type' => $driver)));
        $fmt = db_env_format();
        TestAssert::same($label, $fmt['type'], "$driver type label");
        TestAssert::same($driver, $fmt['driver'], "$driver driver name");
        TestAssert::same('未获取', $fmt['version'], "$driver version missing before connect");
    }

    echo "=== sqlite collect on successful query assign ===\n";

    db_env_reset();
    db_reset_all_drivers();
    if (! extension_loaded('sqlite3')) {
        echo "SKIP: sqlite3 unavailable\n";
    } else {
        $path = db_boot_sqlite('db_env_sqlite');
        if ($path === null) {
            echo "SKIP: sqlite bootstrap failed\n";
        } else {
            try {
                $db = Sqlite::getInstance();
                $db->query('SELECT 1', 'slave');
                $state = db_env_state();
                TestAssert::true($state['master'] !== '', 'sqlite master version collected');
                TestAssert::same($state['master'], $state['slave'], 'sqlite master/slave same connection version');
                $fmt = db_env_format();
                TestAssert::same('SQLite', $fmt['type'], 'sqlite page type');
                TestAssert::same('sqlite', $fmt['driver'], 'sqlite page driver');
                TestAssert::same($state['master'], html_entity_decode($fmt['version'], ENT_QUOTES, 'UTF-8'), 'sqlite page version');
                $before = db_env_state();
                $html = parse_info_tpl(CORE_PATH . '/template/error.html', '数据库执行错误，请稍后重试或联系管理员！');
                $after = db_env_state();
                TestAssert::contains($html, '数据库：SQLite', 'html has SQLite');
                TestAssert::contains($html, '驱动：sqlite', 'html has sqlite driver');
                TestAssert::contains($html, '版本：' . $state['master'], 'html has sqlite version');
                TestAssert::same($before['noted'], $after['noted'], 'render noted unchanged');
                TestAssert::same($before['reads'], $after['reads'], 'render reads unchanged');
            } finally {
                db_commit_sqlite();
                db_reset_sqlite_singleton();
                @unlink($path);
            }
        }
    }

    echo "=== pdo_sqlite collect without borrowing SQLite3::version ===\n";

    db_env_reset();
    db_reset_all_drivers();
    if (! extension_loaded('pdo_sqlite')) {
        echo "SKIP: pdo_sqlite unavailable\n";
    } else {
        $path = db_boot_pdo_sqlite('db_env_pdo');
        if ($path === null) {
            echo "SKIP: pdo_sqlite bootstrap failed\n";
        } else {
            try {
                $db = Pdo::getInstance();
                // PDO 读路径只建 slave；通用错误页只读主连接版本，故显式走 master
                $db->query('SELECT 1', 'master');
                $state = db_env_state();
                TestAssert::true($state['master'] !== '', 'pdo_sqlite master version collected');
                $fmt = db_env_format();
                TestAssert::same('SQLite', $fmt['type'], 'pdo_sqlite page type');
                TestAssert::same('pdo_sqlite', $fmt['driver'], 'pdo_sqlite page driver');
                TestAssert::same($state['master'], html_entity_decode($fmt['version'], ENT_QUOTES, 'UTF-8'), 'pdo_sqlite page version');

                db_env_reset();
                ConfigStub::set(array('database' => array('type' => 'pdo_sqlite')));
                $readsBefore = db_env_state()['reads'];
                db_env_note('master', new \stdClass());
                $state2 = db_env_state();
                TestAssert::same('', $state2['master'], 'non-PDO object does not borrow SQLite3 version');
                TestAssert::true($state2['reads'] > $readsBefore, 'unsupported conn still counted as read');
                TestAssert::same('未获取', db_env_format()['version'], 'unsupported conn → 未获取');
            } finally {
                if (isset($db) && method_exists($db, 'commitTransaction')) {
                    $db->commitTransaction();
                }
                db_reset_pdo_singleton();
                @unlink($path);
            }
        }
    }

    echo "=== mysqli / pdo_mysql collect when MySQL reachable ===\n";

    $mysqliLive = db_boot_mysqli_or_null();
    if ($mysqliLive === null) {
        echo "SKIP: MySQL unreachable for mysqli/pdo_mysql live collect\n";
    } else {
        $mysqliLive->close();

        db_env_reset();
        db_reset_all_drivers();
        $dbFile = ROOT_PATH . 'config/database.php';
        $cfg = require $dbFile;
        $c = $cfg['database'];
        $c['type'] = 'mysqli';
        db_set_database_config($c);
        try {
            $db = Mysqli::getInstance();
            $db->query('SELECT 1', 'slave');
            $state = db_env_state();
            TestAssert::true($state['master'] !== '', 'mysqli master version collected');
            $fmt = db_env_format();
            TestAssert::same('MySQL', $fmt['type'], 'mysqli page type');
            TestAssert::same('mysqli', $fmt['driver'], 'mysqli page driver');
            TestAssert::contains($fmt['version'], '.', 'mysqli version looks like a version');
        } finally {
            db_reset_mysqli_singleton();
        }

        if (! extension_loaded('pdo_mysql')) {
            echo "SKIP: pdo_mysql extension unavailable\n";
        } else {
            db_env_reset();
            db_reset_all_drivers();
            $c['type'] = 'pdo_mysql';
            db_set_database_config($c);
            try {
                $db = Pdo::getInstance();
                $db->query('SELECT 1', 'master');
                $state = db_env_state();
                TestAssert::true($state['master'] !== '', 'pdo_mysql master version collected');
                $fmt = db_env_format();
                TestAssert::same('MySQL', $fmt['type'], 'pdo_mysql page type');
                TestAssert::same('pdo_mysql', $fmt['driver'], 'pdo_mysql page driver');
            } finally {
                db_reset_pdo_singleton();
            }
        }
    }

    echo "=== master/slave pin isolation and block ===\n";

    db_env_reset();
    ConfigStub::set(array('database' => array('type' => 'mysqli')));
    $store = &db_env_store();
    $store['master'] = 'master-9.0';
    $store['slave'] = 'slave-8.0';
    db_env_pin('slave');
    TestAssert::same('slave-8.0', db_env_format()['version'], 'SQL error uses failing slave version');
    db_env_pin('master');
    TestAssert::same('master-9.0', db_env_format()['version'], 'SQL error uses failing master version');
    db_env_reset();
    ConfigStub::set(array('database' => array('type' => 'mysqli')));
    $store = &db_env_store();
    $store['master'] = 'master-only';
    db_env_pin('slave');
    TestAssert::same('未获取', db_env_format()['version'], 'empty pinned slave does not borrow master');
    db_env_block();
    TestAssert::same('未获取', db_env_format()['version'], 'block hides stored versions');
    TestAssert::same('master-only', db_env_state()['master'], 'block keeps stored master intact');

    echo "=== temporary conn() success does not note site version ===\n";

    db_env_reset();
    db_reset_all_drivers();
    ConfigStub::set(array('database' => array('type' => 'mysqli', 'host' => '127.0.0.1')));
    // 仅调用公共 conn 成功路径需要真实 MySQL；无服务时用反射确认 note 未绑定到 conn 返回值分支
    $mysqliSrc = file_get_contents(CORE_PATH . '/database/Mysqli.php');
    $connPos = strpos($mysqliSrc, 'function conn($cfg)');
    $beginPos = strpos($mysqliSrc, 'function beginTransaction()');
    TestAssert::true($connPos !== false && $beginPos !== false, 'mysqli conn/begin bounds');
    $connBody = substr($mysqliSrc, $connPos, $beginPos - $connPos);
    TestAssert::notContains($connBody, 'db_env_note(', 'Mysqli::conn itself does not note');
    TestAssert::contains($connBody, 'db_env_block()', 'Mysqli::conn blocks on failure');

    if ($mysqliLive = db_boot_mysqli_or_null()) {
        $host = $mysqliLive->host_info;
        $mysqliLive->close();
        db_env_reset();
        db_reset_mysqli_singleton();
        $dbFile = ROOT_PATH . 'config/database.php';
        $cfg = require $dbFile;
        $c = $cfg['database'];
        $c['type'] = 'mysqli';
        db_set_database_config($c);
        $tmp = Mysqli::getInstance()->conn(array(
            'host' => $c['host'],
            'user' => $c['user'],
            'passwd' => $c['passwd'],
            'port' => $c['port'],
            'dbname' => $c['dbname']
        ));
        TestAssert::true($tmp instanceof \mysqli, 'temp conn succeeded');
        TestAssert::same('', db_env_state()['master'], 'temp conn did not note master');
        $tmp->close();
        $db = Mysqli::getInstance();
        $db->query('SELECT 1', 'slave');
        TestAssert::true(db_env_state()['master'] !== '', 'later site master still notes');
        db_reset_mysqli_singleton();
        unset($host);
    } else {
        echo "SKIP: MySQL unreachable for temp conn live check\n";
    }

    echo "=== soft fail does not pin ===\n";

    db_env_reset();
    db_reset_all_drivers();
    if (! extension_loaded('sqlite3')) {
        echo "SKIP: sqlite3 unavailable\n";
    } else {
        $path = db_boot_sqlite('db_env_soft');
        if ($path === null) {
            echo "SKIP: sqlite soft bootstrap failed\n";
        } else {
            try {
                Sqlite::setFailSoft(true);
                $db = Sqlite::getInstance();
                $db->amd('UPDATE ay_notable_xyz SET x=1');
                TestAssert::true(Sqlite::hadFailSoftError(), 'soft fail marked');
                TestAssert::same('', db_env_state()['mode'], 'soft fail does not pin');
                Sqlite::setFailSoft(false);
            } finally {
                db_commit_sqlite();
                db_reset_sqlite_singleton();
                @unlink($path);
            }
        }
    }

    echo "=== sanitize and escape edge cases ===\n";

    TestAssert::same('', db_env_sanitize_version("\0\n\t"), 'control-only version empty');
    TestAssert::same('ab', db_env_sanitize_version("a\0b"), 'null bytes stripped');
    db_env_reset();
    ConfigStub::set(array('database' => array('type' => 'sqlite')));
    $store = &db_env_store();
    $store['master'] = '3.45.1"\'<>';
    $fmt = db_env_format();
    TestAssert::contains($fmt['version'], '&quot;', 'quote escaped');
    TestAssert::contains($fmt['version'], '&lt;', 'lt escaped');
    TestAssert::notContains($fmt['version'], '<', 'raw lt absent');
});
