<?php

declare(strict_types=1);

/**
 * MemberController::mod() 快捷改值字段白名单独立进程探测
 *
 * 用法: php tests/scripts/admin_member_quick_mod_probe.php <mode>
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
defined('C') || define('C', 'Member');
defined('URL') || define('URL', '/admin.php?p=/Member/index');

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/basic/Config.php';
require CORE_PATH . '/basic/Response.php';

eval('namespace core\\basic; class Url { public static function get($path, $suffix = null) { $path = trim((string) $path, "/"); if (strpos($path, "admin/") === 0) { $path = substr($path, 6); } return "/admin.php?p=/" . $path; } }');

require CORE_PATH . '/function/helper.php';
require CORE_PATH . '/basic/Controller.php';
require APP_PATH . '/common/function.php';
require APP_PATH . '/admin/controller/member/MemberController.php';

$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_SERVER['HTTP_HOST'] = 'dev.pbootcms.lan';
$_SERVER['REQUEST_SCHEME'] = 'http';
$_SERVER['SERVER_PORT'] = '80';

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

function admin_member_quick_mod_post($field, $value)
{
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET = array('id' => '12');
    $_POST = array(
        'formcheck' => 'probe',
        'field' => $field,
        'value' => $value
    );
}

switch ($mode) {
    case 'field_password':
        admin_member_quick_mod_post('password', '1');
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'field_gid':
        admin_member_quick_mod_post('gid', '1');
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'field_score':
        admin_member_quick_mod_post('score', '1');
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'value_invalid':
        admin_member_quick_mod_post('status', '2');
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'id_array':
        $_SERVER['REQUEST_METHOD'] = 'POST';
        parse_str('id[]=12', $_GET);
        $_POST = array('formcheck' => 'probe', 'field' => 'status', 'value' => '1');
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'field_array':
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = array('id' => '12');
        parse_str('formcheck=probe&field[]=status&value=1', $_POST);
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'value_array':
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = array('id' => '12');
        parse_str('formcheck=probe&field=status&value[]=1', $_POST);
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'value_array_without_field':
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = array('id' => '12');
        parse_str('formcheck=probe&value[]=1', $_POST);
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'get_status':
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = array('id' => '12', 'field' => 'status', 'value' => '0');
        $_POST = array();
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'status_0':
        admin_member_quick_mod_post('status', '0');
        admin_member_quick_mod_make_controller()->mod();
        break;
    case 'status_1':
        admin_member_quick_mod_post('status', '1');
        admin_member_quick_mod_make_controller()->mod();
        break;
    default:
        fwrite(STDERR, "unknown mode: $mode\n");
        exit(2);
}
