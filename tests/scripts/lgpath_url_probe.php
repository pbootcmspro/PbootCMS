<?php

declare(strict_types=1);

/**
 * 子目录部署下 Url::get('home/Do/area') 探测脚本
 *
 * 用法: php tests/scripts/lgpath_url_probe.php <app_url_type>
 */

$appUrlType = isset($argv[1]) ? (int) $argv[1] : 1;

define('ROOT_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('APP_PATH', ROOT_PATH . 'apps');
define('CORE_PATH', ROOT_PATH . 'core');
define('SITE_DIR', '/cms');
define('SITE_INDEX_DIR', '/cms');
define('DOC_PATH', ROOT_PATH);
define('RUN_PATH', ROOT_PATH . 'runtime');
define('CONF_PATH', ROOT_PATH . 'config');

define('URL_BIND', 'home');
define('M', 'home');
$_SERVER['SCRIPT_NAME'] = '/cms/index.php';

require CORE_PATH . '/basic/Config.php';
require CORE_PATH . '/basic/Url.php';
require CORE_PATH . '/function/handle.php';

$ref = new ReflectionClass('core\basic\Config');
$prop = $ref->getProperty('configs');
if (PHP_VERSION_ID < 80100) {
    $prop->setAccessible(true);
}
$prop->setValue(null, array('app_url_type' => $appUrlType));

$url = \core\basic\Url::lgArea('en');
echo json_encode(array('url' => $url));
exit(0);
