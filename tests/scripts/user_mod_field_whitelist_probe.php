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
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

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

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/basic/Config.php';
require CORE_PATH . '/basic/Response.php';
require CORE_PATH . '/function/helper.php';
require CORE_PATH . '/basic/Controller.php';
require APP_PATH . '/admin/controller/system/UserController.php';

// AJAX 强制走 JSON 输出，避免依赖 error.html/success.html 模板
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

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

$_GET['ucode'] = '10002';

switch ($mode) {
    case 'field_password':
        $_POST['field'] = 'password';
        $_POST['value'] = md5(md5('attacker-password'));
        user_mod_probe_make_controller()->mod();
        break;
    case 'field_status':
        $_POST['field'] = 'status';
        $_POST['value'] = '1';
        user_mod_probe_make_controller()->mod();
        break;
    case 'field_status_get':
        $_GET['field'] = 'status';
        $_GET['value'] = '1';
        user_mod_probe_make_controller()->mod();
        break;
    default:
        fwrite(STDERR, "unknown mode: $mode\n");
        exit(2);
}
