<?php

declare(strict_types=1);

/**
 * UpgradeController::upsql() 路径子进程探测（模拟 debug 错误处理）
 *
 * 用法: php tests/scripts/upgrade_sql_model_probe.php <load|upsql|upsql_ddl|upsql_fail|blank>
 */

ini_set('display_errors', '0');

$mode = isset($argv[1]) ? (string) $argv[1] : '';
if (! in_array($mode, array('load', 'upsql', 'upsql_ddl', 'upsql_fail', 'blank'), true)) {
    fwrite(STDERR, "usage: upgrade_sql_model_probe.php <load|upsql|upsql_ddl|upsql_fail|blank>\n");
    exit(2);
}

$sourcePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
$sourcePath = realpath($sourcePath);
if ($sourcePath === false) {
    fwrite(STDERR, "cannot resolve project root\n");
    exit(2);
}
$sourcePath .= DIRECTORY_SEPARATOR;

define('ROOT_PATH', $sourcePath);
define('APP_PATH', $sourcePath . 'apps');
define('CORE_PATH', $sourcePath . 'core');
define('CONF_PATH', $sourcePath . 'config');
define('RUN_PATH', $sourcePath . 'runtime');
defined('SITE_DIR') || define('SITE_DIR', '');

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/function/helper.php';
require CORE_PATH . '/basic/Basic.php';

set_error_handler('core\\basic\\Basic::errorHandler');
error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);

spl_autoload_register('core\\basic\\Basic::autoLoad', true, true);

if ($mode === 'upsql') {
    if (! extension_loaded('sqlite3')) {
        fwrite(STDERR, "sqlite3 extension required for upsql mode\n");
        exit(77);
    }

    require CORE_PATH . '/basic/Config.php';

    $rel = '/data/upgrade_sql_probe_' . getmypid() . '.db';
    $sqlitePath = ROOT_PATH . $rel;
    if (is_file($sqlitePath)) {
        @unlink($sqlitePath);
    }
    $sqliteDir = dirname($sqlitePath);
    if (! is_dir($sqliteDir)) {
        mkdir($sqliteDir, 0777, true);
    }

    $ref = new ReflectionClass('core\\basic\\Config');
    $prop = $ref->getProperty('configs');
    if (PHP_VERSION_ID < 80100) {
        $prop->setAccessible(true);
    }
    $configs = $prop->getValue();
    if (! is_array($configs)) {
        $configs = array();
    }
    $configs['database'] = array(
        'type' => 'sqlite',
        'dbname' => $rel,
        'prefix' => 'ay_',
    );
    $prop->setValue(null, $configs);

    try {
        $sqliteRef = new ReflectionClass('core\\database\\Sqlite');
        $driverProp = $sqliteRef->getProperty('sqlite');
        if (PHP_VERSION_ID < 80100) {
            $driverProp->setAccessible(true);
        }
        $driverProp->setValue(null, null);
    } catch (ReflectionException $e) {
        // Sqlite 尚未加载，无需复位单例
    }

    $sql = "CREATE TABLE IF NOT EXISTS ay_upgrade_probe (id INTEGER PRIMARY KEY, note TEXT NOT NULL DEFAULT '')";
    $model = new \core\basic\Model();
    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $model->amd($statement);
        }
    }

    @unlink($sqlitePath);
    echo "UPSQL_OK\n";
    exit(0);
}

if ($mode === 'blank') {
    // isSqlBlank() 纯函数校验：注释/空白片段判定
    $ctlRef = new ReflectionClass('app\\admin\\controller\\system\\UpgradeController');
    $ctl = $ctlRef->newInstanceWithoutConstructor();
    $m = $ctlRef->getMethod('isSqlBlank');
    if (PHP_VERSION_ID < 80100) {
        $m->setAccessible(true);
    }
    $cases = array(
        array('-- foo', true),
        array("/* x */\n  \n", true),
        array('# c', true),
        array("-- c\nSELECT 1", false),
        array("SELECT '-- literal'", false),
    );
    $bad = 0;
    foreach ($cases as $case) {
        $got = $m->invoke($ctl, $case[0]);
        if ($got !== $case[1]) {
            $bad++;
            echo 'BLANK_BAD want=' . var_export($case[1], true) . ' got=' . var_export($got, true) . "\n";
        }
    }
    echo $bad === 0 ? "BLANK_OK\n" : "BLANK_FAIL=$bad\n";
    exit($bad === 0 ? 0 : 1);
}

if ($mode === 'upsql_ddl' || $mode === 'upsql_fail') {
    if (! extension_loaded('sqlite3')) {
        fwrite(STDERR, "sqlite3 extension required for $mode mode\n");
        exit(77);
    }

    require CORE_PATH . '/basic/Config.php';

    $rel = '/data/upgrade_sql_probe_' . getmypid() . '.db';
    $sqlitePath = ROOT_PATH . $rel;
    if (is_file($sqlitePath)) {
        @unlink($sqlitePath);
    }
    $sqliteDir = dirname($sqlitePath);
    if (! is_dir($sqliteDir)) {
        mkdir($sqliteDir, 0777, true);
    }

    $prop = (new ReflectionClass('core\\basic\\Config'))->getProperty('configs');
    if (PHP_VERSION_ID < 80100) {
        $prop->setAccessible(true);
    }
    $configs = $prop->getValue();
    if (! is_array($configs)) {
        $configs = array();
    }
    $configs['database'] = array('type' => 'sqlite', 'dbname' => $rel, 'prefix' => 'ay_');
    $prop->setValue(null, $configs);

    try {
        $driverProp = (new ReflectionClass('core\\database\\Sqlite'))->getProperty('sqlite');
        if (PHP_VERSION_ID < 80100) {
            $driverProp->setAccessible(true);
        }
        $driverProp->setValue(null, null);
    } catch (ReflectionException $e) {
        // Sqlite 尚未加载，无需复位单例
    }

    $ctlRef = new ReflectionClass('app\\admin\\controller\\system\\UpgradeController');
    $ctl = $ctlRef->newInstanceWithoutConstructor();
    $upsql = $ctlRef->getMethod('upsql');
    if (PHP_VERSION_ID < 80100) {
        $upsql->setAccessible(true);
    }

    if ($mode === 'upsql_ddl') {
        // 注释 + DDL（影响行数 0）+ DML + 尾随纯注释段，全部应通过
        $sql = "-- 升级脚本\n/* 说明 */\n"
            . "CREATE TABLE IF NOT EXISTS ay_upgrade_probe (id INTEGER PRIMARY KEY, note TEXT NOT NULL DEFAULT '');"
            . "INSERT INTO ay_upgrade_probe (note) VALUES ('ok');"
            . "ALTER TABLE ay_upgrade_probe ADD COLUMN extra TEXT;"
            . "-- done";
        $result = $upsql->invoke($ctl, $sql);
        @unlink($sqlitePath);
        echo $result === true ? "UPSQL_DDL_OK\n" : ('UPSQL_DDL_BAD:' . var_export($result, true) . "\n");
        exit($result === true ? 0 : 1);
    }

    // upsql_fail：前一条成功、后一条必然失败，期望返回 false 且不 error()+exit
    $sql = "CREATE TABLE IF NOT EXISTS ay_upgrade_probe (id INTEGER PRIMARY KEY);"
        . "INSERT INTO ay_upgrade_probe (no_such_column) VALUES (1)";
    $result = $upsql->invoke($ctl, $sql);
    @unlink($sqlitePath);
    echo $result === false ? "UPSQL_FAIL_DETECTED\n" : ('UPSQL_FAIL_BAD:' . var_export($result, true) . "\n");
    exit($result === false ? 0 : 1);
}

new \core\basic\Model();
echo "LOAD_OK\n";
exit(0);
