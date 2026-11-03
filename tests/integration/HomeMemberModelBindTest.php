<?php

declare(strict_types=1);

/**
 * @suite integration
 * @covers apps/home/model/MemberModel.php
 *
 * SQLite / 可选 PDO SQLite / 可选 MySQLi 上验证会员绑定与排除自身。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once TEST_ROOT . 'support/DatabaseTestSupport.php';
require_once TEST_ROOT . 'support/ConfigStub.php';
require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/Model.php';
require_once CORE_PATH . '/database/Builder.php';
require_once APP_PATH . '/home/model/MemberModel.php';

use app\home\model\MemberModel;

/**
 * @param string $label
 * @return string|null
 */
function home_member_boot_sqlite($label = 'home_member')
{
    if (! extension_loaded('sqlite3')) {
        return null;
    }

    $rel = '/data/home_member_bind_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $label) . '_' . getmypid() . '.db';
    $sqlitePath = ROOT_PATH . $rel;
    if (is_file($sqlitePath)) {
        @unlink($sqlitePath);
    }
    $dir = dirname($sqlitePath);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $sqlite = new SQLite3($sqlitePath);
    $sqlite->busyTimeout(5000);
    $sqlite->exec('CREATE TABLE ay_member_group (
        id INTEGER PRIMARY KEY,
        gcode TEXT NOT NULL DEFAULT \'\',
        gname TEXT NOT NULL DEFAULT \'\'
    )');
    $sqlite->exec('CREATE TABLE ay_member (
        id INTEGER PRIMARY KEY,
        ucode TEXT NOT NULL DEFAULT \'\',
        username TEXT NOT NULL DEFAULT \'\',
        useremail TEXT NOT NULL DEFAULT \'\',
        usermobile TEXT NOT NULL DEFAULT \'\',
        nickname TEXT NOT NULL DEFAULT \'\',
        password TEXT NOT NULL DEFAULT \'\',
        headpic TEXT NOT NULL DEFAULT \'\',
        status INTEGER NOT NULL DEFAULT 1,
        gid INTEGER NOT NULL DEFAULT 0,
        score INTEGER NOT NULL DEFAULT 0,
        login_count INTEGER NOT NULL DEFAULT 0,
        last_login_ip INTEGER NOT NULL DEFAULT 0,
        last_login_time TEXT NOT NULL DEFAULT \'\'
    )');
    $sqlite->exec("INSERT INTO ay_member_group (id, gcode, gname) VALUES (1, 'default', 'Default')");
    $sqlite->exec("INSERT INTO ay_member_group (id, gcode, gname) VALUES (2, 'vip', 'VIP')");
    $pass = md5(md5('secret'));
    $sqlite->exec("INSERT INTO ay_member (id, ucode, username, useremail, usermobile, nickname, password, status, gid, score, login_count)
        VALUES (1, 'M001', 'alice', 'alice@example.com', '13800000001', 'Alice', '$pass', 1, 1, 10, 0)");
    $sqlite->exec("INSERT INTO ay_member (id, ucode, username, useremail, usermobile, nickname, password, status, gid, score, login_count)
        VALUES (2, 'M002', 'bob', 'bob@example.com', '13800000002', 'Bob', '$pass', 1, 1, 0, 0)");
    $sqlite->exec("INSERT INTO ay_member (id, ucode, username, useremail, usermobile, nickname, password, status, gid, score, login_count)
        VALUES (3, 'M003', 'quote_user', 'q\"ote@ex.com', '13800000003', 'Q', '$pass', 1, 2, 0, 0)");
    $sqlite->close();

    db_set_database_config(array(
        'type' => 'sqlite',
        'dbname' => $rel,
        'prefix' => 'ay_'
    ));
    ConfigStub::set(array(
        'login_score' => 0
    ));
    db_reset_sqlite_singleton();
    return $sqlitePath;
}

/**
 * @param string $label
 * @return string|null
 */
function home_member_boot_pdo($label = 'home_member_pdo')
{
    if (! extension_loaded('pdo_sqlite')) {
        return null;
    }

    $rel = '/data/home_member_bind_pdo_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $label) . '_' . getmypid() . '.db';
    $sqlitePath = ROOT_PATH . $rel;
    if (is_file($sqlitePath)) {
        @unlink($sqlitePath);
    }
    $dir = dirname($sqlitePath);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $pdo = new PDO('sqlite:' . $sqlitePath);
    $pdo->exec('CREATE TABLE ay_member_group (
        id INTEGER PRIMARY KEY,
        gcode TEXT NOT NULL DEFAULT \'\',
        gname TEXT NOT NULL DEFAULT \'\'
    )');
    $pdo->exec('CREATE TABLE ay_member (
        id INTEGER PRIMARY KEY,
        ucode TEXT NOT NULL DEFAULT \'\',
        username TEXT NOT NULL DEFAULT \'\',
        useremail TEXT NOT NULL DEFAULT \'\',
        usermobile TEXT NOT NULL DEFAULT \'\',
        nickname TEXT NOT NULL DEFAULT \'\',
        password TEXT NOT NULL DEFAULT \'\',
        headpic TEXT NOT NULL DEFAULT \'\',
        status INTEGER NOT NULL DEFAULT 1,
        gid INTEGER NOT NULL DEFAULT 0,
        score INTEGER NOT NULL DEFAULT 0,
        login_count INTEGER NOT NULL DEFAULT 0,
        last_login_ip INTEGER NOT NULL DEFAULT 0,
        last_login_time TEXT NOT NULL DEFAULT \'\'
    )');
    $pdo->exec("INSERT INTO ay_member_group (id, gcode, gname) VALUES (1, 'default', 'Default')");
    $pass = md5(md5('secret'));
    $pdo->exec("INSERT INTO ay_member (id, ucode, username, useremail, usermobile, nickname, password, status, gid, score, login_count)
        VALUES (1, 'M001', 'alice', 'alice@example.com', '13800000001', 'Alice', '$pass', 1, 1, 10, 0)");
    $pdo->exec("INSERT INTO ay_member (id, ucode, username, useremail, usermobile, nickname, password, status, gid, score, login_count)
        VALUES (2, 'M002', 'bob', 'bob@example.com', '13800000002', 'Bob', '$pass', 1, 1, 0, 0)");
    $pdo = null;

    db_set_database_config(array(
        'type' => 'pdo_sqlite',
        'dbname' => $rel,
        'prefix' => 'ay_'
    ));
    ConfigStub::set(array(
        'login_score' => 0
    ));
    db_reset_pdo_singleton();
    return $sqlitePath;
}

/**
 * PDO SQLite 主从为不同连接，写后需提交事务才能被读连接看见
 */
function home_member_commit()
{
    db_commit_sqlite();
    if (! class_exists('core\\database\\Pdo', false)) {
        return;
    }
    $instance = db_reflect_static('core\\database\\Pdo', 'pdo')->getValue();
    if ($instance instanceof \core\database\Pdo) {
        $instance->commitTransaction();
    }
}

function home_member_close_drivers()
{
    home_member_commit();
    if (class_exists('core\\database\\Sqlite', false)) {
        $inst = db_reflect_static('core\\database\\Sqlite', 'sqlite')->getValue();
        if ($inst instanceof \core\database\Sqlite) {
            $masterProp = (new ReflectionClass($inst))->getProperty('master');
            if (PHP_VERSION_ID < 80100) {
                $masterProp->setAccessible(true);
            }
            $master = $masterProp->getValue($inst);
            if ($master instanceof SQLite3) {
                $master->close();
            }
            $masterProp->setValue($inst, null);
        }
    }
    if (class_exists('core\\database\\Pdo', false)) {
        $inst = db_reflect_static('core\\database\\Pdo', 'pdo')->getValue();
        if ($inst instanceof \core\database\Pdo) {
            foreach (array('master', 'slave') as $name) {
                $prop = (new ReflectionClass($inst))->getProperty($name);
                if (PHP_VERSION_ID < 80100) {
                    $prop->setAccessible(true);
                }
                $conn = $prop->getValue($inst);
                if ($conn instanceof PDO) {
                    $prop->setValue($inst, null);
                }
            }
        }
    }
    db_reset_all_drivers();
    gc_collect_cycles();
}

/**
 * @param MemberModel $model
 */
function home_member_run_core_cases($model)
{
    echo "=== getUser / modUser session id bind ===\n";
    session('pboot_uid', 1);
    $user = $model->getUser();
    TestAssert::true(is_object($user), 'getUser hits');
    TestAssert::same(1, (int) $user->id, 'getUser id');
    TestAssert::same('Alice', (string) $user->nickname, 'getUser nickname');

    TestAssert::true((bool) $model->modUser(array(
        'nickname' => 'Alice2'
    )), 'modUser ok');
    home_member_commit();
    TestAssert::same('Alice2', (string) $model->getUser()->nickname, 'modUser only self');
    session('pboot_uid', 2);
    TestAssert::same('Bob', (string) $model->getUser()->nickname, 'other member untouched');

    echo "=== getGroupID ===\n";
    session('pboot_uid', 1);
    TestAssert::same(1, (int) $model->getGroupID('default'), 'group default');
    TestAssert::same(null, $model->getGroupID(array('default')), 'array gcode rejected');

    echo "=== existsByEmail / existsByMobile exclude self ===\n";
    TestAssert::false($model->existsByEmail('alice@example.com', 1), 'own email not occupied');
    TestAssert::true($model->existsByEmail('bob@example.com', 1), 'other email occupied');
    TestAssert::false($model->existsByMobile('13800000001', 1), 'own mobile not occupied');
    TestAssert::true($model->existsByMobile('13800000002', 1), 'other mobile occupied');
    TestAssert::true($model->existsByEmail(array('alice@example.com')), 'array email treated occupied');

    echo "=== loginByAccount password AND + stats ===\n";
    $before = (int) $model->getUser()->login_count;
    $login = $model->loginByAccount('alice', md5(md5('secret')));
    home_member_commit();
    TestAssert::true(is_object($login), 'login ok');
    TestAssert::same(1, (int) $login->id, 'login id');
    TestAssert::false((bool) $model->loginByAccount('alice', md5(md5('wrong'))), 'wrong password');
    TestAssert::false((bool) $model->loginByAccount(array('alice', 'bob'), md5(md5('secret'))), 'array account rejected');
    session('pboot_uid', 1);
    TestAssert::same($before + 1, (int) $model->getUser()->login_count, 'login_count +1');

    echo "=== literal special chars ===\n";
    $payloads = array("'", '"', '?', '%', '_', '\\', "' OR '1'='1", "a' OR id<>'0");
    foreach ($payloads as $payload) {
        TestAssert::false((bool) $model->findByAccount($payload), 'find literal ' . $payload);
        TestAssert::false($model->existsByEmail($payload), 'email literal ' . $payload);
        TestAssert::false($model->existsByMobile($payload), 'mobile literal ' . $payload);
        TestAssert::same(null, $model->getGroupID($payload), 'gcode literal ' . $payload);
    }

    echo "=== legacy where reject ===\n";
    TestAssert::same(null, $model->checkUsername('username=\'alice\''), 'string where rejected');
    TestAssert::same(null, $model->checkUsername(array(
        0 => "username='alice'"
    )), 'int-key where rejected');
    TestAssert::same(null, $model->checkUsername(array()), 'empty where rejected');
    TestAssert::same(null, $model->checkUsername(array(
        'username' => array('alice', 'bob')
    )), 'array value rejected');
    TestAssert::false($model->updatePassword(array(
        'username' => array('alice', 'bob')
    ), array(
        'password' => 'x'
    )), 'array updatePassword rejected');
    TestAssert::false($model->updatePassword(array(), array(
        'password' => 'x'
    )), 'empty updatePassword rejected');
    TestAssert::false($model->updatePassword(array(
        'username' => 'alice'
    ), array(
        'password' => 'x',
        'score' => 9
    )), 'extra update field rejected');

    $bobPass = (string) (new MemberModel())->table('ay_member')->where(array(
        'id' => 2
    ))->value('password');
    TestAssert::true($model->updatePassword(array(
        'id' => 1
    ), array(
        'password' => md5(md5('newpass'))
    )), 'update by id ok');
    home_member_commit();
    $bobPassAfter = (string) (new MemberModel())->table('ay_member')->where(array(
        'id' => 2
    ))->value('password');
    TestAssert::same($bobPass, $bobPassAfter, 'other password unchanged');
}

return TestAssert::runSuite(function () {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    $paths = array();

    echo "=== SQLite3 ===\n";
    $sqlitePath = home_member_boot_sqlite();
    if ($sqlitePath === null) {
        echo "SKIP: sqlite3 extension unavailable\n";
    } else {
        $paths[] = $sqlitePath;
        home_member_run_core_cases(new MemberModel());
    }

    echo "=== PDO SQLite ===\n";
    $pdoPath = home_member_boot_pdo();
    if ($pdoPath === null) {
        echo "SKIP: pdo_sqlite extension unavailable\n";
    } else {
        $paths[] = $pdoPath;
        home_member_run_core_cases(new MemberModel());
    }

    $mysqli = db_boot_mysqli_or_null();
    if ($mysqli === null) {
        echo "SKIP MySQLi: no reachable MySQL from config/database.php\n";
    } else {
        echo "NOTE MySQLi reachable but member bind cases covered on SQLite/PDO; skip mutating shared DB\n";
        $mysqli->close();
    }

    home_member_close_drivers();
    foreach ($paths as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
});
