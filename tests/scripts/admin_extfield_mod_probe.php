<?php

declare(strict_types=1);

/**
 * ExtFieldController::mod() 表单提交独立进程探测
 *
 * 用法: php tests/scripts/admin_extfield_mod_probe.php <mode>
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
defined('C') || define('C', 'ExtField');
defined('URL') || define('URL', '/admin.php?p=/ExtField/index');

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/basic/Config.php';
require CORE_PATH . '/basic/Response.php';

eval('namespace core\\basic; class Url { public static function get($path, $suffix = null) { $path = trim((string) $path, "/"); if (strpos($path, "admin/") === 0) { $path = substr($path, 6); } return "/admin.php?p=/" . $path; } }');
eval('namespace core\\basic; class Log { public static function write($content, $level = "info") {} }');

require CORE_PATH . '/function/helper.php';
require CORE_PATH . '/basic/Controller.php';
require APP_PATH . '/common/function.php';
require APP_PATH . '/admin/controller/content/ExtFieldController.php';

$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_SERVER['HTTP_HOST'] = 'dev.pbootcms.lan';
$_SERVER['REQUEST_SCHEME'] = 'http';
$_SERVER['SERVER_PORT'] = '80';

class AdminExtFieldModProbeModelDouble
{
    public function modExtField($id, $data)
    {
        echo 'MOD_CALLED:' . $id . ':' . (is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data) . "\n";
        return true;
    }

    public function getExtField($id)
    {
        echo 'GET_CALLED:' . $id . "\n";
        return (object) array(
            'id' => $id,
            'mcode' => '3',
            'name' => 'ext_type',
            'type' => '4',
            'value' => '基础版,专业版',
            'description' => '类型',
            'sorting' => 255
        );
    }
}

function admin_extfield_mod_make_controller()
{
    $refClass = new ReflectionClass(\app\admin\controller\content\ExtFieldController::class);
    $controller = $refClass->newInstanceWithoutConstructor();
    $modelProperty = $refClass->getProperty('model');
    if (PHP_VERSION_ID < 80100) {
        $modelProperty->setAccessible(true);
    }
    $modelProperty->setValue($controller, new AdminExtFieldModProbeModelDouble());
    return $controller;
}

function admin_extfield_mod_form_post(array $extra = array())
{
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET = array('id' => '2');
    $_POST = array_merge(array(
        'formcheck' => 'probe',
        'mcode' => '3',
        'type' => '4',
        'value' => '基础版,专业版,旗舰版',
        'description' => '类型',
        'sorting' => '255'
    ), $extra);
}

switch ($mode) {
    case 'form_value_without_field':
        admin_extfield_mod_form_post();
        admin_extfield_mod_make_controller()->mod();
        break;
    case 'form_empty_value_without_field':
        admin_extfield_mod_form_post(array('value' => ''));
        admin_extfield_mod_make_controller()->mod();
        break;
    case 'quick_illegal_field':
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = array('id' => '2');
        $_POST = array(
            'formcheck' => 'probe',
            'field' => 'name',
            'value' => '1'
        );
        admin_extfield_mod_make_controller()->mod();
        break;
    default:
        fwrite(STDERR, "unknown mode: $mode\n");
        exit(2);
}
