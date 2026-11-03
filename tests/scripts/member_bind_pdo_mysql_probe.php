<?php

declare(strict_types=1);

/**
 * PDO MySQL 真实路径：在每次运行新建的测试库中验证 PDO 绑定与 MemberModel。
 *
 * 优先 PBOOT_TEST_MYSQL_URL；否则仅当 config/database.php 的库名含 test|init。
 * 测试账号需要有权创建和删除 pboot_member_probe_ 前缀的数据库。
 * 用法: php tests/scripts/member_bind_pdo_mysql_probe.php
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

$root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
define('ROOT_PATH', $root);
define('APP_PATH', $root . 'apps');
define('CORE_PATH', $root . 'core');
define('CONF_PATH', $root . 'config');
define('RUN_PATH', $root . 'runtime');
define('TEST_ROOT', dirname(__DIR__) . DIRECTORY_SEPARATOR);
defined('SITE_DIR') || define('SITE_DIR', '');

if (! extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "SKIP: pdo_mysql extension unavailable\n");
    exit(0);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/function/helper.php';
require_once TEST_ROOT . 'support/DatabaseTestSupport.php';
require_once TEST_ROOT . 'support/ConfigStub.php';
require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/Model.php';
require_once CORE_PATH . '/database/Builder.php';
require_once APP_PATH . '/home/model/MemberModel.php';
require_once APP_PATH . '/admin/model/member/MemberModel.php';

use app\home\model\MemberModel as HomeMemberModel;
use app\admin\model\member\MemberModel as AdminMemberModel;

$failed = 0;

function ok($cond, $msg)
{
    global $failed;
    if ($cond) {
        echo "OK: $msg\n";
    } else {
        echo "FAIL: $msg\n";
        $failed++;
    }
}

function pdo_mysql_commit()
{
    if (! class_exists('core\\database\\Pdo', false)) {
        return;
    }
    $inst = db_reflect_static('core\\database\\Pdo', 'pdo')->getValue();
    if ($inst instanceof \core\database\Pdo) {
        $inst->commitTransaction();
    }
}

/**
 * @return array|null host/port/user/passwd/dbname
 */
function probe_mysql_cfg()
{
    $url = getenv('PBOOT_TEST_MYSQL_URL');
    if (is_string($url) && trim($url) !== '') {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || empty($parts['host']) || empty($parts['path'])) {
            fwrite(STDERR, "SKIP: invalid PBOOT_TEST_MYSQL_URL\n");
            return null;
        }
        $name = ltrim((string) $parts['path'], '/');
        if ($name === '' || ! preg_match('/test|init/i', $name)) {
            fwrite(STDERR, "SKIP: PBOOT_TEST_MYSQL_URL database name must contain test or init\n");
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

    $dbFile = CONF_PATH . '/database.php';
    if (! is_file($dbFile)) {
        fwrite(STDERR, "SKIP: set PBOOT_TEST_MYSQL_URL or provide config/database.php\n");
        return null;
    }
    $cfg = require $dbFile;
    $c = isset($cfg['database']) ? $cfg['database'] : array();
    if (! isset($c['host'], $c['dbname'])) {
        fwrite(STDERR, "SKIP: no mysql host/dbname in config\n");
        return null;
    }
    if (! preg_match('/test|init/i', (string) $c['dbname'])) {
        fwrite(STDERR, "SKIP: config dbname must contain test or init (or set PBOOT_TEST_MYSQL_URL)\n");
        return null;
    }
    return array(
        'host' => $c['host'],
        'port' => isset($c['port']) ? (int) $c['port'] : 3306,
        'user' => isset($c['user']) ? $c['user'] : '',
        'passwd' => isset($c['passwd']) ? $c['passwd'] : '',
        'dbname' => $c['dbname']
    );
}

/**
 * @param array $c
 * @return PDO|null
 */
function probe_mysql_connect(array $c)
{
    try {
        $dsn = 'mysql:host=' . $c['host'] . ';port=' . $c['port'] . ';dbname=' . $c['dbname'] . ';charset=utf8';
        $pdo = new PDO($dsn, $c['user'], $c['passwd']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (Throwable $e) {
        fwrite(STDERR, 'SKIP: PDO MySQL unreachable: ' . $e->getMessage() . "\n");
        return null;
    }
}

$c = probe_mysql_cfg();
if ($c === null) {
    exit(0);
}

$pdo = probe_mysql_connect($c);
if ($pdo === null) {
    exit(0);
}

// MemberModel 按账号登录会更新命中的记录，两段探针都只连接本次创建的库。
try {
    $probeDatabase = 'pboot_member_probe_' . bin2hex(random_bytes(8));
    $pdo->exec("CREATE DATABASE `$probeDatabase` DEFAULT CHARACTER SET utf8");
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: could not create isolated probe database\n");
    exit(1);
}

$dropProbeDatabase = function () use ($pdo, &$probeDatabase) {
    if ($probeDatabase === null) {
        return true;
    }
    $name = $probeDatabase;
    $probeDatabase = null;
    try {
        $pdo->exec("DROP DATABASE `$name`");
        return true;
    } catch (Throwable $e) {
        fwrite(STDERR, "FAIL: could not drop probe database $name\n");
        return false;
    }
};
// 框架的 error() 可能直接退出，跳过 finally。
$completed = false;
register_shutdown_function(function () use ($dropProbeDatabase, &$completed) {
    if (! $completed) {
        $dropProbeDatabase();
        exit(1);
    }
});

$c['dbname'] = $probeDatabase;
$dsn = 'mysql:host=' . $c['host'] . ';port=' . $c['port'] . ';dbname=' . $c['dbname'] . ';charset=utf8';
$envSuffix = getenv('PBOOT_PROBE_SUFFIX');
$suffix = (is_string($envSuffix) && preg_match('/^[A-Za-z0-9_]{1,32}$/', $envSuffix)) ? $envSuffix : (string) getmypid();
$member = 'ay_member_pdo_' . $suffix;
$group = 'ay_member_group_pdo_' . $suffix;

echo "=== A: temp tables via core\\database\\Pdo ===\n";

try {
    $pdo->exec("USE `$probeDatabase`");
    $pdo->exec("CREATE TABLE `$group` (
        id INT PRIMARY KEY,
        gcode VARCHAR(50) NOT NULL DEFAULT '',
        gname VARCHAR(50) NOT NULL DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    $pdo->exec("CREATE TABLE `$member` (
        id INT PRIMARY KEY,
        ucode VARCHAR(50) NOT NULL DEFAULT '',
        username VARCHAR(100) NOT NULL DEFAULT '',
        useremail VARCHAR(100) NOT NULL DEFAULT '',
        usermobile VARCHAR(30) NOT NULL DEFAULT '',
        nickname VARCHAR(100) NOT NULL DEFAULT '',
        password VARCHAR(64) NOT NULL DEFAULT '',
        headpic VARCHAR(255) NOT NULL DEFAULT '',
        status TINYINT NOT NULL DEFAULT 1,
        gid INT NOT NULL DEFAULT 0,
        score INT NOT NULL DEFAULT 0,
        login_count INT NOT NULL DEFAULT 0,
        last_login_ip INT NOT NULL DEFAULT 0,
        last_login_time VARCHAR(30) NOT NULL DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $pass = md5(md5('secret'));
    $pdo->exec("INSERT INTO `$group` (id,gcode,gname) VALUES (1,'default','Default')");
    $pdo->exec("INSERT INTO `$member` (id,ucode,username,useremail,usermobile,nickname,password,status,gid,score,login_count)
        VALUES (1,'M001','alice','alice@example.com','13800000001','Alice','$pass',1,1,0,0)");
    $pdo->exec("INSERT INTO `$member` (id,ucode,username,useremail,usermobile,nickname,password,status,gid,score,login_count)
        VALUES (2,'M002','bob','alice','13800000002','Bob','$pass',1,1,0,0)");
    $pdo = null;

    $database = $c;
    $database['type'] = 'pdo_mysql';
    $database['prefix'] = 'ay_';
    db_set_database_config($database);
    ConfigStub::set(array('login_score' => 0));
    db_reset_pdo_singleton();

    require_once CORE_PATH . '/database/Pdo.php';
    $db = \core\database\Pdo::getInstance();

    $sql = "SELECT id FROM `$member` WHERE (`username`=? OR `useremail`=? OR `usermobile`=?) AND (`id`<>?) LIMIT 1";
    $rs = $db->one($sql, null, array('alice', 'alice', 'alice', 1));
    pdo_mysql_commit();
    ok(is_object($rs) && (int) $rs->id === 2, 'pdo_mysql: edit A alice still hits B email');

    $rs2 = $db->one($sql, null, array('alice@example.com', 'alice@example.com', 'alice@example.com', 1));
    ok($rs2 === null || $rs2 === false || ! isset($rs2->id), 'pdo_mysql: own email free when exclude self');

    $payload = "' OR '1'='1";
    $rs3 = $db->one(
        "SELECT id FROM `$member` WHERE (`username`=? OR `useremail`=? OR `usermobile`=?) LIMIT 1",
        null,
        array($payload, $payload, $payload)
    );
    ok($rs3 === null || $rs3 === false || ! isset($rs3->id), 'pdo_mysql: injection literal no match');

    $okUpd = $db->amd("UPDATE `$member` SET `status`=? WHERE (`id`=?)", array(0, 1));
    pdo_mysql_commit();
    ok((bool) $okUpd, 'pdo_mysql: status update ok');
    $st = $db->one("SELECT status FROM `$member` WHERE id=?", null, array(1));
    ok(is_object($st) && (int) $st->status === 0, 'pdo_mysql: status now 0');

    $db->amd("UPDATE `$member` SET `status`=? WHERE (`id`=?)", array(1, 1));
    pdo_mysql_commit();
    $st2 = $db->one("SELECT status FROM `$member` WHERE id=?", null, array(1));
    ok(is_object($st2) && (int) $st2->status === 1, 'pdo_mysql: status back to 1');

    foreach (array("'", '"', '?', '%', '_', '\\') as $ch) {
        $hit = $db->one("SELECT id FROM `$member` WHERE `username`=? LIMIT 1", null, array($ch));
        ok($hit === null || $hit === false || ! isset($hit->id), 'pdo_mysql literal username ' . $ch);
    }

    pdo_mysql_commit();
    db_reset_pdo_singleton();

} catch (Throwable $e) {
    echo 'FAIL: temp table exception ' . $e->getMessage() . "\n";
    $failed++;
}

echo "=== B: isolated ay_member via MemberModel + pdo_mysql ===\n";

$probeUid = 900000 + ((int) $suffix % 100000);
$probeUid2 = $probeUid + 1;
$probeUser = 'pb_' . $suffix . '_a';
$probeUser2 = 'pb_' . $suffix . '_b';
$probeEmail = 'pb_' . $suffix . '_a@example.com';
$probeEmail2 = 'pb_' . $suffix . '_b@example.com';
$probeMobile = '139' . str_pad((string) ((int) $suffix % 100000000), 8, '0', STR_PAD_LEFT);
$probeMobile2 = '138' . str_pad((string) ((int) $suffix % 100000000), 8, '0', STR_PAD_LEFT);
$probePassPlain = 'secret';
$probePass = md5(md5($probePassPlain));
$probeGid = 1;

try {
    $setup = new PDO($dsn, $c['user'], $c['passwd']);
    $setup->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $setup->exec("CREATE TABLE `ay_member_group` (
        id INT PRIMARY KEY,
        gcode VARCHAR(50) NOT NULL DEFAULT '',
        gname VARCHAR(50) NOT NULL DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    $setup->exec("INSERT INTO `ay_member_group` (id,gcode,gname) VALUES (1,'default','Default')");
    $setup->exec("CREATE TABLE `ay_member` (
        id INT PRIMARY KEY,
        ucode VARCHAR(50) NOT NULL DEFAULT '',
        username VARCHAR(100) NOT NULL DEFAULT '',
        useremail VARCHAR(100) NOT NULL DEFAULT '',
        usermobile VARCHAR(30) NOT NULL DEFAULT '',
        nickname VARCHAR(100) NOT NULL DEFAULT '',
        password VARCHAR(64) NOT NULL DEFAULT '',
        headpic VARCHAR(255) NOT NULL DEFAULT '',
        status TINYINT NOT NULL DEFAULT 1,
        gid INT NOT NULL DEFAULT 0,
        score INT NOT NULL DEFAULT 0,
        login_count INT NOT NULL DEFAULT 0,
        last_login_ip INT NOT NULL DEFAULT 0,
        last_login_time VARCHAR(30) NOT NULL DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $ins = $setup->prepare(
        'INSERT INTO `ay_member`
        (id,ucode,username,useremail,usermobile,nickname,password,status,gid,score,login_count,last_login_ip,last_login_time)
        VALUES
        (?,?,?,?,?,?,?,?,?,?,0,0,\'\'),
        (?,?,?,?,?,?,?,?,?,?,0,0,\'\')'
    );
    $ins->execute(array(
        $probeUid,
        'PB' . $suffix . 'A',
        $probeUser,
        $probeEmail,
        $probeMobile,
        'ProbeA',
        $probePass,
        1,
        $probeGid,
        0,
        $probeUid2,
        'PB' . $suffix . 'B',
        $probeUser2,
        $probeEmail2,
        $probeMobile2,
        'ProbeB',
        $probePass,
        1,
        $probeGid,
        0
    ));
    $setup = null;

    $database = $c;
    $database['type'] = 'pdo_mysql';
    $database['prefix'] = 'ay_';
    db_set_database_config($database);
    ConfigStub::set(array('login_score' => 0));
    db_reset_pdo_singleton();

    $home = new HomeMemberModel();
    $admin = new AdminMemberModel();

    $user = $home->findByAccount($probeUser);
    ok(is_object($user), 'probe pdo findByAccount');
    $uidMatch = is_object($user) && (int) $user->id === $probeUid;
    ok($uidMatch, 'probe pdo account id');
    if ($uidMatch) {
        $uid = $probeUid;
        $email = (string) $user->useremail;
        ok($email === $probeEmail, 'probe pdo account email');
        ok($home->existsByEmail($email, $uid) === false, 'probe pdo existsByEmail exclude self');
        ok($home->existsByAccount($probeUser) === true, 'probe pdo existsByAccount');

        $beforeLogin = $home->findByAccount($probeUser);
        $beforeOwned = is_object($beforeLogin) && (int) $beforeLogin->id === $probeUid;
        if (! $beforeOwned) {
            throw new RuntimeException('probe pdo beforeLogin owned');
        }
        ok(true, 'probe pdo beforeLogin owned');
        $beforeCount = (int) $beforeLogin->login_count;
        $login = $home->loginByAccount($probeUser, $probePass);
        pdo_mysql_commit();
        ok(is_object($login) && (int) $login->id === $probeUid, 'probe pdo loginByAccount');
        $afterLogin = $home->findByAccount($probeUser);
        ok(
            is_object($afterLogin)
            && (int) $afterLogin->id === $probeUid
            && (int) $afterLogin->login_count === $beforeCount + 1,
            'probe pdo login_count +1'
        );
        ok($home->loginByAccount($probeUser, md5(md5('wrong'))) ? false : true, 'probe pdo wrong password');
        ok($home->findByAccount("' OR '1'='1") ? false : true, 'probe pdo injection literal');
        ok($admin->checkMember($probeUser) === true, 'probe pdo admin checkMember occupied');
        ok($admin->checkMember($probeUser, $uid) === false, 'probe pdo admin exclude self username');
        if ($email !== '') {
            ok($admin->checkMember($email, $uid) === false, 'probe pdo admin exclude self email');
        }
        $detail = $admin->getMember($uid);
        ok(is_object($detail) && (int) $detail->id === $uid, 'probe pdo getMember');
        $before = (int) $detail->status;
        $to = $before === 1 ? 0 : 1;
        try {
            ok((bool) $admin->modMemberStatus($uid, (string) $to), 'probe pdo modMemberStatus toggle');
            pdo_mysql_commit();
            $mid = $admin->getMember($uid);
            ok((int) $mid->status === $to, 'probe pdo status changed');
        } finally {
            $admin->modMemberStatus($uid, (string) $before);
            pdo_mysql_commit();
        }
        $after = $admin->getMember($uid);
        ok((int) $after->status === $before, 'probe pdo status restored');
        ok($admin->modMemberStatus($uid, '2') === false, 'probe pdo reject status=2');
        ok($admin->modMember($uid, 'status=1') === false, 'probe pdo reject string SET');
        session('pboot_uid', $uid);
        $me = $home->getUser();
        ok(is_object($me) && (int) $me->id === $uid, 'probe pdo getUser');
    }
} catch (Throwable $e) {
    echo 'FAIL: isolated member exception ' . $e->getMessage() . "\n";
    $failed++;
} finally {
    pdo_mysql_commit();
    db_reset_all_drivers();
    if (! $dropProbeDatabase()) {
        $failed++;
    }
}
$completed = true;

echo "RESULT: " . ($failed > 0 ? "FAILED ($failed)" : 'PASSED') . "\n";
exit($failed > 0 ? 1 : 0);
