<?php
/**
 * 上传附件和上传视频
 * User: Jinqn
 * Date: 14-04-09
 * Time: 上午10:17
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

include "Uploader.class.php";

/* 上传配置 */
$base64 = "upload";
switch (htmlspecialchars($_GET['action'])) {
    case 'uploadimage':
        $config = array(
            "pathFormat" => $CONFIG['imagePathFormat'],
            "maxSize" => $CONFIG['imageMaxSize'],
            "allowFiles" => $CONFIG['imageAllowFiles']
        );
        $fieldName = $CONFIG['imageFieldName'];
        break;
    case 'uploadscrawl':
        $config = array(
            "pathFormat" => $CONFIG['scrawlPathFormat'],
            "maxSize" => $CONFIG['scrawlMaxSize'],
            "allowFiles" => $CONFIG['scrawlAllowFiles'],
            "oriName" => "scrawl.png"
        );
        $fieldName = $CONFIG['scrawlFieldName'];
        $base64 = "base64";
        break;
    case 'uploadvideo':
        $config = array(
            "pathFormat" => $CONFIG['videoPathFormat'],
            "maxSize" => $CONFIG['videoMaxSize'],
            "allowFiles" => $CONFIG['videoAllowFiles']
        );
        $fieldName = $CONFIG['videoFieldName'];
        break;
    case 'uploadfile':
    default:
        $config = array(
            "pathFormat" => $CONFIG['filePathFormat'],
            "maxSize" => $CONFIG['fileMaxSize'],
            "allowFiles" => $CONFIG['fileAllowFiles']
        );
        $fieldName = $CONFIG['fileFieldName'];
        break;
}

if (defined('STATIC_DIR')) {
    $config['pathFormat'] = STATIC_DIR . $config['pathFormat'];
}

/* 生成上传实例对象并完成上传 */
$up = new Uploader($fieldName, $config, $base64);

// 获取上传结果
$rs = $up->getFileInfo();

// 安全加固：如果上传失败，直接返回错误
if ($rs['state'] !== 'SUCCESS') {
    return json_encode($rs);
}

// 安全加固：验证最终文件的扩展名是否合法（防止大小写绕过、双扩展名等）
$uploadedExt = strtolower(pathinfo($rs['url'], PATHINFO_EXTENSION));
$allowedExts = array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'ico', 'svg',
    'mp4', 'mp3', 'webm', 'avi', 'flv', 'swf', 'mkv', 'rm', 'rmvb', 'mpeg', 'mpg', 'ogg', 'ogv', 'mov', 'wmv',
    'rar', 'zip', 'tar', 'gz', '7z', 'bz2', 'cab', 'iso',
    'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'pdf', 'txt', 'md', 'xml',
    'wav', 'mid');
if (!in_array($uploadedExt, $allowedExts)) {
    @unlink(ROOT_PATH . $rs['url']);
    return json_encode(array(
        'state' => '上传失败：文件扩展名不合法',
        'url' => '',
        'title' => '',
        'original' => '',
        'type' => '',
        'size' => 0
    ));
}

// 安全加固：检查上传路径是否在允许的目录下
$uploadDir = realpath(dirname(ROOT_PATH . $rs['url']));
$allowedDirs = array(
    realpath(ROOT_PATH . '/upload/image'),
    realpath(ROOT_PATH . '/upload/video'),
    realpath(ROOT_PATH . '/upload/file'),
    realpath(ROOT_PATH . STATIC_DIR . '/upload/image'),
    realpath(ROOT_PATH . STATIC_DIR . '/upload/video'),
    realpath(ROOT_PATH . STATIC_DIR . '/upload/file'),
);
$pathValid = false;
foreach ($allowedDirs as $allowedDir) {
    if ($allowedDir !== false && strpos($uploadDir, $allowedDir) === 0) {
        $pathValid = true;
        break;
    }
}
if (!$pathValid) {
    @unlink(ROOT_PATH . $rs['url']);
    return json_encode(array(
        'state' => '上传失败：非法的保存路径',
        'url' => '',
        'title' => '',
        'original' => '',
        'type' => '',
        'size' => 0
    ));
}

// 图片打水印
$ext = array(
    '.jpg',
    '.png',
    '.gif'
);
if (in_array($rs['type'], $ext)) {
    resize_img(ROOT_PATH . $rs['url']); // 缩放大小
    watermark_img(ROOT_PATH . $rs['url']); // 水印
}

/**
 * 得到上传文件所对应的各个参数,数组结构
 * array(
 * "state" => "", //上传状态，上传成功时必须返回"SUCCESS"
 * "url" => "", //返回的地址
 * "title" => "", //新文件名
 * "original" => "", //原始文件名
 * "type" => "" //文件类型
 * "size" => "", //文件大小
 * )
 */

/* 返回数据 */
return json_encode($up->getFileInfo());
