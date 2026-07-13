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
    $CONFIG = ueditor_merge_upload_config($CONFIG);
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

$rs = $up->getFileInfo();
if ($rs['state'] === 'SUCCESS') {
    $full_path = upload_resolve_public_path($rs['url']);
    if (is_image($full_path)) {
        $re = upload_post_process_image($full_path, null, true);
        if ($re !== true) {
            @unlink($full_path);
            return json_encode(array(
                'state' => $re,
                'url' => $rs['url'],
                'title' => $rs['title'],
                'original' => $rs['original'],
                'type' => $rs['type'],
                'size' => $rs['size']
            ));
        }
        $notice = upload_post_process_last_notice();
        if ($notice !== '') {
            $rs['warning'] = $notice;
            return json_encode($rs);
        }
    }
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
