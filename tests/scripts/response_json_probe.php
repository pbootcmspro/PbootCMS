<?php

declare(strict_types=1);

/**
 * Response::json 子进程探测
 *
 * 用法: php tests/scripts/response_json_probe.php <base64(serialize([code, data]))>
 */

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

$payload = isset($argv[1]) ? (string) $argv[1] : '';
if ($payload === '') {
    fwrite(STDERR, "missing payload\n");
    exit(2);
}

$decoded = @unserialize(base64_decode($payload, true));
if (! is_array($decoded) || count($decoded) < 2) {
    fwrite(STDERR, "bad payload\n");
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

require CORE_PATH . '/basic/Config.php';
require CORE_PATH . '/basic/Response.php';

\core\basic\Response::json($decoded[0], $decoded[1]);
