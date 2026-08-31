<?php

declare(strict_types=1);

/**
 * get_server_info() / get_server_soft() 缺键独立进程探测
 *
 * 用法: php tests/scripts/get_server_info_probe.php
 * 输出 JSON: {"ok":bool,"warnings":string[],"server_addr":string,"server_software":string,"session_save_path":string,"soft":string}
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

$sourcePath = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
$sourcePath = realpath($sourcePath) . DIRECTORY_SEPARATOR;
define('ROOT_PATH', $sourcePath);
define('APP_PATH', $sourcePath . 'apps');
define('CORE_PATH', $sourcePath . 'core');
define('CONF_PATH', $sourcePath . 'config');
define('RUN_PATH', $sourcePath . 'runtime');
defined('SITE_DIR') || define('SITE_DIR', '');
defined('DOC_PATH') || define('DOC_PATH', ROOT_PATH);

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/basic/Config.php';

$warnings = array();
set_error_handler(static function ($errno, $errstr) use (&$warnings) {
    // 尊重 @：被抑制的诊断（如 Config::ob_clean）不记入，其余全部记录以免掩盖回归
    if (!(error_reporting() & $errno)) {
        return true;
    }
    $warnings[] = '[' . $errno . '] ' . $errstr;
    return true;
});

$unsetKeys = array(
    'HTTP_HOST',
    'SERVER_NAME',
    'SERVER_PORT',
    'SERVER_SOFTWARE',
    'TMP',
    'SERVER_ADDR',
    'LOCAL_ADDR',
);
foreach ($unsetKeys as $key) {
    unset($_SERVER[$key]);
}

$server = get_server_info();
$soft = get_server_soft();

restore_error_handler();

echo json_encode(array(
    'ok' => count($warnings) === 0,
    'warnings' => $warnings,
    'server_addr' => isset($server->server_addr) ? (string) $server->server_addr : '',
    'server_software' => isset($server->server_software) ? (string) $server->server_software : '',
    'session_save_path' => isset($server->session_save_path) ? (string) $server->session_save_path : '',
    'soft' => $soft,
), JSON_UNESCAPED_UNICODE) . "\n";
