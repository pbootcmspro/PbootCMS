<?php

declare(strict_types=1);

/**
 * error() / _404() HTTP 状态码独立进程探测
 *
 * 用法: php tests/scripts/error_http_status_probe.php <mode>
 *
 * mode:
 *   error_default        默认 HTML error() → 404
 *   error_custom         error(..., 403) → 403
 *   error_ajax_default   AJAX 默认 error() → 200 + JSON code:0
 *   error_api_json_default  API return_data_type=json 非 XHR → 404 + JSON
 *   error_ajax_403       AJAX + 显式 403 → 403
 *   error_ajax_404       AJAX + 显式 404 → 404（_404 回落语义）
 *   _404_fallback        _404() 无 404.html 时 → 404
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

register_shutdown_function(static function () {
    echo 'HTTP_STATUS=' . http_response_code() . "\n";
});

switch ($mode) {
    case 'error_default':
        error('probe default status', null, 0);
        break;
    case 'error_custom':
        error('probe custom status', null, 0, 403);
        break;
    case 'error_ajax_default':
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        error('您的账号权限不足，您无法执行该操作！', null, 0);
        break;
    case 'error_api_json_default':
        $ref = new ReflectionClass(\core\basic\Config::class);
        $prop = $ref->getProperty('configs');
        if (PHP_VERSION_ID < 80100) {
            $prop->setAccessible(true);
        }
        $prop->setValue(null, array('return_data_type' => 'json'));
        error('API 模块业务错误', null, 0);
        break;
    case 'error_ajax_403':
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        error('probe ajax explicit 403', null, 0, 403);
        break;
    case 'error_ajax_404':
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        error('probe ajax explicit 404', null, 0, 404);
        break;
    case '_404_fallback':
        _404('probe not found', null, 0);
        break;
    default:
        fwrite(STDERR, "unknown mode: $mode\n");
        exit(2);
}
