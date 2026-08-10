<?php

declare(strict_types=1);

/**
 * 数据库驱动测试共用脚手架（Sqlite / Mysqli / Pdo 单例、配置注入、子进程探测）
 *
 * 注意：勿在 tests/bootstrap.php 全局引入本文件——部分旧测试仍用 require（非 once）
 * 加载 Config.php，提前引入会导致 class redeclare。
 */

if (! class_exists('core\\basic\\Config', false)) {
    require_once CORE_PATH . '/basic/Config.php';
}
if (! interface_exists('core\\database\\Builder', false)) {
    require_once CORE_PATH . '/database/Builder.php';
}

function db_sqlite_available(): bool
{
    return extension_loaded('sqlite3') || extension_loaded('pdo_sqlite');
}

function db_proc_ok($proc): bool
{
    return $proc !== false && (is_resource($proc) || is_object($proc));
}

function db_config_reflection(): ReflectionProperty
{
    $ref = new ReflectionClass('core\\basic\\Config');
    $prop = $ref->getProperty('configs');
    if (PHP_VERSION_ID < 80100) {
        $prop->setAccessible(true);
    }
    return $prop;
}

/**
 * @param array $database Config['database'] 片段
 */
function db_set_database_config(array $database)
{
    $prop = db_config_reflection();
    $configs = $prop->getValue();
    if (! is_array($configs)) {
        $configs = array();
    }
    $configs['database'] = $database;
    $prop->setValue(null, $configs);
}

function db_reflect_static(string $class, string $propName)
{
    if (! class_exists($class, false)) {
        $base = CORE_PATH . '/database/';
        if (strpos($class, 'Sqlite') !== false) {
            require_once $base . 'Sqlite.php';
        } elseif (strpos($class, 'Mysqli') !== false) {
            require_once $base . 'Mysqli.php';
        } elseif (strpos($class, 'Pdo') !== false) {
            require_once $base . 'Pdo.php';
        }
    }
    $ref = new ReflectionClass($class);
    $prop = $ref->getProperty($propName);
    if (PHP_VERSION_ID < 80100) {
        $prop->setAccessible(true);
    }
    return $prop;
}

function db_reset_sqlite_singleton()
{
    db_reflect_static('core\\database\\Sqlite', 'sqlite')->setValue(null, null);
}

function db_reset_mysqli_singleton()
{
    db_reflect_static('core\\database\\Mysqli', 'mysqli')->setValue(null, null);
}

function db_reset_pdo_singleton()
{
    db_reflect_static('core\\database\\Pdo', 'pdo')->setValue(null, null);
}

/** 复位三驱动单例与 failSoft 作用域（含引用计数） */
function db_reset_all_drivers()
{
    foreach (array('core\\database\\Sqlite', 'core\\database\\Mysqli', 'core\\database\\Pdo') as $class) {
        try {
            if ($class === 'core\\database\\Sqlite') {
                db_reset_sqlite_singleton();
            } elseif ($class === 'core\\database\\Mysqli') {
                db_reset_mysqli_singleton();
            } else {
                db_reset_pdo_singleton();
            }
            db_reflect_static($class, 'failSoftDepth')->setValue(null, 0);
            db_reflect_static($class, 'failSoft')->setValue(null, false);
            db_reflect_static($class, 'failSoftError')->setValue(null, false);
        } catch (ReflectionException $e) {
            // 类未加载时跳过
        }
    }
}

function db_failsoft_depth(string $class): int
{
    return (int) db_reflect_static($class, 'failSoftDepth')->getValue();
}

function db_failsoft_enabled(string $class): bool
{
    return (bool) db_reflect_static($class, 'failSoft')->getValue();
}

/** PbootCMS Sqlite 写路径开显式事务，读库前必须提交，否则另一连接看不到更新 */
function db_commit_sqlite()
{
    if (! class_exists('core\\database\\Sqlite', false)) {
        return;
    }
    $instance = db_reflect_static('core\\database\\Sqlite', 'sqlite')->getValue();
    if ($instance instanceof \core\database\Sqlite) {
        $instance->commitTransaction();
    }
}

/**
 * 引导 sqlite3 驱动：建库、写 Config、清单例。
 *
 * @return string|null 绝对库路径；扩展不可用时 null
 */
function db_boot_sqlite(string $label = 'db_support')
{
    if (! extension_loaded('sqlite3')) {
        return null;
    }

    $rel = '/data/db_error_mode_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $label) . '_' . getmypid() . '.db';
    $sqlitePath = ROOT_PATH . $rel;
    if (is_file($sqlitePath)) {
        @unlink($sqlitePath);
    }
    $sqliteDir = dirname($sqlitePath);
    if (! is_dir($sqliteDir)) {
        mkdir($sqliteDir, 0777, true);
    }

    $sqlite = new SQLite3($sqlitePath);
    $sqlite->busyTimeout(5000);
    $sqlite->exec('CREATE TABLE ay_content (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL DEFAULT \'\',
        visits INTEGER NOT NULL DEFAULT 0
    )');
    $sqlite->exec("INSERT INTO ay_content (id, title, visits) VALUES (1, 'seed', 0)");
    $sqlite->close();

    db_set_database_config(array(
        'type' => 'sqlite',
        'dbname' => $rel
    ));
    db_reset_sqlite_singleton();

    return $sqlitePath;
}

/**
 * 引导 pdo_sqlite。
 *
 * @return string|null
 */
function db_boot_pdo_sqlite(string $label = 'pdo_sqlite')
{
    if (! extension_loaded('pdo_sqlite')) {
        return null;
    }

    $rel = '/data/db_error_mode_pdo_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $label) . '_' . getmypid() . '.db';
    $sqlitePath = ROOT_PATH . $rel;
    if (is_file($sqlitePath)) {
        @unlink($sqlitePath);
    }
    $dir = dirname($sqlitePath);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $pdo = new PDO('sqlite:' . $sqlitePath);
    $pdo->exec('CREATE TABLE ay_content (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL DEFAULT \'\',
        visits INTEGER NOT NULL DEFAULT 0
    )');
    $pdo->exec("INSERT INTO ay_content (id, title, visits) VALUES (1, 'seed', 0)");
    $pdo = null;

    db_set_database_config(array(
        'type' => 'pdo_sqlite',
        'dbname' => $rel
    ));
    db_reset_pdo_singleton();

    return $sqlitePath;
}

/**
 * 尝试按 config/database.php 连 MySQL；失败返回 null。
 *
 * @return mysqli|null
 */
function db_boot_mysqli_or_null()
{
    if (! extension_loaded('mysqli')) {
        return null;
    }
    $dbFile = ROOT_PATH . 'config/database.php';
    if (! is_file($dbFile)) {
        return null;
    }
    $cfg = require $dbFile;
    $c = isset($cfg['database']) ? $cfg['database'] : array();
    if (! isset($c['type']) || stripos((string) $c['type'], 'sqlite') !== false) {
        return null;
    }
    if (stripos((string) $c['type'], 'mysql') === false && (string) $c['type'] !== 'mysqli') {
        // 允许 type=mysqli / pdo_mysql；连接仍用原生 mysqli 探测可达性
    }

    try {
        $mysqli = @new mysqli(
            $c['host'] ?? '127.0.0.1',
            $c['user'] ?? 'root',
            $c['passwd'] ?? '',
            $c['dbname'] ?? '',
            (int) ($c['port'] ?? 3306)
        );
    } catch (Throwable $e) {
        return null;
    }
    if ($mysqli->connect_errno) {
        return null;
    }
    $mysqli->set_charset('utf8');
    return $mysqli;
}

/**
 * @return array{stdout:string,stderr:string,exit_code:int}
 */
function db_run_probe(string $mode, array $extraArgs = array()): array
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'db_error_mode_probe.php';
    // Windows PHP 7.x：勿对 PHP 可执行文件 escapeshellarg
    $args = array_merge(array($script, $mode), $extraArgs);
    $cmd = $php . ' ' . implode(' ', array_map('escapeshellarg', $args));

    $descriptors = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $pipes = array();
    $proc = proc_open($cmd, $descriptors, $pipes);
    if (! db_proc_ok($proc)) {
        return array('stdout' => '', 'stderr' => '', 'exit_code' => -1);
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return array(
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
        'exit_code' => proc_close($proc)
    );
}
