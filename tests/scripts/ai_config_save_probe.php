<?php

declare(strict_types=1);

/**
 * ConfigController::index() 保存 AI 配置探测：真实控制器 + 临时库 ay_config，
 * 进程结束时输出整表快照，用于核对校验失败时不部分入库。
 *
 * 用法: php tests/scripts/ai_config_save_probe.php <scenario.json>
 *
 * scenario:
 *   driver   sqlite|pdo_sqlite|mysqli（mysqli 需额外给 mysql: {host,user,passwd,dbname,port}）
 *   seed     ay_config 初始行 name => value（入库态）
 *   post     $_POST 原始值
 * 输出最后一行: SNAPSHOT=<json>
 */

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED);

$file = isset($argv[1]) ? (string) $argv[1] : '';
$scenario = $file !== '' && is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
if (! is_array($scenario)) {
    fwrite(STDERR, "missing scenario\n");
    exit(2);
}

$sourcePath = realpath(__DIR__ . '/../..') . DIRECTORY_SEPARATOR;
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot-ai-save-' . getmypid() . DIRECTORY_SEPARATOR;
@mkdir($tmp . 'runtime', 0777, true);
@mkdir($tmp . 'data', 0777, true);
define('ROOT_PATH', $sourcePath);
define('APP_PATH', $sourcePath . 'apps');
define('CORE_PATH', $sourcePath . 'core');
define('CONF_PATH', $sourcePath . 'config');
define('RUN_PATH', $tmp . 'runtime');
define('DOC_PATH', $tmp);
define('DATA_DIR', 'data');
defined('SITE_DIR') || define('SITE_DIR', '');
defined('CORE_DIR') || define('CORE_DIR', '/core');
defined('APP_VERSION') || define('APP_VERSION', 'test');
defined('RELEASE_TIME') || define('RELEASE_TIME', '0');
defined('M') || define('M', 'admin');
defined('C') || define('C', 'Config');

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/function/helper.php';
require CORE_PATH . '/function/file.php';
require CORE_PATH . '/basic/Config.php';
require CORE_PATH . '/basic/Basic.php';
require CORE_PATH . '/database/Builder.php';
spl_autoload_register('core\\basic\\Basic::autoLoad', true, true);
require dirname(__DIR__) . '/support/DatabaseTestSupport.php';
require APP_PATH . '/common/function.php';

$driver = isset($scenario['driver']) ? (string) $scenario['driver'] : 'sqlite';
$seed = isset($scenario['seed']) && is_array($scenario['seed']) ? $scenario['seed'] : array();
$ddl = 'CREATE TABLE ay_config (id INTEGER PRIMARY KEY %s, name VARCHAR(30) NOT NULL, value VARCHAR(200), type CHAR(1) NOT NULL DEFAULT \'2\', sorting INTEGER NOT NULL DEFAULT 255, description VARCHAR(30))';

if ($driver === 'mysqli') {
    $m = isset($scenario['mysql']) && is_array($scenario['mysql']) ? $scenario['mysql'] : array();
    $conn = @new mysqli($m['host'], $m['user'], $m['passwd'], $m['dbname'], (int) $m['port']);
    if ($conn->connect_errno) {
        echo "SNAPSHOT=null\n";
        exit(0);
    }
    $conn->set_charset('utf8');
    $conn->query('DROP TABLE IF EXISTS ay_config');
    $conn->query(sprintf($ddl, 'AUTO_INCREMENT'));
    $stmt = $conn->prepare('INSERT INTO ay_config (name, value) VALUES (?, ?)');
    foreach ($seed as $name => $value) {
        $value = (string) $value;
        $stmt->bind_param('ss', $name, $value);
        $stmt->execute();
    }
    $database = array('type' => 'mysqli', 'host' => $m['host'], 'user' => $m['user'], 'passwd' => $m['passwd'], 'port' => $m['port'], 'dbname' => $m['dbname']);
    $snapshot = function () use ($conn) {
        $rows = array();
        $rs = $conn->query('SELECT name, value FROM ay_config ORDER BY name');
        while ($row = $rs->fetch_assoc()) {
            $rows[$row['name']] = $row['value'];
        }
        return $rows;
    };
} else {
    $rel = '/data/ai_save_probe_' . getmypid() . '.db';
    $path = ROOT_PATH . ltrim($rel, '/');
    @unlink($path);
    $pdo = new PDO('sqlite:' . $path);
    $pdo->exec(sprintf($ddl, 'AUTOINCREMENT'));
    $stmt = $pdo->prepare('INSERT INTO ay_config (name, value) VALUES (?, ?)');
    foreach ($seed as $name => $value) {
        $stmt->execute(array($name, (string) $value));
    }
    $pdo = null;
    $database = array('type' => $driver, 'dbname' => $rel);
    $snapshot = function () use ($path) {
        $pdo = new PDO('sqlite:' . $path);
        $rows = array();
        foreach ($pdo->query('SELECT name, value FROM ay_config ORDER BY name') as $row) {
            $rows[$row['name']] = $row['value'];
        }
        $pdo = null;
        return $rows;
    };
}

$prop = db_config_reflection();
$prop->setValue(null, array_merge($seed, array('database' => $database, 'url_rule_type' => '1', 'app_url_type' => '1')));
db_reset_all_drivers();

register_shutdown_function(function () use ($snapshot, $tmp, $driver) {
    $out = '';
    while (ob_get_level() > 0) {
        $out = ob_get_clean() . $out;
    }
    foreach (array('core\\database\\Sqlite' => 'sqlite', 'core\\database\\Pdo' => 'pdo', 'core\\database\\Mysqli' => 'mysqli') as $class => $prop) {
        if (class_exists($class, false)) {
            $instance = db_reflect_static($class, $prop)->getValue();
            if (is_object($instance)) {
                $instance->commitTransaction();
            }
        }
    }
    echo 'OUTPUT=' . json_encode($out, JSON_UNESCAPED_UNICODE) . "\n";
    echo "SNAPSHOT=" . json_encode($snapshot(), JSON_UNESCAPED_UNICODE) . "\n";
    $GLOBALS['controller'] = null;
    db_reset_all_drivers();
    gc_collect_cycles();
    if ($driver !== 'mysqli') {
        foreach (glob(ROOT_PATH . 'data/ai_save_probe_' . getmypid() . '.db*') as $f) {
            @unlink($f);
        }
    }
    $rm = function ($p) use (&$rm) {
        if (is_dir($p)) {
            foreach (scandir($p) as $i) {
                if ($i !== '.' && $i !== '..') {
                    $rm($p . DIRECTORY_SEPARATOR . $i);
                }
            }
            @rmdir($p);
        } elseif (is_file($p)) {
            @unlink($p);
        }
    };
    if (strpos($tmp, 'pboot-ai-save-') !== false) {
        $rm(rtrim($tmp, DIRECTORY_SEPARATOR));
    }
});

$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_POST = isset($scenario['post']) && is_array($scenario['post']) ? $scenario['post'] : array();
ob_start();
$ref = new ReflectionClass(\app\admin\controller\system\ConfigController::class);
$controller = $ref->newInstance();
$controller->index();
