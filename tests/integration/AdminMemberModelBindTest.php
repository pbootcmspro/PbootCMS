<?php

declare(strict_types=1);

/**
 * @suite integration
 * @covers apps/admin/model/member/MemberModel.php
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once TEST_ROOT . 'support/DatabaseTestSupport.php';
require_once TEST_ROOT . 'support/ConfigStub.php';
require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/Model.php';
require_once CORE_PATH . '/database/Builder.php';
require_once APP_PATH . '/admin/model/member/MemberModel.php';

use app\admin\model\member\MemberModel;

/**
 * @param string $label
 * @return string|null
 */
function admin_member_boot_sqlite($label = 'admin_member')
{
    if (! extension_loaded('sqlite3')) {
        return null;
    }

    $rel = '/data/admin_member_bind_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $label) . '_' . getmypid() . '.db';
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
        score INTEGER NOT NULL DEFAULT 0
    )');
    $sqlite->exec("INSERT INTO ay_member_group (id, gcode, gname) VALUES (1, 'default', 'Default')");
    $pass = md5(md5('secret'));
    // A 的用户名等于 B 的邮箱交叉，验证 id<> 作用于整个 OR
    $sqlite->exec("INSERT INTO ay_member (id, ucode, username, useremail, usermobile, nickname, password, status, gid, score)
        VALUES (1, 'M001', 'alice', 'alice@example.com', '13800000001', 'Alice', '$pass', 1, 1, 0)");
    $sqlite->exec("INSERT INTO ay_member (id, ucode, username, useremail, usermobile, nickname, password, status, gid, score)
        VALUES (2, 'M002', 'bob', 'alice', '13800000002', 'Bob', '$pass', 1, 1, 0)");
    $sqlite->close();

    db_set_database_config(array(
        'type' => 'sqlite',
        'dbname' => $rel,
        'prefix' => 'ay_'
    ));
    db_reset_sqlite_singleton();
    return $sqlitePath;
}

return TestAssert::runSuite(function () {
    $sqlitePath = admin_member_boot_sqlite();
    if ($sqlitePath === null) {
        echo "SKIP: sqlite3 extension unavailable\n";
        return;
    }

    $model = new MemberModel();

    echo "=== checkMember OR + exclude id wraps whole group ===\n";
    TestAssert::true($model->checkMember('alice'), 'add: alice occupied by A username / B email');
    TestAssert::true($model->checkMember('alice', 1), 'edit A: alice still hits B email');
    TestAssert::false($model->checkMember('alice@example.com', 1), 'edit A: own email free');
    TestAssert::true($model->checkMember('bob', 1), 'edit A: bob occupied');
    TestAssert::false($model->checkMember('bob', 2), 'edit B: own username free');
    TestAssert::true($model->checkMember(array('alice')), 'array account treated occupied');

    echo "=== get/mod/del by bound id ===\n";
    $row = $model->getMember(1);
    TestAssert::true(is_object($row), 'getMember');
    TestAssert::same('Alice', (string) $row->nickname, 'getMember nickname');

    TestAssert::true((bool) $model->modMember(1, array(
        'nickname' => 'AliceX'
    )), 'modMember array');
    TestAssert::false($model->modMember(1, 'nickname=\'hack\''), 'modMember string SET rejected');
    TestAssert::same('AliceX', (string) $model->getMember(1)->nickname, 'modMember applied');

    echo "=== modMemberStatus ===\n";
    TestAssert::true((bool) $model->modMemberStatus(1, '0'), 'status 0 string');
    TestAssert::same(0, (int) $model->getMember(1)->status, 'status now 0');
    TestAssert::true((bool) $model->modMemberStatus(1, 1), 'status 1 int');
    TestAssert::same(1, (int) $model->getMember(1)->status, 'status now 1');
    TestAssert::false($model->modMemberStatus(1, '2'), 'status 2 rejected');
    TestAssert::false($model->modMemberStatus(1, ''), 'empty status rejected');
    TestAssert::false($model->modMemberStatus(1, array(1)), 'array status rejected');
    $model->modMemberStatus(99, '1');
    TestAssert::false((bool) $model->getMember(99), 'missing id still missing');
    TestAssert::same(1, (int) $model->getMember(1)->status, 'status unchanged after rejects');

    echo "=== batch status and delete ===\n";
    TestAssert::true((bool) $model->modMemberList(array(1, 2), array(
        'status' => 0
    )), 'batch disable');
    TestAssert::same(0, (int) $model->getMember(1)->status, 'id1 disabled');
    TestAssert::same(0, (int) $model->getMember(2)->status, 'id2 disabled');
    TestAssert::false($model->modMemberList(array(1), 'status=1'), 'batch string SET rejected');

    TestAssert::true((bool) $model->delMember(2), 'delMember');
    TestAssert::false((bool) $model->getMember(2), 'deleted');

    $payloads = array("'", '"', '?', '%', '_', '\\', "' OR '1'='1");
    foreach ($payloads as $payload) {
        TestAssert::false($model->checkMember($payload), 'literal account ' . $payload);
    }

    db_commit_sqlite();
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
    db_reset_all_drivers();
    gc_collect_cycles();
    if (is_file($sqlitePath)) {
        @unlink($sqlitePath);
    }
});
