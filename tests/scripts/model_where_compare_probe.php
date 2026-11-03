<?php

declare(strict_types=1);

/**
 * Model::whereCompare() 非法参数独立进程探测
 *
 * 用法: php tests/scripts/model_where_compare_probe.php <mode>
 *
 * mode:
 *   bad_operator / bad_field_func / bad_field_semi / bad_value_array / bad_connect
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
require CORE_PATH . '/basic/Model.php';

$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

$model = new \core\basic\Model();

switch ($mode) {
    case 'bad_operator':
        $model->whereCompare('id', 'LIKE', 1);
        break;
    case 'bad_field_func':
        $model->whereCompare('RAND()', '=', 1);
        break;
    case 'bad_field_semi':
        $model->whereCompare('id;', '=', 1);
        break;
    case 'bad_value_array':
        $model->whereCompare('id', '<>', array(1));
        break;
    case 'bad_connect':
        $model->whereCompare('id', '=', 1, 'XOR');
        break;
    default:
        fwrite(STDERR, "unknown mode: $mode\n");
        exit(2);
}

echo "UNEXPECTED_OK\n";
