<?php

declare(strict_types=1);

/**
 * UpgradeController::upsql() 路径子进程探测（模拟 debug 错误处理）
 *
 * 用法: php tests/scripts/upgrade_sql_model_probe.php <load|upsql>
 */

ini_set('display_errors', '0');

$mode = isset($argv[1]) ? (string) $argv[1] : '';
if ($mode !== 'load' && $mode !== 'upsql') {
    fwrite(STDERR, "usage: upgrade_sql_model_probe.php <load|upsql>\n");
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

new \core\basic\Model();
echo "LOAD_OK\n";
exit(0);
