<?php

declare(strict_types=1);

/**
 * MemberController::mod() 快捷改值字段白名单独立进程探测
 *
 * 用法: php tests/scripts/admin_member_quick_mod_probe.php <mode>
 *
 * mode:
 *   field_password / field_gid / field_score / value_invalid / status_0 / status_1
 *   id_array / field_array / value_array / value_array_without_field
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
require APP_PATH . '/admin/controller/member/MemberController.php';

$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

class AdminMemberQuickModProbeModelDouble
{
    public function modMemberStatus($id, $status)
    {
        echo 'STATUS_CALLED:' . $id . ':' . $status . "\n";
        return true;
    }

    public function modMember($id, $data)
    {
        echo 'MOD_CALLED:' . $id . ':' . (is_array($data) ? json_encode($data) : $data) . "\n";
        return true;
    }
}

function admin_member_quick_mod_make_controller()
{
    $refClass = new ReflectionClass(\app\admin\controller\member\MemberController::class);
    $controller = $refClass->newInstanceWithoutConstructor();
    $modelProperty = $refClass->getProperty('model');
    if (PHP_VERSION_ID < 80100) {
        $modelProperty->setAccessible(true);
    }
    $modelProperty->setValue($controller, new AdminMemberQuickModProbeModelDouble());
    return $controller;
}

$_GET['id'] = '12';

switch ($mode) {
    case 'field_password':
        $_GET['field'] = 'password';
        $_GET['value'] = '1';
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'field_gid':
        $_GET['field'] = 'gid';
        $_GET['value'] = '1';
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'field_score':
        $_GET['field'] = 'score';
        $_GET['value'] = '1';
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'value_invalid':
        $_GET['field'] = 'status';
        $_GET['value'] = '2';
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'id_array':
        parse_str('id[]=12&field=status&value=1', $_GET);
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'field_array':
        parse_str('id=12&field[]=status&value=1', $_GET);
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'value_array':
        parse_str('id=12&field=status&value[]=1', $_GET);
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'value_array_without_field':
        parse_str('id=12&value[]=1', $_GET);
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'status_0':
        $_GET['field'] = 'status';
        $_GET['value'] = '0';
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'status_1':
        $_GET['field'] = 'status';
        $_GET['value'] = '1';
        admin_member_quick_mod_make_controller()->mod();
        break;
    default:
        fwrite(STDERR, "unknown mode: $mode\n");
        exit(2);
}
