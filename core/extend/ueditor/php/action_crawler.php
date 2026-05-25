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
foreach ($source as $imgUrl) {
    $item = new Uploader($imgUrl, $config, "remote");
    $info = $item->getFileInfo();
    
    // 图片打水印
    $ext = array(
        '.jpg',
        '.png',
        '.gif'
    );
    if (in_array($info['type'], $ext)) {
        resize_img(ROOT_PATH . $info['url']); // 缩放大小
        watermark_img(ROOT_PATH . $info['url']); // 水印
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