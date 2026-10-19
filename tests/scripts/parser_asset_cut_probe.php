<?php

declare(strict_types=1);

/**
 * 图片类标签截取参数探测脚本
 *
 * 用法: php tests/scripts/parser_asset_cut_probe.php <site_dir> <base64_json_payload>
 * site_dir: '' | '/cms'
 * payload: { path, param, cdn? }，param 为模板参数串，如 "substr=47,4"
 * 输出: { content, list, label }，分别为 {content:ico}、[list:ico]、{label:xxx}（图片类型）的解析结果
 */

$siteDir = isset($argv[1]) ? (string) $argv[1] : '';
$payloadRaw = isset($argv[2]) ? (string) $argv[2] : '';
$payloadJson = $payloadRaw !== '' ? base64_decode($payloadRaw, true) : '';
$payload = is_string($payloadJson) && $payloadJson !== '' ? json_decode($payloadJson, true) : array();
if (! is_array($payload)) {
    $payload = array();
}

$path = isset($payload['path']) ? (string) $payload['path'] : '';
$param = isset($payload['param']) ? (string) $payload['param'] : '';
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

final class AssetCutLabelModelStub
{
    /** @var array */
    public $labels = array();

    public function getLabel()
    {
        return $this->labels;
    }
}

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

$ref = new ReflectionClass(\app\home\controller\ParserController::class);
$parser = $ref->newInstanceWithoutConstructor();

$model = new AssetCutLabelModelStub();
$model->labels = array(
    'probepic' => array('value' => $path, 'type' => 3),
);
$modelProp = $ref->getProperty('model');
$methods = array(
    'parserParam' => $ref->getMethod('parserParam'),
    'parserContent' => $ref->getMethod('parserContent'),
    'parserList' => $ref->getMethod('parserList'),
);
if (PHP_VERSION_ID < 80100) {
    $modelProp->setAccessible(true);
    foreach ($methods as $method) {
        $method->setAccessible(true);
    }
}
$modelProp->setValue($parser, $model);

$params = $methods['parserParam']->invoke($parser, $param);
$data = new stdClass();
$data->ico = $path;
$data->content = '';

$suffix = $param !== '' ? ' ' . $param : '';
$contentTag = '{content:ico' . $suffix . '}';
$listTag = '[list:ico' . $suffix . ']';
$labelTag = '{label:probepic' . $suffix . '}';

$result = array(
    'params' => $params,
    'content' => (string) $methods['parserContent']->invoke($parser, 'ico', $contentTag, $contentTag, $data, $params, null),
    'list' => (string) $methods['parserList']->invoke($parser, 'ico', $listTag, $listTag, $data, $params, 1),
    'label' => (string) $parser->parserUserLabel($labelTag),
);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit(0);
