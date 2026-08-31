<?php

declare(strict_types=1);

/**
 * Request::validate() 独立进程探测
 *
 * 用法: php tests/scripts/request_page_validate_probe.php <base64(QUERY_STRING)>
 * 合法 query 输出 HTTP_STATUS=200 与 OK；非法输出 HTTP_STATUS=400 与 Bad Request
 */

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

$encoded = isset($argv[1]) ? (string) $argv[1] : '';
$qs = $encoded !== '' ? base64_decode($encoded, true) : '';
if ($qs === false) {
    $qs = '';
}

$sourcePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
$sourcePath = realpath($sourcePath) . DIRECTORY_SEPARATOR;
define('ROOT_PATH', $sourcePath);
define('APP_PATH', $sourcePath . 'apps');
define('CORE_PATH', $sourcePath . 'core');
defined('SITE_DIR') || define('SITE_DIR', '');

require CORE_PATH . '/basic/Request.php';

$_SERVER['QUERY_STRING'] = $qs;

register_shutdown_function(static function () {
    $status = http_response_code();
    echo 'HTTP_STATUS=' . ($status ? (string) $status : '200') . "\n";
});

\core\basic\Request::validate();
echo "OK\n";
