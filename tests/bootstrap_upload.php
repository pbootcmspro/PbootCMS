<?php

declare(strict_types=1);

if (! defined('TEST_ROOT')) {
    define('TEST_ROOT', dirname(__FILE__) . DIRECTORY_SEPARATOR);
}
if (! defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(TEST_ROOT) . DIRECTORY_SEPARATOR);
}
if (! defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . 'apps');
}
if (! defined('CORE_PATH')) {
    define('CORE_PATH', ROOT_PATH . 'core');
}
defined('CONF_PATH') ?: define('CONF_PATH', ROOT_PATH . 'config');
defined('RUN_PATH') ?: define('RUN_PATH', ROOT_PATH . 'runtime');
defined('DOC_PATH') ?: define('DOC_PATH', ROOT_PATH);
defined('STATIC_DIR') ?: define('STATIC_DIR', '/static');

if (! function_exists('get_user_bs')) {
    require CORE_PATH . '/function/handle.php';
}
if (! class_exists('core\\basic\\Config', false)) {
    require CORE_PATH . '/basic/Config.php';
}
if (! function_exists('gd_can_post_process_image')) {
    require CORE_PATH . '/function/file.php';
}

if (! class_exists('TestAssert', false)) {
    require TEST_ROOT . 'support/Assert.php';
}
