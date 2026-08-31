<?php

declare(strict_types=1);

/**
 * alert_location / success JSON tourl 独立进程探测
 *
 * 用法: php tests/scripts/safe_redirect_probe.php <mode>
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

$_SERVER['HTTP_HOST'] = 'site.test';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['HTTPS'] = 'off';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

switch ($mode) {
    case 'alert_external':
        alert_location('ok', 'https://evil.test/phish', 1);
        break;
    case 'alert_javascript':
        alert_location('ok', 'javascript:alert(1)', 1);
        break;
    case 'success_base64_external':
        success('ok', base64_decode(base64_encode('https://evil.test/admin')));
        break;
    case 'error_referer_spoof':
        $_SERVER['HTTP_REFERER'] = 'http://site.test.evil.test/page';
        error('denied', '-1', 0);
        break;
    default:
        fwrite(STDERR, "unknown mode: $mode\n");
        exit(2);
}
