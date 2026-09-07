<?php

declare(strict_types=1);

/**
 * UserController::del() / mod() 裸 GET 写操作拦截探测
 *
 * 用法: php tests/scripts/user_del_mod_csrf_probe.php <mode>
 *
 * mode:
 *   del_get           裸 GET del → 拒绝，不得调用 delUser()
 *   del_post          POST del   → 允许，调用 delUser()
 *   status_get        裸 GET mod field=status → 拒绝，不得调用 modUser()
 *   status_post       POST mod field=status → 允许，调用 modUser()
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

$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

class UserCsrfProbeModelDouble
{
    public function delUser($ucode)
    {
        echo 'DEL_CALLED:' . $ucode . "\n";
        return true;
    }

    public function modUser($ucode, $data)
    {
        echo 'MOD_CALLED:' . $ucode . ':' . $data . "\n";
        return true;
    }

    public function getUser($ucode)
    {
        return null;
    }
}

function user_csrf_probe_make_controller()
{
    $refClass = new ReflectionClass(\app\admin\controller\system\UserController::class);
    $controller = $refClass->newInstanceWithoutConstructor();
    $modelProperty = $refClass->getProperty('model');
    if (PHP_VERSION_ID < 80100) {
        $modelProperty->setAccessible(true);
    }
    $modelProperty->setValue($controller, new UserCsrfProbeModelDouble());
    return $controller;
}

$_GET['ucode'] = '10002';

switch ($mode) {
    case 'del_get':
        // 无 $_POST
        user_csrf_probe_make_controller()->del();
        break;
    case 'del_post':
        $_POST['formcheck'] = 'probe-token';
        user_csrf_probe_make_controller()->del();
        break;
    case 'status_get':
        $_GET['field'] = 'status';
        $_GET['value'] = '0';
        user_csrf_probe_make_controller()->mod();
        break;
    case 'status_post':
        $_POST['field'] = 'status';
        $_POST['value'] = '0';
        user_csrf_probe_make_controller()->mod();
        break;
    default:
        fwrite(STDERR, "unknown mode: $mode\n");
        exit(2);
}
