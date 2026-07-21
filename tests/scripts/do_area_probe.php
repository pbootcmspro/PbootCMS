<?php

declare(strict_types=1);

/**
 * 独立进程探测 DoController::area() 跳转与 cookie 行为（避免 location() exit 影响测试进程）
 *
 * 用法: php tests/scripts/do_area_probe.php <lg>
 * 输出 JSON: {"location":"...","cookie_lg":...}
 */

$lgArg = isset($argv[1]) ? $argv[1] : '';

define('ROOT_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('APP_PATH', ROOT_PATH . 'apps');
define('CORE_PATH', ROOT_PATH . 'core');
define('SITE_DIR', '/cms');
define('SITE_INDEX_DIR', '/cms');
define('DOC_PATH', ROOT_PATH);
define('RUN_PATH', ROOT_PATH . 'runtime');
define('CONF_PATH', ROOT_PATH . 'config');

$_GET = array();
$_POST = array();
$_COOKIE = array();
if ($lgArg !== '') {
    $_GET['lg'] = $lgArg;
}
$_SERVER['SCRIPT_NAME'] = '/cms/index.php';

function location($url)
{
    echo json_encode(array(
        'location' => $url,
        'cookie_lg' => isset($_COOKIE['lg']) ? $_COOKIE['lg'] : null,
    ));
    exit(0);
}

function filter($varname, $condition)
{
    $data = @$_GET[$varname];
    if (is_string($data)) {
        $data = trim($data);
    }
    if (! $data) {
        return null;
    }
    if (isset($condition['d_type']) && $condition['d_type'] === 'var') {
        if (! preg_match('/^[\w\-\.]+$/', $data)) {
            return null;
        }
    }
    return $data;
}

function request($name, $type = null, $require = false, $vartext = null, $default = null)
{
    $condition = array(
        'd_source' => 'get',
        'd_type' => $type,
        'd_require' => $require,
        $name => $vartext,
        'd_default' => $default,
    );
    return filter($name, $condition);
}

function cookie($name, $value = null, $expire = null, $path = null, $domain = null, $secure = null, $httponly = false)
{
    if ($value !== null) {
        $_COOKIE[$name] = $value;
        return true;
    }
    return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
}

require CORE_PATH . '/basic/Config.php';
require CORE_PATH . '/basic/Controller.php';
require APP_PATH . '/home/controller/DoController.php';

$ref = new ReflectionClass('core\basic\Config');
$prop = $ref->getProperty('configs');
if (PHP_VERSION_ID < 80100) {
    $prop->setAccessible(true);
}
$prop->setValue(null, array(
    'lgs' => array(
        'cn' => array('name' => '中文'),
        'en' => array('name' => 'English'),
    ),
));

$ctrlRef = new ReflectionClass('app\home\controller\DoController');
$ctrl = $ctrlRef->newInstanceWithoutConstructor();
$ctrlRef->getMethod('area')->invoke($ctrl);

echo json_encode(array('location' => null, 'cookie_lg' => null));
exit(1);
