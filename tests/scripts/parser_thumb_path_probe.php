<?php

declare(strict_types=1);

/**
 * 二级目录缩略图路径探测脚本
 *
 * 用法: php tests/scripts/parser_thumb_path_probe.php <site_dir> <base64_json_payload>
 * site_dir: '' | '/cms' | '/a/b'
 * payload: { path, params, cleanup? }
 */

$siteDir = isset($argv[1]) ? (string) $argv[1] : '';
$payloadRaw = isset($argv[2]) ? (string) $argv[2] : '';
$payloadJson = $payloadRaw !== '' ? base64_decode($payloadRaw, true) : '';
$payload = is_string($payloadJson) && $payloadJson !== '' ? json_decode($payloadJson, true) : array();
if (! is_array($payload)) {
    $payload = array();
}

$path = isset($payload['path']) ? (string) $payload['path'] : '/static/upload/image/probe_thumb_13.jpg';
$params = isset($payload['params']) && is_array($payload['params']) ? $payload['params'] : array('width' => 40, 'height' => 30);
$cleanup = ! isset($payload['cleanup']) || $payload['cleanup'];
$cdnBase = isset($payload['cdn']) ? (string) $payload['cdn'] : '';

define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__, 2)) . '/');
define('APP_PATH', ROOT_PATH . 'apps');
define('CORE_PATH', ROOT_PATH . 'core');
define('SITE_DIR', $siteDir);
define('STATIC_DIR', SITE_DIR . '/static');
define('DOC_PATH', ROOT_PATH);
define('RUN_PATH', ROOT_PATH . 'runtime');
define('CONF_PATH', ROOT_PATH . 'config');

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/function/helper.php';
require CORE_PATH . '/function/file.php';
require CORE_PATH . '/basic/Config.php';
require CORE_PATH . '/basic/Controller.php';
require APP_PATH . '/home/controller/ParserController.php';

$refCfg = new ReflectionClass('core\basic\Config');
$cfgProp = $refCfg->getProperty('configs');
if (PHP_VERSION_ID < 80100) {
    $cfgProp->setAccessible(true);
}
$cfgProp->setValue(null, $cdnBase !== '' ? array(
    'upload_public_url_enable' => '1',
    'upload_public_url' => $cdnBase,
) : array(
    'upload_public_url_enable' => '0',
    'upload_public_url' => '',
));

$relativeSrc = upload_local_path($path);
if ($relativeSrc === null) {
    echo json_encode(array('error' => 'invalid path'));
    exit(1);
}

$srcFs = ROOT_PATH . ltrim($relativeSrc, '/');
$srcDir = dirname($srcFs);
if (! is_dir($srcDir)) {
    mkdir($srcDir, 0777, true);
}

$createdSrc = false;
if (! file_exists($srcFs)) {
    if (! function_exists('imagecreatetruecolor')) {
        echo json_encode(array('error' => 'gd missing'));
        exit(1);
    }
    $im = imagecreatetruecolor(80, 60);
    $bg = imagecolorallocate($im, 30, 144, 255);
    imagefill($im, 0, 0, $bg);
    imagejpeg($im, $srcFs, 90);
    gd_free_image($im);
    $createdSrc = true;
}

$width = isset($params['width']) ? $params['width'] : null;
$height = isset($params['height']) ? $params['height'] : null;
$maxwidth = isset($params['maxwidth']) ? $params['maxwidth'] : null;
$maxheight = isset($params['maxheight']) ? $params['maxheight'] : null;

$basename = basename($relativeSrc);
if (isset($params['width']) || isset($params['height'])) {
    $outFs = RUN_PATH . '/image/w' . $width . '_h' . $height . '_' . $basename;
} elseif (isset($params['maxwidth']) || isset($params['maxheight'])) {
    $outFs = RUN_PATH . '/image/mw' . $maxwidth . '_mh' . $maxheight . '_' . $basename;
} else {
    $outFs = '';
}
if ($outFs !== '' && file_exists($outFs)) {
    @unlink($outFs);
}

$ref = new ReflectionClass(\app\home\controller\ParserController::class);
$parser = $ref->newInstanceWithoutConstructor();
$mAsset = $ref->getMethod('parserAssetUrl');
$mAdjust = $ref->getMethod('adjustLabelData');
if (PHP_VERSION_ID < 80100) {
    $mAsset->setAccessible(true);
    $mAdjust->setAccessible(true);
}

$url = (string) $mAsset->invoke($parser, $path, $params);
$adjustRaw = (string) $mAdjust->invoke($parser, $params, $relativeSrc);
$adjustUrl = upload_public_url(upload_local_path($adjustRaw));

// 带 SITE_DIR 前缀的输入也应与 canonical 一致
$pathWithSite = ($siteDir !== '' ? $siteDir : '') . $relativeSrc;
$adjustPrefixed = (string) $mAdjust->invoke($parser, $params, $pathWithSite);
$urlFromPrefixed = (string) $mAsset->invoke($parser, $pathWithSite, $params);

$doubleNeedle = ($siteDir !== '' ? $siteDir . $siteDir . '/' : null);
$hasDouble = $doubleNeedle !== null && (
    strpos($url, $doubleNeedle) !== false
    || strpos($adjustUrl, $doubleNeedle) !== false
    || strpos($urlFromPrefixed, $doubleNeedle) !== false
);

$result = array(
    'url' => $url,
    'adjust_raw' => str_replace('\\', '/', $adjustRaw),
    'adjust_url' => $adjustUrl,
    'url_from_prefixed' => $urlFromPrefixed,
    'adjust_prefixed_raw' => str_replace('\\', '/', $adjustPrefixed),
    'src_exists' => file_exists($srcFs),
    'out_exists' => ($outFs !== '' && file_exists($outFs)),
    'out_file' => str_replace('\\', '/', $outFs),
    'has_double_prefix' => $hasDouble,
    'url_matches_adjust' => ($url === $adjustUrl),
);

if ($cleanup) {
    if ($outFs !== '' && file_exists($outFs)) {
        @unlink($outFs);
    }
    if ($createdSrc && file_exists($srcFs)) {
        @unlink($srcFs);
    }
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit(0);
