<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/database/Sqlite.php
 * @covers core/database/Mysqli.php
 * @covers core/database/Pdo.php
 *
 * 驱动 failSoft / 连接失败 / CRUD / commitTransaction 回归（本 PR 保留 core/database 改动的覆盖要求）
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

defined('CONF_PATH') ?: define('CONF_PATH', ROOT_PATH . 'config');
defined('RUN_PATH') ?: define('RUN_PATH', ROOT_PATH . 'runtime');

require_once TEST_ROOT . 'support/DatabaseTestSupport.php';
require_once CORE_PATH . '/basic/Basic.php';
require_once CORE_PATH . '/database/Sqlite.php';
require_once CORE_PATH . '/database/Mysqli.php';
require_once CORE_PATH . '/database/Pdo.php';

spl_autoload_register('core\\basic\\Basic::autoLoad', true, true);

use core\database\Sqlite;
use core\database\Mysqli;
use core\database\Pdo;

/**
 * @param array{stdout:string,stderr:string,exit_code:int} $r
 */
function db_mode_out(array $r): string
{
    return $r['stdout'] . "\n" . $r['stderr'];
}

function db_mode_has(array $r, string $needle): bool
{
    return strpos(db_mode_out($r), $needle) !== false;
}

/**
 * @return array{driver:object,label:string,cleanup:callable}|null
 */
function db_mode_open_driver(string $kind)
{
    db_reset_all_drivers();
    if ($kind === 'sqlite') {
        $path = db_boot_sqlite('crud_sqlite');
        if ($path === null) {
            return null;
        }
        $db = Sqlite::getInstance();
        return array(
            'driver' => $db,
            'label' => 'sqlite',
            'cleanup' => function () use ($path) {
                db_commit_sqlite();
                db_reset_sqlite_singleton();
                @unlink($path);
            }
        );
    }
    if ($kind === 'pdo_sqlite') {
        $path = db_boot_pdo_sqlite('crud_pdo');
        if ($path === null) {
            return null;
        }
        $db = Pdo::getInstance();
        // 触发连接
        $db->query('SELECT 1', 'slave');
        return array(
            'driver' => $db,
            'label' => 'pdo_sqlite',
            'cleanup' => function () use ($path, $db) {
                if (method_exists($db, 'commitTransaction')) {
                    $db->commitTransaction();
                }
                db_reset_pdo_singleton();
                @unlink($path);
            }
        );
    }
    if ($kind === 'mysqli') {
        $mysqli = db_boot_mysqli_or_null();
        if ($mysqli === null) {
            return null;
        }
        $dbFile = ROOT_PATH . 'config/database.php';
        $cfg = require $dbFile;
        $c = $cfg['database'];
        $c['type'] = 'mysqli';
        db_set_database_config($c);
        db_reset_mysqli_singleton();
        $table = 'ay_db_error_mode_t_' . getmypid();
        $mysqli->query("DROP TABLE IF EXISTS `$table`");
        $mysqli->query("CREATE TABLE `$table` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(64) NOT NULL DEFAULT '',
            visits INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
        $mysqli->query("INSERT INTO `$table` (id, title, visits) VALUES (1, 'seed', 0)");
        $mysqli->close();

        $db = Mysqli::getInstance();
        return array(
            'driver' => $db,
            'label' => 'mysqli',
            'table' => $table,
            'cleanup' => function () use ($table, $c) {
                db_reset_mysqli_singleton();
                try {
                    $m = @new \mysqli($c['host'], $c['user'], $c['passwd'], $c['dbname'], (int) $c['port']);
                    if (\mysqli_connect_errno() === 0) {
                        \mysqli_query($m, "DROP TABLE IF EXISTS `$table`");
                        \mysqli_close($m);
                    }
                } catch (Throwable $e) {
                    fwrite(STDERR, "WARN: mysqli cleanup failed\n");
                }
            }
        );
    }
    return null;
}

/**
 * @param object $db
 */
function db_mode_run_crud($db, string $table, string $label)
{
    echo "=== C1 CRUD ($label) ===\n";

    TestAssert::true($db->isExist("SELECT id FROM $table WHERE id=1"), "$label isExist seed");
    $one = $db->one("SELECT id, title, visits FROM $table WHERE id=1");
    TestAssert::true(is_object($one) || is_array($one), "$label one() returns row");
    $all = $db->all("SELECT id FROM $table");
    TestAssert::true(is_array($all) && count($all) >= 1, "$label all() returns rows");

    $affected = $db->amd("INSERT INTO $table (title, visits) VALUES ('n', 0)");
    TestAssert::true($affected > 0, "$label insert amd");
    $newId = (int) $db->insertId();
    TestAssert::true($newId > 0, "$label insertId");

    TestAssert::true($db->amd("UPDATE $table SET visits = visits + 3 WHERE id=$newId") > 0, "$label update +=N");
    // pdo_sqlite 写库挂在连接事务上，读库可能是另一连接，提交后才能看到
    $db->commitTransaction();
    $row = $db->one("SELECT visits FROM $table WHERE id=$newId");
    TestAssert::true(is_object($row) || is_array($row), "$label one() after update");
    $visits = is_object($row) ? (int) $row->visits : (int) (isset($row['visits']) ? $row['visits'] : 0);
    TestAssert::same(3, $visits, "$label visits after +=3");

    TestAssert::true($db->rows($table) >= 2, "$label rows()");
    $fieldCount = $db->fields($table);
    TestAssert::true($fieldCount >= 3, "$label fields()");
    $names = $db->tableFields($table);
    TestAssert::true(is_array($names) && in_array('visits', $names, true), "$label tableFields has visits");

    TestAssert::true($db->multi("UPDATE $table SET title='m' WHERE id=$newId"), "$label multi()");
    TestAssert::true($db->amd("DELETE FROM $table WHERE id=$newId") > 0, "$label delete");

    $db->beginTransaction();
    $db->amd("UPDATE $table SET visits = visits + 1 WHERE id=1");
    TestAssert::true($db->commitTransaction(), "$label begin+commit");
}

return TestAssert::runSuite(function () {
    echo "=== A1 non-failSoft bad SQL exits via global error() ===\n";

    if (! extension_loaded('sqlite3')) {
        echo "SKIP: sqlite3 unavailable\n";
    } else {
        $r = db_run_probe('sql_error_hard');
        // error() 使用裸 exit()，退出码为 0；非调试默认对外仅通用文案
        TestAssert::true(db_mode_has($r, '数据库执行错误，请稍后重试或联系管理员！'), 'A1 generic error page');
        TestAssert::false(db_mode_has($r, 'ay_notable_xyz'), 'A1 must not leak table name');
        TestAssert::false(db_mode_has($r, 'UNEXPECTED_SURVIVE=1'), 'A1 must not silently return');
        TestAssert::false(db_mode_has($r, 'SURVIVED=1'), 'A1 must not print soft survive marker');
    }

    echo "=== A2 failSoft bad SQL returns without exit ===\n";

    if (! extension_loaded('sqlite3')) {
        echo "SKIP: sqlite3 unavailable\n";
    } else {
        $r = db_run_probe('sql_error_soft');
        TestAssert::same(0, $r['exit_code'], 'A2 process survives');
        TestAssert::true(db_mode_has($r, 'AMD=0'), 'A2 amd returns 0');
        TestAssert::true(db_mode_has($r, 'HAD_ERROR=1'), 'A2 hadFailSoftError');
        TestAssert::true(db_mode_has($r, 'SURVIVED=1'), 'A2 survived marker');
    }

    echo "=== A3 setFailSoft(false) restores hard error ===\n";

    if (! extension_loaded('sqlite3')) {
        echo "SKIP: sqlite3 unavailable\n";
    } else {
        $r = db_run_probe('sql_error_after_soft');
        TestAssert::true(db_mode_has($r, '数据库执行错误，请稍后重试或联系管理员！'), 'A3 generic hard error page again');
        TestAssert::false(db_mode_has($r, 'UNEXPECTED_SURVIVE=1'), 'A3 must not survive');
    }

    echo "=== A4 nested failSoft keeps error flag until outermost exit ===\n";

    if (! extension_loaded('sqlite3')) {
        echo "SKIP: sqlite3 unavailable\n";
    } else {
        $path = db_boot_sqlite('nested_failsoft');
        if ($path === null) {
            echo "SKIP: sqlite bootstrap failed\n";
        } else {
            db_reset_sqlite_singleton();
            Sqlite::setFailSoft(true);
            Sqlite::setFailSoft(true);
            $db = Sqlite::getInstance();
            $db->amd('UPDATE ay_notable SET x=1');
            TestAssert::true(Sqlite::hadFailSoftError(), 'A4 error set inside nested scope');
            Sqlite::setFailSoft(false);
            TestAssert::true(Sqlite::hadFailSoftError(), 'A4 error retained after inner exit');
            TestAssert::same(1, db_failsoft_depth('core\\database\\Sqlite'), 'A4 outer depth still open');
            Sqlite::setFailSoft(false);
            TestAssert::false(Sqlite::hadFailSoftError(), 'A4 cleared on outermost exit');
            TestAssert::same(0, db_failsoft_depth('core\\database\\Sqlite'), 'A4 depth zero');
            @unlink($path);
            db_reset_sqlite_singleton();
        }
    }

    echo "=== A5 exit residual contract ===\n";

    // 显式契约：error()/exit() 经 zend_bailout 离开，try/finally 不会执行，
    // withFailSoftDb 无法在全局 error() 路径上复位；shutdown 仍可见开启态。
    $r = db_run_probe('failsoft_residual_exit');
    TestAssert::true(db_mode_has($r, 'SHUTDOWN=1'), 'A5a shutdown ran');
    TestAssert::true(db_mode_has($r, 'FAILSOFT_DEPTH=1'), 'A5a exit skips finally — depth residual (explicit contract)');
    TestAssert::true(db_mode_has($r, 'FAILSOFT_ON=1'), 'A5a exit skips finally — flag residual (explicit contract)');

    // 无 finally：同样残留
    $r2 = db_run_probe('failsoft_open_then_exit');
    TestAssert::true(db_mode_has($r2, 'SHUTDOWN=1'), 'A5b shutdown ran');
    TestAssert::true(db_mode_has($r2, 'FAILSOFT_DEPTH=1'), 'A5b no-finally residual depth (explicit contract)');
    TestAssert::true(db_mode_has($r2, 'FAILSOFT_ON=1'), 'A5b no-finally residual flag (explicit contract)');

    echo "=== B1 Mysqli::conn wrong credentials ===\n";

    if (! extension_loaded('mysqli')) {
        echo "SKIP: mysqli unavailable\n";
    } else {
        $r = db_run_probe('conn_mysqli');
        if (db_mode_has($r, 'SKIP=')) {
            echo "SKIP: " . trim($r['stdout']) . "\n";
        } else {
            TestAssert::false(db_mode_has($r, 'UNCAUGHT='), 'B1 no uncaught mysqli_sql_exception');
            TestAssert::true(
                db_mode_has($r, '连接数据库服务器失败') || $r['exit_code'] !== 0,
                'B1 friendly connect failure page or non-zero exit'
            );
            TestAssert::true(db_mode_has($r, '连接数据库服务器失败'), 'B1 message 连接数据库服务器失败');
        }
    }

    echo "=== B2 Pdo::conn pdo_mysql wrong credentials ===\n";

    if (! extension_loaded('pdo_mysql')) {
        echo "SKIP: pdo_mysql unavailable\n";
    } else {
        $r = db_run_probe('conn_pdo_mysql');
        if (db_mode_has($r, 'SKIP=')) {
            echo "SKIP: " . trim($r['stdout']) . "\n";
        } else {
            TestAssert::false(db_mode_has($r, 'UNCAUGHT=PDOException'), 'B2 PDOException caught inside driver');
            TestAssert::true(db_mode_has($r, 'PDO方式连接'), 'B2 hits PDO connect error() branch');
        }
    }

    echo "=== B3 Pdo::conn pdo_sqlite unwritable path ===\n";

    if (! extension_loaded('pdo_sqlite')) {
        echo "SKIP: pdo_sqlite unavailable\n";
    } else {
        $r = db_run_probe('conn_pdo_sqlite');
        if (db_mode_has($r, 'SKIP=')) {
            echo "SKIP: " . trim($r['stdout']) . "\n";
        } else {
            TestAssert::false(db_mode_has($r, 'UNCAUGHT=PDOException'), 'B3 PDOException not leaked');
            TestAssert::true(
                db_mode_has($r, 'PDO方式连接Sqlite') || db_mode_has($r, 'PDO方式连接'),
                'B3 hits sqlite PDO error() branch'
            );
        }
    }

    echo "=== B4 Mysqli conn sets MYSQLI_REPORT_OFF ===\n";

    if (! extension_loaded('mysqli') || ! function_exists('mysqli_report')) {
        echo "SKIP: mysqli_report unavailable\n";
    } else {
        $r = db_run_probe('mysqli_report_mode');
        if (db_mode_has($r, 'SKIP=')) {
            echo "SKIP: " . trim($r['stdout']) . "\n";
        } else {
            TestAssert::true(db_mode_has($r, 'SHUTDOWN=1'), 'B4 shutdown observed report_mode');
            // 从输出解析数值
            if (preg_match('/REPORT_MODE=(\d+)/', db_mode_out($r), $m1)
                && preg_match('/MYSQLI_REPORT_OFF=(\d+)/', db_mode_out($r), $m2)
            ) {
                TestAssert::same((int) $m2[1], (int) $m1[1], 'B4 report_mode === MYSQLI_REPORT_OFF');
            } else {
                TestAssert::true(false, 'B4 could not parse report_mode lines');
            }
        }
    }

    echo "=== B5 PDO ATTR_ERRMODE === ERRMODE_SILENT ===\n";

    if (! extension_loaded('pdo_sqlite')) {
        echo "SKIP: pdo_sqlite unavailable\n";
    } else {
        $r = db_run_probe('pdo_errmode');
        if (db_mode_has($r, 'SKIP=')) {
            echo "SKIP: " . trim($r['stdout']) . "\n";
        } else {
            TestAssert::same(0, $r['exit_code'], 'B5 survived');
            if (preg_match('/ERRMODE=(\d+)/', db_mode_out($r), $m1)
                && preg_match('/ERRMODE_SILENT=(\d+)/', db_mode_out($r), $m2)
            ) {
                TestAssert::same((int) $m2[1], (int) $m1[1], 'B5 ERRMODE_SILENT');
            } else {
                TestAssert::true(false, 'B5 could not parse errmode lines');
            }
        }
    }

    // C1
    foreach (array('sqlite', 'pdo_sqlite', 'mysqli') as $kind) {
        $opened = db_mode_open_driver($kind);
        if ($opened === null) {
            echo "SKIP: C1 $kind not available\n";
            continue;
        }
        $table = isset($opened['table']) ? $opened['table'] : 'ay_content';
        try {
            db_mode_run_crud($opened['driver'], $table, $opened['label']);
        } finally {
            $opened['cleanup']();
        }
    }

    echo "=== C2 query false is not reachable without failSoft (hard path exits) ===\n";

    if (! extension_loaded('sqlite3')) {
        echo "SKIP: sqlite3 unavailable\n";
    } else {
        // 非 failSoft：error()+exit，false 传不到 amd/one/all/isExist 调用方
        $r = db_run_probe('sql_error_hard');
        TestAssert::true(db_mode_has($r, '数据库执行错误，请稍后重试或联系管理员！'), 'C2 hard path shows generic error page');
        TestAssert::false(db_mode_has($r, 'UNEXPECTED_SURVIVE=1'), 'C2 hard path exits before false propagates');

        $path = db_boot_sqlite('c2_soft');
        db_reset_sqlite_singleton();
        Sqlite::setFailSoft(true);
        $db = Sqlite::getInstance();
        $q = $db->query('UPDATE ay_notable SET x=1', 'master');
        TestAssert::same(false, $q, 'C2 failSoft query() returns false');
        TestAssert::same(0, $db->amd('UPDATE ay_notable SET x=1'), 'C2 failSoft amd() returns 0');
        TestAssert::true(Sqlite::hadFailSoftError(), 'C2 failSoft flag set');
        Sqlite::setFailSoft(false);
        @unlink($path);
        db_reset_sqlite_singleton();
    }

    echo "=== D commitTransaction return contract ===\n";

    $driverKinds = array();
    if (extension_loaded('sqlite3')) {
        $driverKinds[] = 'sqlite';
    }
    if (extension_loaded('pdo_sqlite')) {
        $driverKinds[] = 'pdo_sqlite';
    }
    if (db_boot_mysqli_or_null() !== null) {
        $driverKinds[] = 'mysqli';
    }

    if (! $driverKinds) {
        echo "SKIP: no drivers for commit tests\n";
    }

    foreach ($driverKinds as $label) {
        db_reset_all_drivers();
        $path = null;
        $table = 'ay_content';
        if ($label === 'sqlite') {
            $path = db_boot_sqlite('commit_' . $label);
            $class = 'core\\database\\Sqlite';
            $db = Sqlite::getInstance();
        } elseif ($label === 'pdo_sqlite') {
            $path = db_boot_pdo_sqlite('commit_' . $label);
            $class = 'core\\database\\Pdo';
            $db = Pdo::getInstance();
        } else {
            $mysqli = db_boot_mysqli_or_null();
            if ($mysqli === null) {
                echo "SKIP: mysqli unreachable\n";
                continue;
            }
            $dbFile = ROOT_PATH . 'config/database.php';
            $cfg = require $dbFile;
            $c = $cfg['database'];
            $c['type'] = 'mysqli';
            db_set_database_config($c);
            $table = 'ay_db_commit_t_' . getmypid();
            $mysqli->query("DROP TABLE IF EXISTS `$table`");
            $mysqli->query("CREATE TABLE `$table` (id INT PRIMARY KEY, visits INT NOT NULL DEFAULT 0) ENGINE=InnoDB");
            $mysqli->query("INSERT INTO `$table` (id, visits) VALUES (1, 0)");
            $mysqli->close();
            db_reset_mysqli_singleton();
            $class = 'core\\database\\Mysqli';
            $db = Mysqli::getInstance();
        }
        if ($label !== 'mysqli' && $path === null) {
            echo "SKIP: boot $label failed\n";
            continue;
        }

        TestAssert::true($db->commitTransaction(), "D1 $label no active txn => true");

        $db->beginTransaction();
        $db->amd("UPDATE $table SET visits = visits + 1 WHERE id=1");
        TestAssert::true($db->commitTransaction(), "D2 $label commit success => true");

        $ref = new ReflectionObject($db);
        $masterProp = $ref->getProperty('master');
        $beginProp = $ref->getProperty('begin');
        if (PHP_VERSION_ID < 80100) {
            $masterProp->setAccessible(true);
            $beginProp->setAccessible(true);
        }

        if ($label === 'sqlite') {
            $failMaster = new class {
                public function exec($sql)
                {
                    return false;
                }
            };
        } else {
            // Mysqli / PDO：commit() 返回 false；Mysqli 另需 autocommit()
            $failMaster = new class {
                public function commit()
                {
                    return false;
                }

                public function autocommit($mode)
                {
                    return true;
                }
            };
        }

        call_user_func(array($class, 'setFailSoft'), true);
        $masterProp->setValue($db, $failMaster);
        $beginProp->setValue($db, true);
        $ok = $db->commitTransaction();
        TestAssert::false($ok, "D3 $label failSoft commit fail => false");
        TestAssert::true(call_user_func(array($class, 'hadFailSoftError')), "D3 $label failSoft error flag");
        call_user_func(array($class, 'setFailSoft'), false);

        $masterProp->setValue($db, $failMaster);
        $beginProp->setValue($db, true);
        TestAssert::true($db->commitTransaction(), "D5 $label non-failSoft commit fail still true");

        $beginProp->setValue($db, false);
        $masterProp->setValue($db, null);
        if ($path) {
            @unlink($path);
        }
        if ($label === 'mysqli') {
            try {
                $m = db_boot_mysqli_or_null();
                if ($m) {
                    $m->query("DROP TABLE IF EXISTS `$table`");
                    $m->close();
                }
            } catch (Throwable $e) {
                fwrite(STDERR, "WARN: mysqli cleanup failed\n");
            }
        }
        db_reset_all_drivers();
    }

    // D4 Builder 文档
    $builderSrc = file_get_contents(CORE_PATH . '/database/Builder.php');
    TestAssert::contains($builderSrc, '@return bool', 'D4 Builder documents commitTransaction bool');

    echo "=== G SQL error public message sanitization ===\n";

    $safeMsg = '数据库执行错误，请稍后重试或联系管理员！';
    if (! extension_loaded('sqlite3')) {
        echo "SKIP: sqlite3 unavailable for G1-G5\n";
    } else {
        // G1 debug=false
        $r = db_run_probe('sql_error_public', array('0'));
        TestAssert::true(db_mode_has($r, $safeMsg), 'G1 generic message only');
        TestAssert::false(db_mode_has($r, 'ay_secret_table'), 'G1 no table name');
        TestAssert::false(db_mode_has($r, 'supersecret_col'), 'G1 no column name');
        TestAssert::false(db_mode_has($r, 'bogusfn'), 'G1 no function name');
        TestAssert::false(db_mode_has($r, 'SQLSTATE'), 'G1 no SQLSTATE');
        TestAssert::false(db_mode_has($r, '执行SQL发生错误！错误：'), 'G1 no detailed prefix in body');
        TestAssert::true(db_mode_has($r, 'LOG_HAS_PREFIX=1'), 'G3 debug=0 log has driver prefix');
        TestAssert::true(db_mode_has($r, 'LOG_HAS_STMT=1'), 'G3 debug=0 log has 语句：');
        TestAssert::true(db_mode_has($r, 'LOG_HAS_SECRET_TABLE=1'), 'G3 debug=0 log keeps table');
        TestAssert::true(db_mode_has($r, 'LOG_HAS_BOGUS=1'), 'G3 debug=0 log keeps detail');

        // G2 debug=true
        $r2 = db_run_probe('sql_error_public', array('1'));
        TestAssert::true(db_mode_has($r2, '执行SQL发生错误'), 'G2 detailed message kept for debug');
        TestAssert::true(db_mode_has($r2, 'bogusfn'), 'G2 raw detail visible when debug');
        TestAssert::true(db_mode_has($r2, 'LOG_HAS_PREFIX=1'), 'G3 debug=1 log has prefix');
        TestAssert::true(db_mode_has($r2, 'LOG_HAS_STMT=1'), 'G3 debug=1 log has 语句：');
        TestAssert::true(db_mode_has($r2, 'LOG_HAS_SECRET_TABLE=1'), 'G3 debug=1 log keeps table');

        // G5 AJAX + debug=false
        $r5 = db_run_probe('sql_error_public', array('0', 'ajax'));
        TestAssert::true(db_mode_has($r5, $safeMsg), 'G5 AJAX/JSON returns generic message');
        TestAssert::false(db_mode_has($r5, 'ay_secret_table'), 'G5 AJAX no table leak');
        TestAssert::false(db_mode_has($r5, 'bogusfn'), 'G5 AJAX no detail leak');
        TestAssert::true(db_mode_has($r5, '"code":0') || db_mode_has($r5, '"code": 0'), 'G5 JSON code 0');
    }

    // G4 三驱动非调试文案字节级一致
    $safeNeedle = "error('数据库执行错误，请稍后重试或联系管理员！')";
    foreach (array('Sqlite.php', 'Mysqli.php', 'Pdo.php') as $file) {
        $src = file_get_contents(CORE_PATH . '/database/' . $file);
        TestAssert::contains($src, $safeNeedle, 'G4 ' . $file . ' safe message identical');
    }
});
