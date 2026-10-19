<?php

/**
 * 子进程调用 Check::checkApp()。
 * 正常环境输出 OK；缺失项由 error() 写入标准输出后退出。
 */

$root = str_replace('\\', '/', dirname(__DIR__, 2));
$_SERVER['SCRIPT_NAME'] = '/admin.php';
$_SERVER['REQUEST_URI'] = '/admin.php';

define('START_TIME', microtime(true));
define('SITE_DIR', '');
define('ROOT_PATH', $root);
define('DOC_PATH', $root);
define('CORE_PATH', $root . '/core');
define('APP_PATH', $root . '/apps');
define('RUN_PATH', $root . '/runtime');
define('CONF_PATH', $root . '/config');
define('STATIC_DIR', '/static');
define('DATA_DIR', '/data');
define('CORE_DIR', '/core');
define('APP_VERSION', 'test');
define('RELEASE_TIME', 'test');

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/function/helper.php';
require CORE_PATH . '/function/file.php';
require CORE_PATH . '/basic/Basic.php';
spl_autoload_register('core\\basic\\Basic::autoLoad', true, true);

\core\basic\Check::checkApp();
echo "OK\n";
