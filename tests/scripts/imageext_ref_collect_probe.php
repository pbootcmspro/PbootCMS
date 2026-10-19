<?php

declare(strict_types=1);

/**
 * ImageExt 冗余集合探测脚本：临时站点目录 + 临时 SQLite 库，执行真实 buildRedundantMap()
 *
 * 用法: php tests/scripts/imageext_ref_collect_probe.php <site_dir> <base64_json_payload>
 * payload: doc_path（临时站点物理根）、db_rel（相对 ROOT_PATH 的库路径）
 */

$siteDir = isset($argv[1]) ? (string) $argv[1] : '';
$payloadRaw = isset($argv[2]) ? (string) $argv[2] : '';
$payloadJson = $payloadRaw !== '' ? base64_decode($payloadRaw, true) : '';
$payload = is_string($payloadJson) && $payloadJson !== '' ? json_decode($payloadJson, true) : array();
if (!is_array($payload) || empty($payload['doc_path']) || empty($payload['db_rel'])) {
    fwrite(STDERR, "invalid payload\n");
    exit(2);
}

define('ROOT_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('APP_PATH', ROOT_PATH . 'apps');
define('CORE_PATH', ROOT_PATH . 'core');
define('SITE_DIR', $siteDir);
define('STATIC_DIR', SITE_DIR . '/static');
define('DOC_PATH', (string) $payload['doc_path']);
define('RUN_PATH', ROOT_PATH . 'runtime');
define('CONF_PATH', ROOT_PATH . 'config');

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/function/helper.php';
require CORE_PATH . '/function/file.php';
require CORE_PATH . '/basic/Basic.php';
spl_autoload_register('core\basic\Basic::autoLoad', true, true);

\core\basic\Config::get('database.type');
$ref = new ReflectionClass('core\\basic\\Config');
$prop = $ref->getProperty('configs');
if (PHP_VERSION_ID < 80100) {
    $prop->setAccessible(true);
}
$configs = $prop->getValue();
if (!is_array($configs)) {
    $configs = array();
}
$configs['database'] = array('type' => 'sqlite', 'dbname' => (string) $payload['db_rel']);
$prop->setValue(null, $configs);

$ctrlRef = new ReflectionClass(\app\admin\controller\system\ImageExtController::class);
$ctrl = $ctrlRef->newInstance();
$method = $ctrlRef->getMethod('buildRedundantMap');
if (PHP_VERSION_ID < 80100) {
    $method->setAccessible(true);
}
$result = array_keys($method->invoke($ctrl));

echo json_encode(array('result' => $result), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit(0);
