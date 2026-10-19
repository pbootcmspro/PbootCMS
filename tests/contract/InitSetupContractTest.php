<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers 首次登录初始化不改入口、Kernel、AdminController，并保留 modDB 与改密门控
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== 入口、Kernel、AdminController 没有安装器 ===\n";
    foreach (array('admin.php', 'index.php', 'api.php', 'core/basic/Kernel.php', 'apps/common/AdminController.php') as $file) {
        $src = file_get_contents(ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $file));
        TestAssert::false($src === false, $file . ' readable');
        TestAssert::notContains($src, 'PBOOT_INSTALL', $file . ' has no install constant');
        TestAssert::notContains($src, 'install.lock', $file . ' has no install lock');
        TestAssert::notContains($src, 'ensureInstallLock', $file . ' has no lock backfill');
    }

    echo "=== checkPHP 仍是 7.0 ===\n";
    $check = file_get_contents(CORE_PATH . '/basic/Check.php');
    TestAssert::false($check === false, 'Check.php readable');
    TestAssert::contains($check, "version_compare(phpversion(),'7.0.0','<')", 'checkPHP keeps 7.0');
    TestAssert::contains($check, "implode('<br>', \$miss)", 'checkApp reports the full list once');

    $source = file_get_contents(APP_PATH . '/admin/controller/IndexController.php');
    TestAssert::false($source === false, 'IndexController readable');

    echo "=== 切换分支受 isInitMode 保护，pwsecurity 在写入成功之后 ===\n";
    $start = strpos($source, 'public function ucenter()');
    $end = strpos($source, 'public function area()');
    TestAssert::true($start !== false && $end > $start, 'ucenter() located');
    $ucenter = substr($source, $start, $end - $start);
    $mod = strpos($ucenter, 'modUserInfo($data)');
    $pw = strpos($ucenter, "session('pwsecurity'");
    $switch = strpos($ucenter, '$this->switchMysql($data)');
    TestAssert::true($mod !== false && $pw !== false && $pw > $mod, 'pwsecurity is written after modUserInfo');
    TestAssert::contains($ucenter, "if (\$init && post('db_type') == 'mysql')", 'mysql switch is gated by init mode');
    TestAssert::true($switch !== false && $switch < $mod, 'mysql switch runs before the sqlite profile update');

    $initStart = strpos($source, 'private function isInitMode()');
    $initEnd = strpos($source, 'private function isDefaultDb(');
    TestAssert::true($initStart !== false && $initEnd > $initStart, 'isInitMode() located');
    $init = substr($source, $initStart, $initEnd - $initStart);
    TestAssert::contains($init, "! session('pwsecurity')", 'init mode requires default password');
    TestAssert::contains($init, "session('id') == 1", 'init mode requires super admin');
    TestAssert::contains($init, 'isDefaultDb', 'init mode requires the package database');

    $swStart = strpos($source, 'private function switchMysql(');
    $swEnd = strpos($source, 'private function abortMysql(');
    TestAssert::true($swStart !== false && $swEnd > $swStart, 'switchMysql() located');
    $switchBody = substr($source, $swStart, $swEnd - $swStart);
    $tablesAt = strpos($switchBody, 'ayTables');
    $importAt = strpos($switchBody, 'importSql');
    TestAssert::true($tablesAt !== false && $importAt !== false && $tablesAt < $importAt, 'existing ay_ tables are rejected before import');
    TestAssert::contains($switchBody, 'isInitMode()', 'switchMysql rechecks init mode');
    TestAssert::contains($switchBody, "RUN_PATH . '/archive/init_db.lock'", 'switch uses the runtime lock');
    TestAssert::contains($switchBody, 'LOCK_EX | LOCK_NB', 'switch lock is non-blocking');
    $fieldAt = strpos($switchBody, 'adminFieldError(');
    $connAt = strpos($switchBody, '->conn(');
    TestAssert::true($fieldAt !== false && $connAt !== false && $fieldAt < $connAt, 'admin field length is checked before connect');
    $inputAt = strpos($switchBody, 'checkMysqlInput(');
    $portCastAt = strpos($switchBody, "(int) \$db['port']");
    TestAssert::true($inputAt !== false && $portCastAt !== false && $inputAt < $portCastAt, 'port is validated before integer cast');
    $unsetAt = strpos($switchBody, 'session_unset()');
    $logAt = strpos($switchBody, 'writeSwitchLog(');
    TestAssert::true($unsetAt !== false && $logAt !== false && $unsetAt < $logAt, 'session is cleared before the switch log');
    TestAssert::contains($switchBody, 'setFailSoft(true)', 'switch log cannot abort the redirect');
    TestAssert::contains($source, 'function modDB()', 'modDB() remains');

    echo "=== 首页告警与 modDB 契约保持不变 ===\n";
    $home = file_get_contents(APP_PATH . '/admin/view/default/system/home.html');
    TestAssert::false($home === false, 'home.html readable');
    TestAssert::contains($home, '您的数据库文件存在安全隐患，可能被下载，请尽快修改数据库路径！', 'home warning text unchanged');
    TestAssert::contains($home, 'action=moddb', 'home manual rename link unchanged');
    $dbTest = file_get_contents(TEST_ROOT . 'unit/AdminHomeDefaultDbTest.php');
    TestAssert::contains($dbTest, 'home(): auto modDB retained', 'AdminHomeDefaultDbTest still requires modDB');
    TestAssert::contains($dbTest, '&& \\$this->modDB()', 'AdminHomeDefaultDbTest still asserts auto rename');

    $ucenter = file_get_contents(APP_PATH . '/admin/view/default/system/ucenter.html');
    TestAssert::contains($ucenter, 'name="formcheck"', 'ucenter keeps formcheck');
    TestAssert::contains($ucenter, '首次使用，请完成初始化设置。', 'init banner present');
    TestAssert::contains($ucenter, '您的账号密码为初始密码，存在安全隐患，必须修改密码才能查看后台主页！', 'existing password warning kept');
    TestAssert::contains($ucenter, 'name="db_type"', 'database choice is on the init form');
});
