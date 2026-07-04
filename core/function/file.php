<?php

/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年8月3日
 *  数据处理函数库
 */
use core\basic\Config;

// 检测目录是否存在
function check_dir($path, $create = false)
{
    if (is_dir($path)) {
        return true;
    } elseif ($create) {
        return create_dir($path);
    }
}

// 创建目录
function create_dir($path)
{
    if (! file_exists($path)) {
        if (mkdir($path, 0777, true)) {
            return true;
        }
    }
    return false;
}

// 检查文件是否存在
function check_file($path, $create = false, $content = null)
{
    if (file_exists($path)) {
        return true;
    } elseif ($create) {
        return create_file($path, $content);
    }
}

// 创建文件
function create_file($path, $content = null, $over = false)
{
    if (file_exists($path) && ! $over) {
        return false;
    } elseif (file_exists($path)) {
        @unlink($path);
    }
    check_dir(dirname($path), true);
    $handle = fopen($path, 'w') or error('创建文件失败，请检查目录权限！');
    fwrite($handle, $content);
    return fclose($handle);
}

// 目录文件夹列表
function dir_list($path)
{
    $list = array();
    if (! is_dir($path) || ! $filename = scandir($path)) {
        return $list;
    }
    $files = count($filename);
    for ($i = 0; $i < $files; $i ++) {
        $dir = $path . '/' . $filename[$i];
        if (is_dir($dir) && $filename[$i] != '.' && $filename[$i] != '..') {
            $list[] = $filename[$i];
        }
    }
    return $list;
}

// 目录文件列表
function file_list($path)
{
    $list = array();
    if (! is_dir($path) || ! $filename = scandir($path)) {
        return $list;
    }
    $files = count($filename);
    for ($i = 0; $i < $files; $i ++) {
        $dir = $path . '/' . $filename[$i];
        if (is_file($dir)) {
            $list[] = $filename[$i];
        }
    }
    return $list;
}

// 目录下文件及文件夹列表
function path_list($path)
{
    $list = array();
    if (! is_dir($path) || ! $filename = scandir($path)) {
        return $list;
    }
    $files = count($filename);
    for ($i = 0; $i < $files; $i ++) {
        $dir = $path . '/' . $filename[$i];
        if (is_file($dir) || (is_dir($dir) && $filename[$i] != '.' && $filename[$i] != '..')) {
            $list[] = $filename[$i];
        }
    }
    return $list;
}

//获取目录和子目录下所有文件
function get_dir($path): array
{
    $files = array();
    if (is_dir($path)) {
        if ($handle = opendir($path)) {
            while (($file = readdir($handle)) !== false) {
                if ($file != "." && $file != ".." && $file != "file") {
                    if (is_dir($path . "/" . $file)) {
                        $files[$file] = get_dir($path . "/" . $file);
                    } else {
                        $files[] = $path . "/" . $file;
                    }
                 }
             }
            closedir($handle);
            return $files;
         }
     }
    return $files;
}

/**
 * 删除目录及目录下所有文件或删除指定文件
 *
 * @param str $path
 *            待删除目录路径
 * @param int $delDir
 *            是否删除目录，true删除目录，false则只删除文件保留目录
 * @return bool 返回删除状态
 */
function path_delete($path, $delDir = false, $exFile = array())
{
    $result = true; // 对于空目录直接返回true状态
    if (! file_exists($path)) {
        return $result;
    }
    if (is_dir($path)) {
        if (! ! $dirs = scandir($path)) {
            foreach ($dirs as $value) {
                if ($value != "." && $value != ".." && ! in_array($value, $exFile)) {
                    $dir = $path . '/' . $value;
                    $result = is_dir($dir) ? path_delete($dir, $delDir, $exFile) : unlink($dir);
                }
            }
            if ($result && $delDir) {
                return rmdir($path);
            } else {
                return $result;
            }
        } else {
            return false;
        }
    } else {
        return unlink($path);
    }
}

// 拷贝文件夹
function dir_copy($src, $des, $son = 1)
{
    if (! is_dir($src)) {
        return false;
    }
    
    if (! is_dir($des)) {
        create_dir($des);
    }
    
    $handle = dir($src);
    while (! ! $path = $handle->read()) {
        if (($path != ".") && ($path != "..")) {
            if (is_dir($src . "/" . $path)) {
                if ($son)
                    dir_copy($src . "/" . $path, $des . "/" . $path, $son);
            } else {
                copy($src . "/" . $path, $des . "/" . $path);
            }
        }
    }
    return true;
}

// 上传扩展名安全目录（代码级白名单母集，config 只能启用其子集）
function upload_catalog_extensions()
{
    return array(
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp',
        'xls', 'xlsx', 'doc', 'docx', 'ppt', 'pptx',
        'rar', 'zip', 'pdf', 'txt',
        'mp4', 'avi', 'flv', 'rmvb', 'mp3',
        'otf', 'ttf'
    );
}

// 规范化并过滤：upload.format 与代码母集取交集
function filter_upload_ext_allow($exts)
{
    $catalog = array_flip(upload_catalog_extensions());
    $result = array();
    foreach ((array) $exts as $ext) {
        $ext = strtolower(trim($ext, " \t\n\r\0\x0B.\""));
        if ($ext === '' || ! preg_match('/^[a-z0-9]{1,12}$/', $ext)) {
            continue;
        }
        if (isset($catalog[$ext])) {
            $result[] = $ext;
        }
    }
    return array_values(array_unique($result));
}

// 将 upload.format 转为 UEditor 使用的带点扩展名列表
function upload_ext_allow_to_dot_exts($exts)
{
    $dots = array();
    foreach (filter_upload_ext_allow($exts) as $ext) {
        $dots[] = '.' . $ext;
    }
    return $dots;
}

// 合并站点 upload.format 到 UEditor 配置
function ueditor_merge_upload_config($config)
{
    $all = upload_ext_allow_to_dot_exts(Config::get('upload.format', true));
    if (! $all) {
        return $config;
    }
    $image_set = array('.png', '.jpg', '.jpeg', '.gif');
    $video_set = array(
        '.flv', '.swf', '.mkv', '.avi', '.rm', '.rmvb', '.mpeg', '.mpg',
        '.ogg', '.ogv', '.mov', '.wmv', '.mp4', '.webm', '.mp3', '.wav', '.mid'
    );
    $images = array_values(array_intersect($all, $image_set));
    if ($images) {
        $config['imageAllowFiles'] = $images;
        $config['scrawlAllowFiles'] = $images;
        $config['catcherAllowFiles'] = $images;
        $config['imageManagerAllowFiles'] = $images;
    }
    $videos = array_values(array_intersect($all, $video_set));
    if ($videos) {
        $config['videoAllowFiles'] = $videos;
    }
    $config['fileAllowFiles'] = $all;
    $config['fileManagerAllowFiles'] = $all;
    return $config;
}

// 从文件名解析扩展名
function upload_ext_from_filename($filename)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext === '' || ! preg_match('/^[a-z0-9]{1,12}$/', $ext)) {
        return '';
    }
    return $ext;
}

// 将站点相对 URL（如 /static/upload/...）解析为磁盘绝对路径
function upload_resolve_public_path($url)
{
    $url = '/' . ltrim(str_replace('\\', '/', $url), '/');
    $candidates = array();
    if (! empty($_SERVER['DOCUMENT_ROOT'])) {
        $candidates[] = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/') . $url;
    }
    if (defined('DOC_PATH')) {
        $candidates[] = rtrim(str_replace('\\', '/', DOC_PATH), '/') . ltrim($url, '/');
    }
    if (defined('ROOT_PATH')) {
        $candidates[] = rtrim(str_replace('\\', '/', ROOT_PATH), '/') . ltrim($url, '/');
    }
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return $candidates ? $candidates[0] : ltrim($url, '/');
}

// 检测 GD 是否支持 WebP
function gd_supports_webp()
{
    if (! function_exists('gd_info')) {
        return false;
    }
    $info = gd_info();
    return ! empty($info['WebP Support']);
}

// 判断文件是否是图片
function is_image($path)
{
    if (! file_exists($path)) {
        return false;
    }
    $info = @getimagesize($path);
    if (! $info || empty($info[2])) {
        return false;
    }
    $types = array(
        IMAGETYPE_GIF,
        IMAGETYPE_JPEG,
        IMAGETYPE_PNG,
        IMAGETYPE_BMP
    );
    if (defined('IMAGETYPE_WEBP')) {
        $types[] = IMAGETYPE_WEBP;
    }
    return in_array($info[2], $types, true);
}

// 图片重编码，剥离伪装脚本/附加数据
function reencode_image($path, $img_quality = 90)
{
    if (! is_file($path) || ! is_readable($path)) {
        return '图片文件不可读！';
    }
    $info = @getimagesize($path);
    if (! $info || empty($info[2])) {
        return '上传文件不是有效的图片！';
    }
    list ($width, $height, $type) = $info;
    $img = null;
    switch ($type) {
        case IMAGETYPE_GIF:
            if (! function_exists('imagecreatefromgif')) {
                return '服务器不支持GIF图片处理！';
            }
            $img = @imagecreatefromgif($path);
            break;
        case IMAGETYPE_JPEG:
            $img = @imagecreatefromjpeg($path);
            break;
        case IMAGETYPE_PNG:
            $img = @imagecreatefrompng($path);
            break;
        case IMAGETYPE_BMP:
            if (! function_exists('imagecreatefrombmp')) {
                return '服务器不支持BMP图片处理！';
            }
            $img = @imagecreatefrombmp($path);
            break;
        default:
            if (defined('IMAGETYPE_WEBP') && $type == IMAGETYPE_WEBP) {
                if (! gd_supports_webp()) {
                    return '服务器不支持WebP图片处理！';
                }
                $img = @imagecreatefromwebp($path);
                break;
            }
            return '不允许的图片格式！';
    }
    if (! $img) {
        return '图片解码失败，可能文件已损坏或为伪装图片！';
    }
    $ok = false;
    switch ($type) {
        case IMAGETYPE_GIF:
            $ok = imagegif($img, $path);
            break;
        case IMAGETYPE_JPEG:
            $ok = imagejpeg($img, $path, $img_quality);
            break;
        case IMAGETYPE_PNG:
            $ok = imagepng($img, $path, (int) ($img_quality / 10));
            break;
        case IMAGETYPE_BMP:
            if (function_exists('imagebmp')) {
                $ok = imagebmp($img, $path);
            }
            break;
        default:
            if (defined('IMAGETYPE_WEBP') && $type == IMAGETYPE_WEBP && function_exists('imagewebp')) {
                $ok = imagewebp($img, $path, $img_quality);
            }
            break;
    }
    imagedestroy($img);
    return $ok ? true : '图片重编码保存失败！';
}

/**
 * 文件上传
 *
 * @param string $input_name表单名称
 * @param string $file_ext允许的扩展名
 * @param number $max_width最大宽度
 * @param number $max_height最大高度
 * @return string 返回成功上传文件的路径数组
 */
function upload($input_name, $file_ext = null, $max_width = null, $max_height = null, $watermark = false)
{
    // 未选择文件返回空
    if (! isset($_FILES[$input_name])) {
        return '文件超过PHP环境允许的大小！';
    } else {
        $files = $_FILES[$input_name];
    }
    
    // 定义允许上传的扩展（config 子集 ∩ 代码母集）
    if (! $file_ext) {
        $array_ext_allow = Config::get('upload.format', true);
    } else {
        $array_ext_allow = explode(',', $file_ext);
    }
    $array_ext_allow = filter_upload_ext_allow($array_ext_allow);
    if (! $array_ext_allow) {
        return '未配置允许的上传格式！';
    }
    
    // 未直接传递函数参数，且具有地址参数，则打水印
    if (! $watermark && get('watermark', 'int')) {
        $watermark = true;
    }
    
    $array_save_file = array();
    if (is_array($files['tmp_name'])) { // 多文件情况
        $file_count = count($files['tmp_name']);
        for ($i = 0; $i < $file_count; $i ++) {
            if (! $files['error'][$i]) {
                $upfile = handle_upload($files['name'][$i], $files['tmp_name'][$i], $array_ext_allow, $max_width, $max_height, $watermark);
                if (strrpos($upfile, '/') > 0) {
                    $array_save_file[] = $upfile;
                } else {
                    $err = $upfile;
                }
            } else {
                $err = '错误代码' . $files['error'][$i];
            }
        }
    } else { // 单文件情况
        if (! $files['error']) {
            $upfile = handle_upload($files['name'], $files['tmp_name'], $array_ext_allow, $max_width, $max_height, $watermark);
            if (strrpos($upfile, '/') > 0) {
                $array_save_file[] = $upfile;
            } else {
                $err = $upfile;
            }
        } else {
            $err = '错误代码' . $files['error'];
        }
    }
    if (isset($err)) {
        return $err;
    } else {
        return $array_save_file;
    }
}

// 处理并移动上传文件
function handle_upload($file, $temp, $array_ext_allow, $max_width, $max_height, $watermark)
{
    // 定义主存储路径
    $save_path = DOC_PATH . STATIC_DIR . '/upload';
    
    $array_ext_allow = filter_upload_ext_allow($array_ext_allow);
    if (! $array_ext_allow) {
        return '未配置允许的上传格式！';
    }
    
    $file_ext = upload_ext_from_filename($file);
    if ($file_ext === '' || ! in_array($file_ext, $array_ext_allow, true)) {
        return ($file_ext ?: '未知') . '格式的文件不允许上传！';
    }
    
    $image = array(
        'png',
        'jpg',
        'jpeg',
        'gif',
        'bmp',
        'webp'
    );
    $file = array(
        'ppt',
        'pptx',
        'xls',
        'xlsx',
        'doc',
        'docx',
        'pdf',
        'txt'
    );
    if (in_array($file_ext, $image, true)) {
        $file_type = 'image';
    } elseif (in_array($file_ext, $file, true)) {
        $file_type = 'file';
    } else {
        $file_type = 'other';
    }
    
    // 检查文件存储路径
    if (! check_dir($save_path . '/' . $file_type . '/' . date('Ymd'), true)) {
        return '存储目录创建失败！';
    }
    $file_path = $save_path . '/' . $file_type . '/' . date('Ymd') . '/' . time() . mt_rand(100000, 999999) . '.' . $file_ext;
    if (! move_uploaded_file($temp, $file_path)) { // 从缓存中转存
        return '从缓存中转存失败！';
    }
    $save_file = str_replace(ROOT_PATH, '', $file_path); // 获取文件站点路径
    
    // 图片：重编码剥离伪装内容，再缩放/水印
    if (in_array($file_ext, $image, true)) {
        if (($re = reencode_image($file_path)) !== true) {
            @unlink($file_path);
            return $re;
        }
        if (($reset = resize_img($file_path, $file_path, $max_width, $max_height)) !== true) {
            @unlink($file_path);
            return $reset;
        }
        if ($watermark) {
            watermark_img($file_path);
        }
    }
    return $save_file;
}

/**
 * *
 * 等比缩放图片
 *
 * @param string $src_image源图片路径
 * @param string $out_image输出图像路径
 * @param number $max_width最大宽
 * @param number $max_height最大高
 * @param number $img_quality图片质量
 * @return boolean 返回是否成功
 */
function resize_img($src_image, $out_image = null, $max_width = null, $max_height = null, $img_quality = 90)
{
    // 输出地址
    if (! $out_image)
        $out_image = $src_image;
    
    // 读取配置文件设置
    if (! $max_width)
        $max_width = Config::get('upload.max_width') ?: 999999999;
    if (! $max_height)
        $max_height = Config::get('upload.max_height') ?: 999999999;
    
    // 获取图片属性
    $size_info = @getimagesize($src_image);
    if (! $size_info || empty($size_info[2])) {
        return '上传文件不是有效的图片！';
    }
    list ($width, $height, $type, $attr) = $size_info;
    
    // 检查输出目录
    check_dir(dirname($out_image), true);
    
    // 无需缩放的图片
    if ($width <= $max_width && $height <= $max_height) {
        if ($src_image != $out_image) { // 存储地址不一致时进行拷贝
            if (! copy($src_image, $out_image)) {
                return '缩放图片时拷贝到目的地址失败！';
            }
        }
        return true;
    }
    
    // 求缩放比例
    if ($max_width && $max_height) {
        $scale = min($max_width / $width, $max_height / $height);
    } elseif ($max_width) {
        $scale = $max_width / $width;
    } elseif ($max_height) {
        $scale = $max_height / $height;
    }
    
    if ($scale < 1) {
        switch ($type) {
            case IMAGETYPE_GIF:
                $img = imagecreatefromgif($src_image);
                break;
            case IMAGETYPE_JPEG:
                $img = imagecreatefromjpeg($src_image);
                break;
            case IMAGETYPE_PNG:
                $img = imagecreatefrompng($src_image);
                break;
            default:
                if (defined('IMAGETYPE_WEBP') && $type == IMAGETYPE_WEBP && gd_supports_webp()) {
                    $img = imagecreatefromwebp($src_image);
                } else {
                    return '不允许的图片格式！';
                }
        }
        if (! $img) {
            return '图片解码失败！';
        }
        
        $new_width = floor($scale * $width);
        $new_height = floor($scale * $height);
        $new_img = imagecreatetruecolor($new_width, $new_height); // 创建画布
                                                                  
        // 创建透明画布,避免黑色
        if ($type == IMAGETYPE_GIF || $type == IMAGETYPE_PNG) {
            $color = imagecolorallocate($new_img, 255, 255, 255);
            imagefill($new_img, 0, 0, $color);
            imagecolortransparent($new_img, $color);
        }
        imagecopyresized($new_img, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        
        switch ($type) {
            case IMAGETYPE_GIF:
                imagegif($new_img, $out_image, $img_quality);
                break;
            case IMAGETYPE_JPEG:
                imagejpeg($new_img, $out_image, $img_quality);
                break;
            case IMAGETYPE_PNG:
                imagepng($new_img, $out_image, $img_quality / 10);
                break;
            default:
                if (defined('IMAGETYPE_WEBP') && $type == IMAGETYPE_WEBP && function_exists('imagewebp')) {
                    imagewebp($new_img, $out_image, $img_quality);
                } else {
                    imagejpeg($new_img, $out_image, $img_quality);
                }
        }
        imagedestroy($new_img);
        imagedestroy($img);
    }
    return true;
}

// 剪切图片
function cut_img($src_image, $out_image = null, $new_width = null, $new_height = null, $img_quality = 90)
{
    // 输出地址
    if (! $out_image)
        $out_image = $src_image;
    
    // 读取配置文件设置
    if (! $new_width && ! $new_height)
        return;
    
    // 获取图片属性
    list ($width, $height, $type, $attr) = getimagesize($src_image);
    switch ($type) {
        case 1:
            $img = imagecreatefromgif($src_image);
            break;
        case 2:
            $img = imagecreatefromjpeg($src_image);
            break;
        case 3:
            $img = imagecreatefrompng($src_image);
            break;
    }
    
    // 不限定是等比例缩放
    if (! $new_width) {
        $new_width = floor($width * ($new_height / $height));
    }
    if (! $new_height) {
        $new_height = floor($height * ($new_width / $width));
    }
    
    // 计算裁剪是变大缩小方式
    if ($width >= $new_width && $height >= $new_height) { // 长宽均满足
        $cut_width = $new_width;
        $cut_height = $new_height;
    } else { // 有一边不满足
        $scale1 = $width / $new_width;
        $scale2 = $height / $new_height;
        if ($scale1 < $scale2) { // 变化越多的一边取全值，其余一边等比例缩放
            $cut_width = $width;
            $cut_height = floor($height * ($width / $new_width));
        } else {
            $cut_width = floor($new_width * ($height / $new_height));
            $cut_height = $height;
        }
    }
    
    // 创建画布
    $new_img = imagecreatetruecolor($new_width, $new_height);
    
    // 创建透明画布,避免黑色
    if ($type == 1 || $type == 3) {
        $color = imagecolorallocate($new_img, 255, 255, 255);
        imagefill($new_img, 0, 0, $color);
        imagecolortransparent($new_img, $color);
    }
    
    imagecopyresized($new_img, $img, 0, 0, 0, 0, $new_width, $new_height, $cut_width, $cut_height);
    check_dir(dirname($out_image), true); // 检查输出目录
    
    switch ($type) {
        case 1:
            imagegif($new_img, $out_image, $img_quality);
            break;
        case 2:
            imagejpeg($new_img, $out_image, $img_quality);
            break;
        case 3:
            imagepng($new_img, $out_image, $img_quality / 10); // $quality参数取值范围0-99 在php 5.1.2之后变更为0-9
            break;
        default:
            imagejpeg($new_img, $out_image, $img_quality);
    }
    imagedestroy($new_img);
    imagedestroy($img);
    return true;
}

// 图片水印
function watermark_img($src_image, $out_image = null, $position = null, $watermark_image = null, $watermark_text = '', $watermark_text_size = null, $watermark_text_color = null)
{
    if (! Config::get('watermark_open')) {
        return;
    }
    
    // 输出地址
    if (! $out_image)
        $out_image = $src_image;
    
    // 如果不存在文字及图片则直接返回
    if (! $watermark_text) {
        $watermark_text = Config::get('watermark_text') ?: 'PbootCMS';
    }
    $watermark_image = $watermark_image ?: Config::get('watermark_pic');
    if (! $watermark_text && ! $watermark_image) {
        return;
    }
    
    // 获取图片属性
    list ($width1, $height1, $type1, $attr1) = getimagesize($src_image);
    switch ($type1) {
        case 1:
            $img1 = imagecreatefromgif($src_image);
            break;
        case 2:
            $img1 = imagecreatefromjpeg($src_image);
            break;
        case 3:
            $img1 = imagecreatefrompng($src_image);
            break;
    }
    
    if ($watermark_image) {
        $watermark_image = ROOT_PATH . $watermark_image;
        // 获取水印图片
        list ($width2, $height2, $type2, $attr2) = getimagesize($watermark_image);
        switch ($type2) {
            case 1:
                $img2 = imagecreatefromgif($watermark_image);
                break;
            case 2:
                $img2 = imagecreatefromjpeg($watermark_image);
                break;
            case 3:
                $img2 = imagecreatefrompng($watermark_image);
                break;
        }
    } else {
        if (! $watermark_text_size) {
            $watermark_text_size = Config::get('watermark_text_size') ?: 16;
        }
        if (! $watermark_text_color) {
            $watermark_text_color = Config::get('watermark_text_color') ?: '100,100,100';
        }
        $colors = explode(',', $watermark_text_color);
        
        if (Config::get('watermark_text_font')) {
            $font = ROOT_PATH . Config::get('watermark_text_font');
        } else {
            return;
        }
        
        // 手动创建水印图像
        $fontsize = $watermark_text_size;
        $width2 = mb_strlen($watermark_text, 'UTF-8') * ($fontsize + 10) + 20;
        $height2 = $fontsize + 10;
        $img2 = imagecreatetruecolor($width2, $height2);
        $color = imagecolorallocate($img2, 255, 255, 255);
        imagefill($img2, 0, 0, $color);
        imagecolortransparent($img2, $color); // 创建透明图
        $textcolor = imagecolorallocate($img2, $colors[0], $colors[1], $colors[2]);
        imagettftext($img2, $fontsize, 0, 5, $fontsize + 5, $textcolor, $font, $watermark_text);
    }
    
    // 现对图片太大时，自动缩放水印
    if ($width1 < $width2 * 3 || $height1 < $height2) {
        $scale = min(($width1 / 3) / $width2, ($height1 / 2) / $height2); // 求缩放比例
        $new_width = floor($scale * $width2);
        $new_height = floor($scale * $height2);
    } else {
        $new_width = $width2;
        $new_height = $height2;
    }
    
    // 水印位置
    if (! $position) {
        $position = Config::get('watermark_position') ?: 4;
    }
    switch ($position) {
        case '1':
            $x = 15;
            $y = 15;
            break;
        case '2':
            $x = $width1 - $new_width - 15;
            $y = 20;
            break;
        case '3':
            $x = 20;
            $y = $height1 - $new_height - 15;
            break;
        case '5':
            $x = ($width1 - $new_width) / 2;
            $y = ($height1 - $new_height) / 2;
            break;
        default:
            $x = $width1 - $new_width - 15;
            $y = $height1 - $new_height - 15;
            break;
    }
    
    // 创建透明画布,避免黑色
    if ($type1 == 1 || $type1 == 3) {
        $out = imagecreatetruecolor($width1, $height1);
        $color = imagecolorallocate($out, 255, 255, 255);
        imagefill($out, 0, 0, $color);
        imagecolortransparent($out, $color);
        imagecopy($out, $img1, 0, 0, 0, 0, $width1, $height1);
    } else {
        $out = $img1;
    }
    
    // 打上水印
    imagecopyresized($out, $img2, $x, $y - 10, 0, 0, $new_width, $new_height, $width2, $height2);
    check_dir(dirname($out_image), true); // 检查输出目录
                                          
    // 输出图片
    switch ($type1) {
        case 1:
            imagegif($out, $out_image, 90);
            break;
        case 2:
            imagejpeg($out, $out_image, 90);
            break;
        case 3:
            imagepng($out, $out_image, 90 / 10); // $quality参数取值范围0-99 在php 5.1.2之后变更为0-9
            break;
        default:
            imagejpeg($out, $out_image, 90);
    }
    imagedestroy($img1);
    imagedestroy($img2);
    return true;
}


