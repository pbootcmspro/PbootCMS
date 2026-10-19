<?php

declare(strict_types=1);

/**
 * ImageExt 路径规范化与边界校验探测脚本
 *
 * 用法: php tests/scripts/imageext_path_probe.php <site_dir> <action> <json_payload>
 * site_dir: '' | '/cms' | '/a/b'
 * action: normalize | fstkey | resolve | redundant | cleanup_all
 */

$siteDir = isset($argv[1]) ? (string) $argv[1] : '';
$action = isset($argv[2]) ? (string) $argv[2] : 'normalize';
$payloadRaw = isset($argv[3]) ? (string) $argv[3] : '';
$payloadJson = $payloadRaw !== '' ? base64_decode($payloadRaw, true) : '';
$payload = is_string($payloadJson) && $payloadJson !== '' ? json_decode($payloadJson, true) : array();
if (!is_array($payload)) {
    $payload = array();
}

define('ROOT_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('APP_PATH', ROOT_PATH . 'apps');
define('CORE_PATH', ROOT_PATH . 'core');
define('SITE_DIR', $siteDir);
define('STATIC_DIR', SITE_DIR . '/static');
define('DOC_PATH', isset($payload['doc_path']) ? (string) $payload['doc_path'] : ROOT_PATH);
define('RUN_PATH', ROOT_PATH . 'runtime');
define('CONF_PATH', ROOT_PATH . 'config');

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/function/helper.php';
require CORE_PATH . '/function/file.php';
require CORE_PATH . '/basic/Basic.php';
spl_autoload_register('core\basic\Basic::autoLoad', true, true);

$ref = new ReflectionClass(\app\admin\controller\system\ImageExtController::class);
$ctrl = $ref->newInstanceWithoutConstructor();

function imageext_stub_models(ReflectionClass $ref, $ctrl)
{
    $stub = new class {
        public function getImage()
        {
            return array();
        }
    };
    $props = array(
        'companyModel',
        'contentSortModel',
        'contentModel',
        'linkModel',
        'memberModel',
        'siteModel',
        'slideModel',
        'LabelModel',
    );
    foreach ($props as $name) {
        $prop = $ref->getProperty($name);
        if (PHP_VERSION_ID < 80100) {
            $prop->setAccessible(true);
        }
        $prop->setValue($ctrl, $stub);
    }
}

function imageext_private_method(ReflectionClass $ref, $name)
{
    $method = $ref->getMethod($name);
    if (PHP_VERSION_ID < 80100) {
        $method->setAccessible(true);
    }
    return $method;
}

switch ($action) {
    case 'normalize':
        $method = $ref->getMethod('normalizeUploadKey');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
        $result = $method->invoke($ctrl, isset($payload['path']) ? (string) $payload['path'] : '');
        break;

    case 'fstkey':
        $method = $ref->getMethod('fsToKey');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
        $staticSeg = substr(STATIC_DIR, strlen(SITE_DIR));
        $fsBase = str_replace('\\', '/', DOC_PATH . STATIC_DIR . '/upload');
        $result = $method->invoke(
            $ctrl,
            isset($payload['fs_path']) ? (string) $payload['fs_path'] : '',
            $fsBase,
            $staticSeg
        );
        break;

    case 'resolve':
        $uploadReal = realpath(DOC_PATH . STATIC_DIR . '/upload');
        $method = $ref->getMethod('resolveInsideUpload');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
        $candidate = isset($payload['path']) ? (string) $payload['path'] : '';
        $resolved = $method->invoke($ctrl, $candidate, $uploadReal);
        $result = array(
            'ok' => $resolved !== false,
            'resolved' => $resolved === false ? '' : str_replace('\\', '/', $resolved),
        );
        break;

    case 'redundant':
        imageext_stub_models($ref, $ctrl);
        $map = imageext_private_method($ref, 'buildRedundantMap')->invoke($ctrl);
        $result = array_keys($map);
        break;

    case 'cleanup_all':
        imageext_stub_models($ref, $ctrl);
        $map = imageext_private_method($ref, 'buildRedundantMap')->invoke($ctrl);
        $uploadReal = imageext_private_method($ref, 'getUploadReal')->invoke($ctrl);
        $backupBase = DOC_PATH . STATIC_DIR . '/backup/ImageExt';
        $move = imageext_private_method($ref, 'moveToBackup');
        $moved = array();
        foreach ($map as $key => $fsPath) {
            if ($move->invoke($ctrl, $fsPath, $key, $uploadReal, $backupBase)) {
                $moved[] = $key;
            }
        }
        $result = array('moved' => $moved);
        break;

    default:
        fwrite(STDERR, "unknown action: $action\n");
        exit(2);
}

echo json_encode(array('result' => $result), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit(0);
