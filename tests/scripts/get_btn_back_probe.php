<?php

declare(strict_types=1);

/**
 * get_btn_back() href 输出独立进程探测
 *
 * 用法: php tests/scripts/get_btn_back_probe.php <mode>
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
define('M', 'admin');
define('C', 'Content');

$_SERVER['HTTP_HOST'] = 'site.test';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['HTTPS'] = 'off';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require CORE_PATH . '/basic/Config.php';
require CORE_PATH . '/basic/Url.php';
require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/function/helper.php';
require APP_PATH . '/common/function.php';

$ref = new ReflectionClass('core\basic\Config');
$prop = $ref->getProperty('configs');
if (PHP_VERSION_ID < 80100) {
    $prop->setAccessible(true);
}
$prop->setValue(null, array('app_url_type' => 1));

switch ($mode) {
    case 'entity_javascript':
        $_GET['backurl'] = base64_encode('javascript&#58;alert(1)');
        break;
    case 'entity_triple_javascript':
        $_GET['backurl'] = base64_encode('javascript&amp;amp;#58;alert(1)');
        break;
    case 'safe_query':
        $_GET['backurl'] = base64_encode('/search?a=1&b=2');
        break;
    case 'entity_space_protocol_relative':
        $_GET['backurl'] = base64_encode('&#32;//evil.test/path');
        break;
    case 'entity_hex_space_protocol_relative':
        $_GET['backurl'] = base64_encode('&#x20;//evil.test/path');
        break;
    default:
        fwrite(STDERR, "unknown mode: $mode\n");
        exit(2);
}

echo get_btn_back();
exit(0);
