<?php

declare(strict_types=1);

/**
 * 通过真实前台入口验证非法 page 请求不会写入 HTML 缓存
 *
 * 用法: php tests/scripts/request_page_cache_probe.php <base64(QUERY_STRING)>
 */

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

$encoded = isset($argv[1]) ? (string) $argv[1] : '';
$queryString = $encoded !== '' ? base64_decode($encoded, true) : '';
if ($queryString === false) {
    $queryString = '';
}

$sourcePath = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . '..');
if ($sourcePath === false) {
    fwrite(STDERR, "source path not found\n");
    exit(2);
}
$sourcePath .= DIRECTORY_SEPARATOR;
$cachePath = $sourcePath . 'runtime' . DIRECTORY_SEPARATOR . 'cache';

function request_page_cache_files($cachePath)
{
    if (! is_dir($cachePath)) {
        return array();
    }
    $files = glob($cachePath . DIRECTORY_SEPARATOR . '*.html');
    if ($files === false) {
        return array();
    }
    $names = array_map('basename', $files);
    sort($names);
    return $names;
}

$before = request_page_cache_files($cachePath);

$_SERVER = array(
    'QUERY_STRING' => $queryString,
    'REQUEST_URI' => '/?' . $queryString,
    'REQUEST_METHOD' => 'GET',
    'HTTP_HOST' => 'localhost',
    'SERVER_NAME' => 'localhost',
    'SERVER_PORT' => '80',
    'REMOTE_ADDR' => '127.0.0.1',
    'SCRIPT_NAME' => '/index.php',
    'PHP_SELF' => '/index.php',
    'SCRIPT_FILENAME' => $sourcePath . 'index.php',
    'SERVER_SOFTWARE' => 'cli-probe',
);
parse_str($queryString, $_GET);
$_POST = array();
$_COOKIE = array();

register_shutdown_function(function () use ($before, $cachePath) {
    $after = request_page_cache_files($cachePath);
    $result = array(
        'status' => http_response_code() ?: 200,
        'new_cache_files' => array_values(array_diff($after, $before)),
    );
    echo "\nPROBE_RESULT=" . base64_encode(json_encode($result)) . "\n";
});

chdir($sourcePath);
require $sourcePath . 'index.php';
