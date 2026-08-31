<?php

declare(strict_types=1);

/**
 * 子进程探测：debug 级 error_reporting 下 curl_safe_close 不触发 E_DEPRECATED
 */

ini_set('display_errors', '0');
// 与 core/init.php debug 模式一致（含 E_DEPRECATED）
error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);

$sourcePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
$sourcePath = realpath($sourcePath) . DIRECTORY_SEPARATOR;
define('ROOT_PATH', $sourcePath);
define('APP_PATH', $sourcePath . 'apps');
define('CORE_PATH', $sourcePath . 'core');

require CORE_PATH . '/function/handle.php';

$deprecations = 0;
set_error_handler(static function ($errno) use (&$deprecations) {
    if ($errno === E_DEPRECATED) {
        $deprecations++;
    }
    return true;
});

if (! function_exists('curl_init')) {
    echo "DEPRECATIONS=0\n";
    echo "SKIP=1\n";
    exit(0);
}

$ch = curl_init();
if ($ch === false) {
    fwrite(STDERR, "curl_init failed\n");
    exit(1);
}
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 1);
@curl_exec($ch);
curl_safe_close($ch);

restore_error_handler();
echo "DEPRECATIONS=$deprecations\n";
exit(0);
