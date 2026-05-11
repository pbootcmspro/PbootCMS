<?php
/**
 * 抓取远程图片
 * User: Jinqn
 * Date: 14-04-14
 * Time: 下午19:18
 */
// 防止直接访问：未通过 controller.php 引入时执行独立验证
if (!isset($CONFIG)) {
    require_once '../../../init.php';
    error_reporting(0);
    if (!session('sid')) {
        echo json_encode(array('state' => '权限不足，请重新登录'));
        exit;
    }
    $sid = encrypt_string(session_id() . session('id'));
    if ($sid != session('sid')) {
        session_destroy();
        echo json_encode(array('state' => '权限不足，请重新登录'));
        exit;
    }
    $CONFIG = json_decode(preg_replace("/\/\*[\s\S]+?\*\//", "", file_get_contents("config.json")), true);
}

set_time_limit(0);
include ("Uploader.class.php");

/* 上传配置 */
$config = array(
    "pathFormat" => $CONFIG['catcherPathFormat'],
    "maxSize" => $CONFIG['catcherMaxSize'],
    "allowFiles" => $CONFIG['catcherAllowFiles'],
    "oriName" => "remote.png"
);
$fieldName = $CONFIG['catcherFieldName'];

if (defined('STATIC_DIR')) {
    $config['pathFormat'] = STATIC_DIR . $config['pathFormat'];
}

/* 抓取远程图片 */
$list = array();
if (isset($_POST[$fieldName])) {
    $source = $_POST[$fieldName];
} else {
    $source = $_GET[$fieldName];
}

// 检查是否启用 OSS
$ossEnabled = \core\basic\Config::get('upload.oss_enabled');

foreach ($source as $imgUrl) {
    $item = new Uploader($imgUrl, $config, "remote");
    $info = $item->getFileInfo();
    
    // 未启用 OSS，使用原有的图片处理逻辑
    if (!$ossEnabled) {
        // 图片打水印
        $ext = array(
            '.jpg',
            '.jpeg',
            '.png',
            '.gif',
            '.webp'
        );
        if (in_array($info['type'], $ext)) {
            resize_img(ROOT_PATH . $info['url']); // 缩放大小
            watermark_img(ROOT_PATH . $info['url']); // 水印
        }
    } else {
        // 如果启用了 OSS，则上传到阿里云 OSS
        $localFilePath = ROOT_PATH . $info['url'];
        if (file_exists($localFilePath)) {
            // 生成 OSS 路径（添加固定父级目录 ztfxcx）
            $ossPath = 'ztfxcx/image/' . date('Ymd') . '/' . basename($info['url']);
            // 如果是图片，先进行缩放和水印处理
            $ext = strtolower(pathinfo($info['url'], PATHINFO_EXTENSION));
            if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif'))) {
                resize_img($localFilePath);
                watermark_img($localFilePath);
            }
            
            // 上传到 OSS
            require_once CORE_PATH . '/extend/oss/AmazonS3.php';
            $ossConfig = \core\basic\Config::get('upload.oss_config', true);
            $oss = new \core\extend\oss\AmazonS3($ossConfig);
            $result = $oss->uploadFile($localFilePath, $ossPath);
            
            if ($result['code'] == 1) {
                // 上传成功，更新 URL 为 OSS URL
                $info['url'] = $result['url'];
                
                // 删除本地临时文件
                @unlink($localFilePath);
            }
        }
    }
    
    array_push($list, array(
        "state" => $info["state"],
        "url" => $info["url"],
        "size" => $info["size"],
        "title" => htmlspecialchars($info["title"]),
        "original" => htmlspecialchars($info["original"]),
        "source" => htmlspecialchars($imgUrl)
    ));
}

/* 返回抓取数据 */
return json_encode(array(
    'state' => count($list) ? 'SUCCESS' : 'ERROR',
    'list' => $list
));