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

// 图片打水印
$rs = $up->getFileInfo();
$ext = array(
    '.jpg',
    '.jpeg',
    '.png',
    '.gif',
    '.webp'
);

$ossEnabled = \core\basic\Config::get('upload.oss_enabled');

// 如果上传成功且启用了 OSS，则上传到阿里云 OSS
if (!$ossEnabled) {
    // 未启用 OSS，使用原有的图片处理逻辑
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
} else {
    // 获取本地文件路径
    $localFilePath = ROOT_PATH . $rs['url'];

    if (file_exists($localFilePath)) {
        // 确定文件类型
        $extension = '.' . strtolower(pathinfo($rs['url'], PATHINFO_EXTENSION));
        if (in_array($extension, $ext)) {
            $fileType = 'image';
        } elseif (in_array($extension, array('.pdf', '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx', '.md', '.mkd', '.txt'))) {
            $fileType = 'file';
        } else {
            $fileType = 'other';
        }

        // 生成 OSS 路径（添加固定父级目录 ztfxcx）
        $ossPath = 'ztfxcx/' . $fileType . '/' . date('Ymd') . '/' . basename($rs['url']);

        // 如果是图片，先进行缩放和水印处理
        if (in_array($extension, $ext)) {
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
            $rs['url'] = $result['url'];

            // 删除本地临时文件
            @unlink($localFilePath);
        }
    }
    /* 返回数据 */
    return json_encode($rs);
}
