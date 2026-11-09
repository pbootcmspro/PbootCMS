<?php

declare(strict_types=1);

/**
 * UserController::mod() 快捷改值字段白名单 / POST 约束独立进程探测
 *
 * 用法: php tests/scripts/user_mod_field_whitelist_probe.php <mode>
 *
 * mode:
 *   field_password        POST field=password → 必须拒绝，不得调用 modUser()
 *   field_status          POST field=status   → 允许，调用 modUser()
 *   field_status_get      裸 GET field=status  → 必须拒绝，不得调用 modUser()
 */

ini_set('display_errors', '0');
$errorLevel = E_ALL & ~E_DEPRECATED;
if (defined('E_STRICT')) {
    $errorLevel = $errorLevel & ~ constant('E_STRICT');
}
error_reporting($errorLevel);

$mode = isset($argv[1]) ? (string) $argv[1] : '';
if ($mode === '') {
    fwrite(STDERR, "missing mode\n");
    exit(2);
}

$sourcePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
$sourcePath = realpath($sourcePath) . DIRECTORY_SEPARATOR;
define('ROOT_PATH', $sourcePath);
define('APP_PATH', $sourcePath . 'apps');
define('CORE_PATH', $sourcePath . 'core');
define('CONF_PATH', $sourcePath . 'config');
define('RUN_PATH', $sourcePath . 'runtime');
defined('SITE_DIR') || define('SITE_DIR', '');
defined('CORE_DIR') || define('CORE_DIR', '/core');
defined('APP_VERSION') || define('APP_VERSION', 'test');
defined('RELEASE_TIME') || define('RELEASE_TIME', '0');
defined('M') || define('M', 'admin');
defined('C') || define('C', 'User');
defined('URL') || define('URL', '/admin.php?p=/User/index');

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/basic/Config.php';
require CORE_PATH . '/basic/Response.php';

eval('namespace core\\basic; class Url { public static function get($path, $suffix = null) { $path = trim((string) $path, "/"); if (strpos($path, "admin/") === 0) { $path = substr($path, 6); } return "/admin.php?p=/" . $path; } }');

require CORE_PATH . '/function/helper.php';
require CORE_PATH . '/basic/Controller.php';
require APP_PATH . '/common/function.php';
require APP_PATH . '/admin/controller/system/UserController.php';

$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_SERVER['HTTP_HOST'] = 'dev.pbootcms.lan';
$_SERVER['REQUEST_SCHEME'] = 'http';
$_SERVER['SERVER_PORT'] = '80';

class UserModProbeModelDouble
{
    public function modUser($ucode, $data)
    {
        echo 'MOD_CALLED:' . $ucode . ':' . $data . "\n";
        return true;
    }
}

function user_mod_probe_make_controller()
{
    $refClass = new ReflectionClass(\app\admin\controller\system\UserController::class);
    $controller = $refClass->newInstanceWithoutConstructor();
    $modelProperty = $refClass->getProperty('model');
    if (PHP_VERSION_ID < 80100) {
        $modelProperty->setAccessible(true);
    }
    $modelProperty->setValue($controller, new UserModProbeModelDouble());
    return $controller;
}

function user_mod_probe_post($field, $value)
{
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET = array('ucode' => '10002');
    $_POST = array(
        'formcheck' => 'probe',
        'field' => $field,
        'value' => $value
    );
}

switch ($mode) {
    case 'field_password':
        user_mod_probe_post('password', md5(md5('attacker-password')));
        user_mod_probe_make_controller()->mod();
        break;
    case 'field_status':
        user_mod_probe_post('status', '1');
        user_mod_probe_make_controller()->mod();
        break;
    case 'field_status_get':
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = array('ucode' => '10002', 'field' => 'status', 'value' => '1');
        $_POST = array();
        user_mod_probe_make_controller()->mod();
        break;
    default:
        fwrite(STDERR, "unknown mode: $mode\n");
        exit(2);
}
