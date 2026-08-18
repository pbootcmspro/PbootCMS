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

/**
 * 清理运行时缓存目录。
 * 常规模式仅删除白名单子目录，保留 runtime/image 缩略图等；
 * 仅当 $delall 为真时才清空整个运行目录。
 *
 * @param string $runPath
 *            运行目录（如 RUN_PATH）
 * @param string $mode
 *            all=含 cache；sys=仅系统缓存（complile/config/upgrade）
 * @param mixed $delall
 *            为真时 path_delete 整个 $runPath（含 image）
 * @return bool
 */
function purge_runtime_cache($runPath, $mode = 'all', $delall = false)
{
    if ($delall) {
        return path_delete($runPath);
    }
    // 白名单：明确不包含 image / session / data，避免误删缩略图与会话
    $dirs = array(
        'complile',
        'config',
        'upgrade'
    );
    if ($mode === 'all') {
        array_unshift($dirs, 'cache');
    }
    $rs = true;
    foreach ($dirs as $dir) {
        if (! path_delete($runPath . '/' . $dir)) {
            $rs = false; // 继续清理其余目录，避免短路导致残留
        }
    }
    return $rs;
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
         // 图片（10）
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'ico', 'svg', 'svgz', 'avif',
        // 办公文档（15）
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'pdf', 'txt', 'md', 'xml', 'csv',
        'rtf', 'wps', 'et', 'dps',
        // 压缩（6）
        'rar', 'zip', '7z', 'tar', 'gz', 'bz2',
        // 视频（13）
        'mp4', 'webm', 'mov', 'avi', 'mkv', 'flv', 'rmvb', 'mpeg', 'mpg',
        'wmv', 'ogv', 'rm', 'm4v',
        // 音频（8）
        'mp3', 'wav', 'ogg', 'm4a', 'aac', 'mid', 'wma', 'flac',
        // 字幕（1）
        'srt',
        // 字体（4）
        'otf', 'ttf', 'woff', 'woff2',
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

// 兼容在线升级保留的旧版默认上传白名单
function upload_config_extensions()
{
    $exts = Config::get('upload.format', true);
    $legacy_default = array(
        'jpg', 'jpeg', 'png', 'gif', 'xls', 'xlsx', 'doc', 'docx', 'ppt', 'pptx',
        'rar', 'zip', 'pdf', 'txt', 'mp4', 'avi', 'flv', 'rmvb', 'mp3', 'otf', 'ttf'
    );
    if ($exts === $legacy_default) {
        $exts[] = 'webp';
        $exts[] = 'svg';
        $exts[] = 'svgz';
        $exts[] = 'avif';
    }
    return $exts;
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
    $all = upload_ext_allow_to_dot_exts(upload_config_extensions());
    if (! $all) {
        return $config;
    }
    $image_set = array('.png', '.jpg', '.jpeg', '.gif', '.bmp', '.webp', '.svg', '.svgz', '.avif');
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
    $url = '/' . ltrim(str_replace('\\', '/', (string) $url), '/');
    $rel = ltrim($url, '/');
    $candidates = array();
    if (defined('DOC_PATH')) {
        $candidates[] = rtrim(str_replace('\\', '/', DOC_PATH), '/') . '/' . $rel;
    }
    if (defined('ROOT_PATH')) {
        $candidates[] = rtrim(str_replace('\\', '/', ROOT_PATH), '/') . '/' . $rel;
    }
    if (! empty($_SERVER['DOCUMENT_ROOT'])) {
        $candidates[] = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/') . $url;
    }
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return $candidates ? $candidates[0] : $rel;
}

// GD 是否支持 JPEG
function gd_supports_jpeg()
{
    if (! function_exists('gd_info') || ! function_exists('imagecreatefromjpeg')) {
        return false;
    }
    $info = gd_info();
    return ! empty($info['JPEG Support']);
}

// GD 是否支持 FreeType（文字水印）
function gd_supports_freetype()
{
    if (! function_exists('gd_info') || ! function_exists('imagettftext')) {
        return false;
    }
    $info = gd_info();
    return ! empty($info['FreeType Support']);
}

// 解析 ini 大小值为字节（用于 post_max_size 检测）
function parse_ini_size_bytes($value)
{
    $value = trim((string) $value);
    if ($value === '-1') {
        return PHP_INT_MAX;
    }
    $unit = strtolower(substr($value, -1));
    $num = (float) $value;
    switch ($unit) {
        case 'g':
            return (int) ($num * 1024 * 1024 * 1024);
        case 'm':
            return (int) ($num * 1024 * 1024);
        case 'k':
            return (int) ($num * 1024);
        default:
            return (int) $num;
    }
}

// 解析 memory_limit 为字节
function parse_memory_limit_bytes()
{
    return parse_ini_size_bytes(ini_get('memory_limit'));
}

// 估算 GD 处理图片所需内存（宽×高×4字节×缓冲数）
function gd_estimate_image_memory($width, $height, $buffers = 4)
{
    $bytes = (float) $width * (float) $height * 4 * (float) $buffers;
    return (int) min(PHP_INT_MAX, $bytes);
}

// 当前可用内存是否满足需求（预留 20% 余量）
function gd_has_enough_memory($need_bytes)
{
    $limit = parse_memory_limit_bytes();
    if ($limit === PHP_INT_MAX) {
        return true;
    }
    $available = $limit - memory_get_usage(true);
    return $available > $need_bytes * 1.2;
}

// 释放 GD 图像资源（PHP 7.x resource）；PHP 8.0+ 由 GC 回收，无需 imagedestroy
function gd_free_image($img)
{
    if (PHP_VERSION_ID < 80000 && is_resource($img)) {
        imagedestroy($img);
    }
}

// 统一加载图片资源，成功返回 array($img, true)，失败返回 array(false, $message)
function gd_load_image($path, $type)
{
    $img = null;
    switch ($type) {
        case IMAGETYPE_GIF:
            if (! function_exists('imagecreatefromgif')) {
                return array(false, '服务器不支持GIF图片处理！');
            }
            $img = @imagecreatefromgif($path);
            break;
        case IMAGETYPE_JPEG:
            if (! gd_supports_jpeg()) {
                return array(false, '服务器GD未启用JPEG支持，无法处理JPG图片！');
            }
            $img = @imagecreatefromjpeg($path);
            break;
        case IMAGETYPE_PNG:
            $img = @imagecreatefrompng($path);
            break;
        case IMAGETYPE_BMP:
            if (! function_exists('imagecreatefrombmp')) {
                return array(false, '服务器不支持BMP图片处理！');
            }
            $img = @imagecreatefrombmp($path);
            break;
        default:
            if ($type == image_type_webp()) {
                if (! gd_supports_webp()) {
                    return array(false, '服务器不支持WebP图片处理！');
                }
                $img = @imagecreatefromwebp($path);
                break;
            }
            if ($type == image_type_avif()) {
                if (! gd_supports_avif()) {
                    return array(false, '服务器不支持AVIF图片处理！');
                }
                $img = @imagecreatefromavif($path);
                break;
            }
            return array(false, '不允许的图片格式！');
    }
    if (! $img) {
        return array(false, '图片解码失败，可能文件已损坏！');
    }
    return array($img, true);
}

// 统一保存图片
function gd_save_image($img, $path, $type, $quality = 90)
{
    switch ($type) {
        case IMAGETYPE_GIF:
            // imagegif 仅接受 (image, filename)，多传参数在 PHP7 会告警失败、PHP8 抛异常
            return @imagegif($img, $path);
        case IMAGETYPE_JPEG:
            return @imagejpeg($img, $path, $quality);
        case IMAGETYPE_PNG:
            return @imagepng($img, $path, (int) ($quality / 10));
        case IMAGETYPE_BMP:
            return function_exists('imagebmp') ? @imagebmp($img, $path) : false;
        default:
            if ($type == image_type_webp() && gd_supports_webp()) {
                return @imagewebp($img, $path, $quality);
            }
            if ($type == image_type_avif() && gd_supports_avif()) {
                return @imageavif($img, $path, $quality);
            }
            return false;
    }
}

// WebP 图片类型常量（PHP 7.1+ 自带 IMAGETYPE_WEBP）
function image_type_webp()
{
    return defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : 18;
}

// GD 是否支持 WebP 读写
function gd_supports_webp()
{
    if (! function_exists('imagecreatefromwebp') || ! function_exists('imagewebp') || ! function_exists('gd_info')) {
        return false;
    }
    $info = gd_info();
    return ! empty($info['WebP Support']);
}

// GD WebP 是否可用（测试可通过 $GLOBALS['__test_gd_supports_webp'] 覆盖）
function gd_webp_gd_available()
{
    if (array_key_exists('__test_gd_supports_webp', $GLOBALS)) {
        return (bool) $GLOBALS['__test_gd_supports_webp'];
    }
    return gd_supports_webp();
}

// AVIF 图片类型常量（PHP 8.1+ 自带 IMAGETYPE_AVIF）
function image_type_avif()
{
    return defined('IMAGETYPE_AVIF') ? IMAGETYPE_AVIF : 19;
}

// GD 是否支持 AVIF 读写
function gd_supports_avif()
{
    if (! function_exists('imagecreatefromavif') || ! function_exists('imageavif') || ! function_exists('gd_info')) {
        return false;
    }
    $info = gd_info();
    if (! isset($info['AVIF Support'])) {
        return false;
    }
    return ! empty($info['AVIF Support']);
}

// GD AVIF 是否可用（测试可通过 $GLOBALS['__test_gd_supports_avif'] 覆盖）
function gd_avif_gd_available()
{
    if (array_key_exists('__test_gd_supports_avif', $GLOBALS)) {
        return (bool) $GLOBALS['__test_gd_supports_avif'];
    }
    return gd_supports_avif();
}

/**
 * 清除图像后端能力探测缓存（测试或配置变更后可调用）
 */
function image_backend_clear_probe_cache()
{
    unset($GLOBALS['__imagick_probe_cache'], $GLOBALS['__image_capability_matrix_cache']);
}

/**
 * Imagick 白名单格式（读/写，唯一数据源）
 *
 * @return array
 */
function imagick_format_whitelist()
{
    return array('AVIF', 'HEIC', 'HEIF', 'GIF', 'JPEG', 'JPG', 'PNG', 'WEBP');
}

/**
 * Imagick 白名单扩展名（小写，不含点；由 imagick_format_whitelist() 派生）
 *
 * @return array
 */
function imagick_extension_whitelist()
{
    return array('avif', 'heic', 'heif', 'gif', 'jpeg', 'jpg', 'png', 'webp');
}

/**
 * 格式名是否在 Imagick 白名单内
 *
 * @param string $format
 * @return bool
 */
function imagick_format_is_whitelisted($format)
{
    return in_array(strtoupper((string) $format), imagick_format_whitelist(), true);
}

/**
 * Imagick 危险 coder 黑名单（ImageTragick 相关）
 *
 * 仅用于 queryFormats 注册表扫描与深度探测展示；真实输入防线是 imagick_validate_input() 白名单。
 *
 * @return array
 */
function imagick_coder_denylist()
{
    return array(
        'MSVG', 'MVG', 'MSL', 'URL', 'HTTP', 'HTTPS', 'FTP', 'PS', 'EPS', 'PDF', 'EPHEMERAL',
        'LABEL', 'CAPTION', 'CLIPBOARD', 'TEXT', 'SVG', 'SVGZ'
    );
}

/**
 * Imagick 拒绝的扩展名（小写，不含点）
 *
 * @return array
 */
function imagick_denied_extensions()
{
    return array('svg', 'svgz', 'pdf', 'ps', 'eps', 'mvg', 'msvg');
}

/**
 * Imagick 探测结果默认结构
 *
 * @return array
 */
function imagick_probe_defaults()
{
    return array(
        'loaded' => false,
        'usable' => false,
        'version' => '',
        'formats' => array(
            'AVIF' => false,
            'HEIC' => false,
            'GIF' => false,
            'WEBP' => false,
            'JPEG' => false,
            'PNG' => false
        ),
        'delegates' => array(
            'avif' => false,
            'heic' => false,
            'webp' => false
        ),
        'animated_gif' => false,
        'registry_risk_coders' => array(),
        'denied_coders' => array(),
        'deep_probe_at' => 0,
        'deep_probe_runtime_coders' => array(),
        'min_version_ok' => false,
        'resource_limits_applied' => false,
        'policy_applied' => false,
        'unusable_reason' => 'Imagick 扩展未加载'
    );
}

/**
 * 解析 ImageMagick 版本号
 *
 * @param string $versionString
 * @return array|null major, minor, patch, revision
 */
function imagick_parse_version_number($versionString)
{
    if (! preg_match('/ImageMagick\s+(\d+)\.(\d+)\.(\d+)(?:-(\d+))?/i', (string) $versionString, $m)) {
        return null;
    }
    return array(
        'major' => (int) $m[1],
        'minor' => (int) $m[2],
        'patch' => (int) $m[3],
        'revision' => isset($m[4]) ? (int) $m[4] : 0
    );
}

/**
 * ImageMagick 是否满足 ImageTragick 安全基线（6.9.10-23 / 7.0.8-11+）
 *
 * @param string $versionString
 * @return bool
 */
function imagick_version_meets_minimum($versionString)
{
    $v = imagick_parse_version_number($versionString);
    if ($v === null) {
        return false;
    }
    if ($v['major'] >= 7) {
        if ($v['minor'] > 0) {
            return true;
        }
        if ($v['minor'] === 0 && $v['patch'] > 8) {
            return true;
        }
        if ($v['minor'] === 0 && $v['patch'] === 8 && $v['revision'] >= 11) {
            return true;
        }
        return false;
    }
    if ($v['major'] === 6 && $v['minor'] === 9) {
        if ($v['patch'] > 10) {
            return true;
        }
        if ($v['patch'] === 10 && $v['revision'] >= 23) {
            return true;
        }
    }
    return false;
}

/**
 * 查询 ImageMagick 已注册格式（仅 trust queryFormats('*') 全量结果）
 *
 * 不使用无参 queryFormats() 降级：部分环境仅返回 JPEG/PNG 子集，无法可靠探测危险 coder。
 *
 * @return array 大写格式名 => true
 */
function imagick_query_all_formats()
{
    if (array_key_exists('__test_imagick_query_formats', $GLOBALS)) {
        $stub = $GLOBALS['__test_imagick_query_formats'];
        if (! is_array($stub)) {
            return array();
        }
        $formats = array();
        foreach ($stub as $f) {
            $formats[strtoupper((string) $f)] = true;
        }
        return $formats;
    }
    $formats = array();
    if (! extension_loaded('imagick') || ! method_exists('Imagick', 'queryFormats')) {
        return $formats;
    }
    $list = @\Imagick::queryFormats('*');
    if (! is_array($list) || empty($list)) {
        return $formats;
    }
    foreach ($list as $f) {
        $formats[strtoupper((string) $f)] = true;
    }
    return $formats;
}

/**
 * queryFormats 登记的危险 coder（fail-closed，不发起 readImage）
 *
 * @param array $allFormats imagick_query_all_formats() 结果
 * @return array
 */
function imagick_detect_registry_risk_coders(array $allFormats)
{
    $found = array();
    foreach (imagick_coder_denylist() as $coder) {
        $upper = strtoupper($coder);
        if (! empty($allFormats[$upper])) {
            $found[] = $upper;
        }
    }
    return $found;
}

/**
 * 深度探测是否允许对该 coder 执行 readImage（仅 file 类，禁止网络/spec）
 *
 * @param string $coder
 * @return bool
 */
function imagick_coder_deep_probe_eligible($coder)
{
    $spec = imagick_coder_probe_spec($coder);
    return is_array($spec) && isset($spec['type']) && $spec['type'] === 'file';
}

/**
 * 读取持久化的 Imagick 深度探测缓存（ay_config imagick_security_probe JSON）
 *
 * @return array|null version_hash（或升级前 version）, probed_at, runtime_coders, probe_mode
 */
function imagick_security_probe_read()
{
    if (array_key_exists('__test_imagick_security_probe', $GLOBALS)) {
        $stub = $GLOBALS['__test_imagick_security_probe'];
        return is_array($stub) ? $stub : null;
    }
    $raw = '';
    if (class_exists('core\\basic\\Config', false)) {
        $raw = (string) \core\basic\Config::get('imagick_security_probe');
    }
    if ($raw === '') {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/**
 * 深度探测缓存是否与当前 ImageMagick 版本匹配
 *
 * @param array $cache
 * @param string $currentVersion
 * @return bool
 */
function imagick_security_probe_cache_valid(array $cache, $currentVersion)
{
    if (empty($cache['probed_at']) || (string) $currentVersion === '') {
        return false;
    }
    $currentVersion = (string) $currentVersion;
    if (! empty($cache['version_hash'])) {
        return (string) $cache['version_hash'] === md5($currentVersion);
    }
    // 兼容升级前已成功写入的短 version 串
    return ! empty($cache['version']) && (string) $cache['version'] === $currentVersion;
}

/**
 * 编码深度探测缓存 JSON（ay_config.value 为 varchar(200)）
 *
 * @param array $data
 * @return string|false 超长或编码失败时 false
 */
function imagick_security_probe_encode(array $data)
{
    unset($data['version']);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || strlen($json) > 200) {
        return false;
    }
    return $json;
}

/**
 * 持久化深度探测结果至 ay_config 并刷新配置缓存
 *
 * @param array $data
 * @return bool
 */
function imagick_security_probe_persist(array $data)
{
    $json = imagick_security_probe_encode($data);
    if ($json === false || ! function_exists('model')) {
        return false;
    }
    $model = model('admin.system.Config');
    if (! $model) {
        return false;
    }
    if ($model->checkConfig("name='imagick_security_probe'")) {
        $ok = $model->modValue('imagick_security_probe', $json);
    } else {
        $ok = $model->addConfig(array(
            'name' => 'imagick_security_probe',
            'value' => $json,
            'type' => 2,
            'sorting' => 255,
            'description' => 'Imagick 深度安全探测缓存（JSON）'
        ));
    }
    if ($ok) {
        path_delete(RUN_PATH . '/config');
        if (function_exists('cache_config')) {
            cache_config(true);
        }
    }
    return (bool) $ok;
}

/**
 * 危险 coder 运行时可读性探测规格（最小 payload，非 exploit）
 *
 * @param string $coder
 * @return array|null type=file|spec, ext/bytes 或 value
 */
function imagick_coder_probe_spec($coder)
{
    switch (strtoupper((string) $coder)) {
        case 'MVG':
        case 'MSVG':
            return array(
                'type' => 'file',
                'ext' => 'mvg',
                'bytes' => "@begin mvg\n@end mvg\n"
            );
        case 'PDF':
            return array(
                'type' => 'file',
                'ext' => 'pdf',
                'bytes' => '%PDF-1.4 probe'
            );
        case 'PS':
        case 'EPS':
            return array(
                'type' => 'file',
                'ext' => 'ps',
                'bytes' => '%!PS-Adobe-3.0 probe'
            );
        case 'SVG':
        case 'SVGZ':
            return array(
                'type' => 'file',
                'ext' => 'svg',
                'bytes' => '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"></svg>'
            );
        case 'HTTPS':
        case 'HTTP':
        case 'FTP':
        case 'URL':
            return array(
                'type' => 'spec',
                'value' => 'https://127.0.0.1/im-probe-' . uniqid('', true)
            );
        case 'MSL':
            return array(
                'type' => 'file',
                'ext' => 'msl',
                'bytes' => '<?xml version="1.0" encoding="UTF-8"?><msl:image></msl:image>'
            );
        case 'LABEL':
            return array('type' => 'spec', 'value' => 'label:probe');
        case 'CAPTION':
            return array('type' => 'spec', 'value' => 'caption:probe');
        case 'TEXT':
            return array('type' => 'spec', 'value' => 'text:probe');
        case 'CLIPBOARD':
            return array('type' => 'spec', 'value' => 'clipboard:');
        case 'EPHEMERAL':
            return array('type' => 'spec', 'value' => 'ephemeral:probe');
        default:
            return null;
    }
}

/**
 * 探测危险 coder 是否仍可读（policy 禁用时 readImage 失败；IM7 queryFormats 仍可能登记）
 *
 * @param string $coder
 * @return bool true=仍可读（不安全）
 */
function imagick_probe_coder_readable($coder)
{
    if (array_key_exists('__test_imagick_coder_readable', $GLOBALS)) {
        $stub = $GLOBALS['__test_imagick_coder_readable'];
        if (is_array($stub)) {
            $key = strtoupper((string) $coder);
            if (array_key_exists($key, $stub)) {
                return (bool) $stub[$key];
            }
        }
    }
    if (! extension_loaded('imagick') || ! class_exists('Imagick', false)) {
        return false;
    }
    $spec = imagick_coder_probe_spec($coder);
    // 仅 file 类 coder 允许 readImage；网络/spec 类永不主动探测
    if ($spec === null || ! isset($spec['type']) || $spec['type'] !== 'file') {
        return false;
    }
    $probePath = '';
    try {
        if (! imagick_apply_security_policy()) {
            return false;
        }
        $im = new \Imagick();
        imagick_apply_instance_options($im);
        $probePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'improbe_'
            . uniqid('', true) . '.' . $spec['ext'];
        if (@file_put_contents($probePath, $spec['bytes']) === false) {
            return false;
        }
        @$im->readImage($probePath);
        if (method_exists($im, 'clear')) {
            $im->clear();
        }
        if (method_exists($im, 'destroy')) {
            $im->destroy();
        }
        return true;
    } catch (\Throwable $e) {
        return false;
    } finally {
        if ($probePath !== '' && is_file($probePath)) {
            @unlink($probePath);
        }
    }
}

/**
 * 深度探测：file 类危险 coder 运行时可读（显式触发，非热路径）
 *
 * @param array|null $allFormats imagick_query_all_formats() 结果，可传入以避免重复查询
 * @return array
 */
function imagick_detect_runtime_readable_coders($allFormats = null)
{
    if ($allFormats === null) {
        $allFormats = imagick_query_all_formats();
    }
    $found = array();
    foreach (imagick_coder_denylist() as $coder) {
        $upper = strtoupper($coder);
        if (empty($allFormats[$upper]) || ! imagick_coder_deep_probe_eligible($coder)) {
            continue;
        }
        if (imagick_probe_coder_readable($coder)) {
            $found[] = $upper;
        }
    }
    return $found;
}

/**
 * @deprecated 请用 imagick_detect_registry_risk_coders（热路径）或 imagick_detect_runtime_readable_coders（深度探测）
 */
function imagick_detect_denied_coders($allFormats = null)
{
    return imagick_detect_runtime_readable_coders($allFormats);
}

/**
 * 进程级 Imagick 资源限制（请求内幂等；不能替代 policy.xml coder/delegate 禁用）
 *
 * @return bool
 */
function imagick_apply_security_policy()
{
    if (array_key_exists('__test_imagick_policy_applied', $GLOBALS)) {
        return (bool) $GLOBALS['__test_imagick_policy_applied'];
    }
    static $applied = false;
    static $ok = false;
    if ($applied) {
        return $ok;
    }
    $applied = true;
    if (! extension_loaded('imagick') || ! class_exists('Imagick', false)) {
        return false;
    }
    try {
        $phpMem = parse_memory_limit_bytes();
        $imMem = (int) min(256 * 1024 * 1024, $phpMem * 0.25);
        if ($imMem < 32 * 1024 * 1024) {
            $imMem = 32 * 1024 * 1024;
        }
        if (defined('Imagick::RESOURCETYPE_MEMORY')) {
            @\Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, $imMem);
        }
        if (defined('Imagick::RESOURCETYPE_MAP')) {
            @\Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MAP, $imMem);
        }
        if (defined('Imagick::RESOURCETYPE_DISK')) {
            @\Imagick::setResourceLimit(\Imagick::RESOURCETYPE_DISK, 512 * 1024 * 1024);
        }
        if (defined('Imagick::RESOURCETYPE_TIME')) {
            @\Imagick::setResourceLimit(\Imagick::RESOURCETYPE_TIME, 120);
        }
        if (defined('Imagick::RESOURCETYPE_AREA')) {
            @\Imagick::setResourceLimit(\Imagick::RESOURCETYPE_AREA, 64 * 1024 * 1024);
        }
        if (defined('Imagick::RESOURCETYPE_WIDTH')) {
            @\Imagick::setResourceLimit(\Imagick::RESOURCETYPE_WIDTH, 8192);
        }
        if (defined('Imagick::RESOURCETYPE_HEIGHT')) {
            @\Imagick::setResourceLimit(\Imagick::RESOURCETYPE_HEIGHT, 8192);
        }
        $tmp = new \Imagick();
        imagick_apply_instance_options($tmp);
        if (method_exists($tmp, 'clear')) {
            $tmp->clear();
        }
        if (method_exists($tmp, 'destroy')) {
            $tmp->destroy();
        }
        $ok = true;
    } catch (\Throwable $e) {
        $ok = false;
    }
    return $ok;
}

/**
 * 实例级格式解析加固 setOption（纵深防御；不能禁用 coder/delegate，须配置 policy.xml）
 *
 * @param \Imagick $im
 */
function imagick_apply_instance_options($im)
{
    if (! is_object($im) || ! method_exists($im, 'setOption')) {
        return;
    }
    $options = array(
        'svg:xml-parse-huge' => 'false',
        'pdf:use-cropbox' => 'false',
        'ps:use-trimbox' => 'false',
        'mvg:vector-graphics' => 'false'
    );
    foreach ($options as $key => $value) {
        try {
            @$im->setOption($key, $value);
        } catch (\Throwable $e) {
            // 部分 ImageMagick 版本不支持对应 option，忽略
        }
    }
}

/**
 * 根据探测结果计算 usable / unusable_reason
 *
 * @param array $result
 * @param array|null $allFormats imagick_query_all_formats() 结果；null 时内部查询一次
 * @return array
 */
function imagick_probe_finalize(array $result, $allFormats = null)
{
    if (empty($result['loaded'])) {
        $result['usable'] = false;
        if (empty($result['unusable_reason'])) {
            $result['unusable_reason'] = 'Imagick 扩展未加载';
        }
        return $result;
    }

    if ($allFormats === null) {
        $allFormats = imagick_query_all_formats();
    }
    if (! is_array($allFormats)) {
        $allFormats = array();
    }
    if (! empty($allFormats)) {
        $result['registry_risk_coders'] = imagick_detect_registry_risk_coders($allFormats);
    } else {
        $result['registry_risk_coders'] = array();
    }
    // 向后兼容：denied_coders 与 registry_risk_coders 同义（热路径 fail-closed）
    $result['denied_coders'] = $result['registry_risk_coders'];
    $result['min_version_ok'] = imagick_version_meets_minimum(isset($result['version']) ? $result['version'] : '');
    $result['resource_limits_applied'] = imagick_apply_security_policy();
    // 向后兼容：policy_applied 仅表示资源限制已应用，不代表 policy.xml 已配置
    $result['policy_applied'] = $result['resource_limits_applied'];

    $versionStr = isset($result['version']) ? (string) $result['version'] : '';
    $deepCache = imagick_security_probe_read();
    if ($deepCache && imagick_security_probe_cache_valid($deepCache, $versionStr)) {
        $result['deep_probe_at'] = (int) $deepCache['probed_at'];
        $result['deep_probe_runtime_coders'] = isset($deepCache['runtime_coders']) && is_array($deepCache['runtime_coders'])
            ? $deepCache['runtime_coders']
            : array();
    } else {
        $result['deep_probe_at'] = 0;
        $result['deep_probe_runtime_coders'] = array();
    }

    $reasons = array();
    if (empty($allFormats)) {
        $reasons[] = '无法确认 ImageMagick coder 注册表（queryFormats 无结果）';
    }
    if (empty($result['min_version_ok'])) {
        $reasons[] = 'ImageMagick 版本低于安全基线（需 ≥6.9.10-23 或 ≥7.0.8-11）';
    }
    if (! empty($result['registry_risk_coders'])) {
        $reasons[] = 'queryFormats 仍登记危险 coder，请在 policy.xml 中禁用：'
            . implode(',', $result['registry_risk_coders']);
    }
    if (empty($result['formats']['JPEG']) || empty($result['formats']['PNG'])) {
        $reasons[] = '缺少 JPEG/PNG 基础格式支持';
    }
    if (empty($result['resource_limits_applied'])) {
        $reasons[] = 'Imagick 资源限制未能应用';
    }

    $result['usable'] = empty($reasons);
    $result['unusable_reason'] = $result['usable'] ? '' : implode('；', $reasons);
    return $result;
}

/**
 * Imagick 是否通过安全门闸（extension_loaded ≠ usable）
 *
 * @return bool
 */
function imagick_is_usable()
{
    if (array_key_exists('__test_imagick_usable', $GLOBALS)) {
        return (bool) $GLOBALS['__test_imagick_usable'];
    }
    $probe = imagick_probe();
    return ! empty($probe['usable']);
}

/**
 * Imagick 不可用的原因（空串表示可用）
 *
 * @return string
 */
function imagick_usable_reason()
{
    if (array_key_exists('__test_imagick_usable_reason', $GLOBALS)) {
        return (string) $GLOBALS['__test_imagick_usable_reason'];
    }
    $probe = imagick_probe();
    return isset($probe['unusable_reason']) ? (string) $probe['unusable_reason'] : '';
}

/**
 * 检测文件头是否含 SVG/PDF/PS/MVG 等危险魔数
 *
 * @param string $head 文件头字节
 * @return bool true=危险
 */
function imagick_detect_dangerous_magic($head)
{
    if ($head === '' || $head === false) {
        return false;
    }
    $sample = strtolower(substr($head, 0, 512));
    if (strncmp($head, '%PDF-', 5) === 0) {
        return true;
    }
    if (strncmp($head, '%!PS', 4) === 0) {
        return true;
    }
    if (strpos($sample, '<svg') !== false || strpos($sample, '<?xml') !== false) {
        return true;
    }
    if (strpos($sample, '@begin') !== false && strpos($sample, 'mvg') !== false) {
        return true;
    }
    return false;
}

/**
 * 由文件头魔数识别白名单格式名（无法识别时返回 null）
 *
 * @param string $head 文件头字节
 * @return string|null 大写格式名
 */
function imagick_magic_identify($head)
{
    if ($head === '' || $head === false || strlen($head) < 4) {
        return null;
    }
    if (strncmp($head, 'GIF87a', 6) === 0 || strncmp($head, 'GIF89a', 6) === 0) {
        return 'GIF';
    }
    if (strncmp($head, "\xFF\xD8\xFF", 3) === 0) {
        return 'JPEG';
    }
    if (strlen($head) >= 8 && strncmp($head, "\x89PNG\r\n\x1a\n", 8) === 0) {
        return 'PNG';
    }
    if (strlen($head) >= 12 && strncmp($head, 'RIFF', 4) === 0 && strncmp(substr($head, 8, 4), 'WEBP', 4) === 0) {
        return 'WEBP';
    }
    if (strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp') {
        $avifBrands = array('avif', 'avis');
        $heicBrands = array('heic', 'heix', 'hevc', 'hevx', 'heim', 'heis');
        $heifBrands = array('mif1', 'msf1');
        $brands = array();
        $brands[] = strtolower(substr($head, 8, 4));
        for ($i = 16; $i + 4 <= min(strlen($head), 64); $i += 4) {
            $brands[] = strtolower(substr($head, $i, 4));
        }
        foreach ($brands as $brand) {
            if (in_array($brand, $avifBrands, true)) {
                return 'AVIF';
            }
            if (in_array($brand, $heicBrands, true)) {
                return 'HEIC';
            }
            if (in_array($brand, $heifBrands, true)) {
                return 'HEIF';
            }
        }
    }
    return null;
}

/**
 * getimagesize() 的 IMAGETYPE_* 映射为白名单格式名
 *
 * @param int $type
 * @return string|null
 */
function imagick_imagetype_to_format($type)
{
    $type = (int) $type;
    switch ($type) {
        case IMAGETYPE_GIF:
            return 'GIF';
        case IMAGETYPE_JPEG:
            return 'JPEG';
        case IMAGETYPE_PNG:
            return 'PNG';
    }
    if ((int) $type === image_type_webp()) {
        return 'WEBP';
    }
    if (defined('IMAGETYPE_AVIF') && $type === IMAGETYPE_AVIF) {
        return 'AVIF';
    }
    if (defined('IMAGETYPE_HEIC') && $type === IMAGETYPE_HEIC) {
        return 'HEIC';
    }
    if (defined('IMAGETYPE_HEIF') && $type === IMAGETYPE_HEIF) {
        return 'HEIF';
    }
    return null;
}

/**
 * IMAGETYPE_* 是否在 Imagick 白名单内
 *
 * @param int $type
 * @return bool
 */
function imagick_type_is_whitelisted($type)
{
    $format = imagick_imagetype_to_format($type);
    return $format !== null && imagick_format_is_whitelisted($format);
}

/**
 * 白名单格式名对应的扩展名（小写）
 *
 * @param string $format
 * @return array
 */
function imagick_format_to_extensions($format)
{
    switch (strtoupper((string) $format)) {
        case 'JPEG':
        case 'JPG':
            return array('jpg', 'jpeg');
        case 'PNG':
            return array('png');
        case 'GIF':
            return array('gif');
        case 'WEBP':
            return array('webp');
        case 'AVIF':
            return array('avif');
        case 'HEIC':
            return array('heic');
        case 'HEIF':
            return array('heif');
        default:
            return array();
    }
}

/**
 * 两个白名单格式名是否可视为同一图像（JPEG/JPG、HEIC/HEIF 互通）
 *
 * @param string $formatA
 * @param string $formatB
 * @return bool
 */
function imagick_formats_compatible($formatA, $formatB)
{
    $a = strtoupper((string) $formatA);
    $b = strtoupper((string) $formatB);
    if ($a === $b) {
        return true;
    }
    if (($a === 'JPEG' && $b === 'JPG') || ($a === 'JPG' && $b === 'JPEG')) {
        return true;
    }
    if (($a === 'HEIC' && $b === 'HEIF') || ($a === 'HEIF' && $b === 'HEIC')) {
        return true;
    }
    return false;
}

/**
 * 扩展名是否与格式一致（HEIC/HEIF 扩展名互通）
 *
 * @param string $ext 小写扩展名
 * @param string $format 大写格式名
 * @return bool
 */
function imagick_extension_matches_format($ext, $format)
{
    $ext = strtolower((string) $ext);
    if ($ext === '') {
        return false;
    }
    if (in_array($ext, imagick_format_to_extensions($format), true)) {
        return true;
    }
    $fmt = strtoupper((string) $format);
    if (in_array($fmt, array('HEIC', 'HEIF'), true) && in_array($ext, array('heic', 'heif'), true)) {
        return true;
    }
    return false;
}

/**
 * getimagesize() 无法识别的格式是否允许仅凭魔数+扩展名放行
 *
 * @param string $format 大写格式名
 * @return bool
 */
function imagick_format_opaque_to_php($format)
{
    return in_array(strtoupper((string) $format), array('HEIC', 'HEIF', 'AVIF'), true);
}

/**
 * ImageMagick policy.xml 注册表状态摘要（供系统信息展示）
 *
 * @param array $probe imagick_probe() 结果
 * @return string
 */
function imagick_policy_registry_status(array $probe)
{
    $risk = isset($probe['registry_risk_coders']) && is_array($probe['registry_risk_coders'])
        ? $probe['registry_risk_coders']
        : array();
    if (empty($risk)) {
        return '注册表无危险 coder（建议在 policy.xml 中显式禁用 URL/MSL/PDF/SVG 等 coder）';
    }
    return '仍有 ' . count($risk) . ' 个危险 coder 登记：' . implode(',', $risk) . '；请在 policy.xml 中禁用';
}

/**
 * Imagick 输入校验（fail-closed 白名单；SVG/PDF/PS/MVG/URL 一律拒绝）
 *
 * @param string $path
 * @param mixed $hintFormat 预留
 * @return bool|string true=通过，string=错误信息
 */
function imagick_validate_input($path, $hintFormat = null)
{
    unset($hintFormat);
    if ($path === '') {
        return true;
    }
    if (preg_match('#^(https?|ftp)://#i', $path)) {
        return 'Imagick 拒绝远程 URL 输入';
    }
    $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    if ($ext !== '' && in_array($ext, imagick_denied_extensions(), true)) {
        return 'Imagick 拒绝处理 ' . strtoupper($ext) . ' 格式';
    }
    $canonical = realpath($path);
    if ($canonical === false || ! is_file($canonical) || ! is_readable($canonical)) {
        return '图片文件不可读';
    }
    $head = @file_get_contents($canonical, false, null, 0, 512);
    if ($head === false) {
        return '图片文件不可读';
    }
    if (imagick_detect_dangerous_magic($head)) {
        return 'Imagick 拒绝危险文件内容';
    }
    $magicFmt = imagick_magic_identify($head);
    $info = @getimagesize($canonical);
    if ($info && ! empty($info[2])) {
        if (! imagick_type_is_whitelisted($info[2])) {
            return 'Imagick 拒绝非白名单图片类型';
        }
        $typeFmt = imagick_imagetype_to_format($info[2]);
        if ($typeFmt === null) {
            return 'Imagick 无法确认图片类型，拒绝处理';
        }
        if ($ext === '' || ! in_array($ext, imagick_extension_whitelist(), true)) {
            return 'Imagick 无法确认图片类型，拒绝处理';
        }
        if (! imagick_extension_matches_format($ext, $typeFmt)) {
            return 'Imagick 无法确认图片类型，拒绝处理';
        }
        if ($magicFmt !== null) {
            if (! imagick_format_is_whitelisted($magicFmt)) {
                return 'Imagick 拒绝危险文件内容';
            }
            if (! imagick_formats_compatible($typeFmt, $magicFmt)) {
                return 'Imagick 无法确认图片类型，拒绝处理';
            }
        }
        return true;
    }
    $extOk = ($ext !== '' && in_array($ext, imagick_extension_whitelist(), true));
    $magicOk = ($magicFmt !== null && imagick_format_is_whitelisted($magicFmt));
    if (! $extOk || ! $magicOk) {
        return 'Imagick 无法确认图片类型，拒绝处理';
    }
    if (! imagick_extension_matches_format($ext, $magicFmt)) {
        return 'Imagick 无法确认图片类型，拒绝处理';
    }
    if (! imagick_format_opaque_to_php($magicFmt)) {
        return 'Imagick 无法确认图片类型，拒绝处理';
    }
    return true;
}

/**
 * Imagick 唯一工厂入口（禁止业务代码直接 new Imagick）
 *
 * @param string $path 本地路径，空串则仅创建空实例
 * @param mixed $hintFormat 预留
 * @return array array(?Imagick, string) 成功时 error 为空串
 */
function imagick_create($path = '', $hintFormat = null)
{
    if (! imagick_is_usable()) {
        $reason = imagick_usable_reason();
        if ($reason === '') {
            $reason = '未知原因';
        }
        return array(null, 'Imagick 不可用：' . $reason);
    }
    if (! imagick_apply_security_policy()) {
        return array(null, 'Imagick 资源限制未能应用');
    }
    if ($path !== '' && preg_match('#^(https?|ftp)://#i', $path)) {
        $valid = imagick_validate_input($path, $hintFormat);
        return array(null, $valid === true ? 'Imagick 拒绝远程 URL 输入' : (string) $valid);
    }
    $canonical = '';
    if ($path !== '') {
        $canonical = realpath($path);
        if ($canonical === false) {
            return array(null, '图片文件不可读');
        }
    }
    $valid = imagick_validate_input($canonical !== '' ? $canonical : $path, $hintFormat);
    if ($valid !== true) {
        return array(null, (string) $valid);
    }
    try {
        $im = new \Imagick();
        imagick_apply_instance_options($im);
        if ($canonical !== '') {
            $fh = @fopen($canonical, 'rb');
            if ($fh === false) {
                return array(null, '图片文件不可读');
            }
            try {
                if (method_exists($im, 'readImageFile')) {
                    $im->readImageFile($fh);
                } else {
                    $im->readImage($canonical);
                }
            } finally {
                fclose($fh);
            }
        }
        return array($im, '');
    } catch (\Throwable $e) {
        return array(null, 'Imagick 初始化失败');
    }
}

/**
 * coalesceImages 前资源守卫（动图帧数/像素面积）
 *
 * @param \Imagick $im
 * @return string|null null=通过，string=拒绝原因
 */
function imagick_guard_before_coalesce($im)
{
    if (! is_object($im) || ! method_exists($im, 'getNumberImages')) {
        return '无效的 Imagick 实例';
    }
    $maxFrames = 100;
    if (array_key_exists('__test_imagick_max_gif_frames', $GLOBALS)) {
        $maxFrames = (int) $GLOBALS['__test_imagick_max_gif_frames'];
    }
    $frames = (int) $im->getNumberImages();
    if ($frames > $maxFrames) {
        return 'GIF 帧数超过限制（' . $frames . '>' . $maxFrames . '）';
    }
    $maxArea = 64 * 1024 * 1024;
    for ($i = 0; $i < $frames; $i++) {
        $im->setIteratorIndex($i);
        $w = (int) $im->getImageWidth();
        $h = (int) $im->getImageHeight();
        if ($w <= 0 || $h <= 0) {
            continue;
        }
        if ($w > 8192 || $h > 8192) {
            return 'GIF 单帧尺寸超过限制';
        }
        if ((float) $w * (float) $h > $maxArea) {
            return 'GIF 单帧像素面积超过限制';
        }
    }
    $im->setIteratorIndex(0);
    return null;
}

/**
 * 执行 Imagick 深度安全探测（file 类 coder readImage，禁止网络请求）
 *
 * @return array|null version_hash, probed_at, runtime_coders, probe_mode；无 Imagick 时 null
 */
function imagick_probe_deep_run()
{
    if (! extension_loaded('imagick') || ! class_exists('Imagick', false)) {
        return null;
    }
    $version = '';
    try {
        if (method_exists('Imagick', 'getVersion')) {
            $ver = \Imagick::getVersion();
            if (is_array($ver) && ! empty($ver['versionString'])) {
                $version = (string) $ver['versionString'];
            } elseif (is_string($ver)) {
                $version = $ver;
            }
        }
    } catch (\Throwable $e) {
        return null;
    }
    $formats = imagick_query_all_formats();
    return array(
        'version_hash' => md5($version),
        'probed_at' => time(),
        'runtime_coders' => imagick_detect_runtime_readable_coders($formats),
        'probe_mode' => 'file_only'
    );
}

/**
 * 深度探测并持久化至 ay_config（管理员显式触发）
 *
 * @return array|null 探测结果；失败时 null
 */
function imagick_probe_deep_and_persist()
{
    $data = imagick_probe_deep_run();
    if ($data === null) {
        return null;
    }
    image_backend_clear_probe_cache();
    if (! imagick_security_probe_persist($data)) {
        return null;
    }
    return $data;
}

/**
 * Imagick 能力探测（请求内缓存；仅内省 + registry fail-closed，不主动 readImage）
 *
 * @return array loaded, usable, version, formats, delegates, animated_gif, denied_coders, ...
 */
function imagick_probe()
{
    if (array_key_exists('__test_imagick_probe', $GLOBALS)) {
        return $GLOBALS['__test_imagick_probe'];
    }
    if (isset($GLOBALS['__imagick_probe_cache']) && is_array($GLOBALS['__imagick_probe_cache'])) {
        return $GLOBALS['__imagick_probe_cache'];
    }

    $result = imagick_probe_defaults();

    if (! extension_loaded('imagick') || ! class_exists('Imagick', false)) {
        $GLOBALS['__imagick_probe_cache'] = $result;
        return $result;
    }

    $result['loaded'] = true;
    $result['unusable_reason'] = '';
    $formats = array();
    try {
        if (method_exists('Imagick', 'getVersion')) {
            $ver = \Imagick::getVersion();
            if (is_array($ver) && ! empty($ver['versionString'])) {
                $result['version'] = (string) $ver['versionString'];
            } elseif (is_string($ver)) {
                $result['version'] = $ver;
            }
        }

        $formats = imagick_query_all_formats();
        foreach (array_keys($result['formats']) as $name) {
            $result['formats'][$name] = ! empty($formats[$name]);
        }
        // HEIC 在部分 ImageMagick 中登记为 HEIF
        if (! $result['formats']['HEIC'] && ! empty($formats['HEIF'])) {
            $result['formats']['HEIC'] = true;
        }

        $delegatesRaw = '';
        if (method_exists('Imagick', 'queryConfigureOption')) {
            $delegatesRaw = (string) @\Imagick::queryConfigureOption('DELEGATES');
        }
        $delegatesLower = strtolower($delegatesRaw);
        $result['delegates']['avif'] = (strpos($delegatesLower, 'avif') !== false) || ! empty($result['formats']['AVIF']);
        $result['delegates']['heic'] = (strpos($delegatesLower, 'heic') !== false)
            || (strpos($delegatesLower, 'heif') !== false)
            || ! empty($result['formats']['HEIC']);
        $result['delegates']['webp'] = (strpos($delegatesLower, 'webp') !== false) || ! empty($result['formats']['WEBP']);

        // 动图：具备 GIF 格式且支持多帧合并接口（本阶段仅探测，不接管处理）
        $result['animated_gif'] = ! empty($result['formats']['GIF']) && method_exists('Imagick', 'coalesceImages');
    } catch (\Throwable $e) {
        // 探测失败时保留 loaded=true、其余能力为 false，避免中断后台页
    }

    $result = imagick_probe_finalize($result, $formats);
    $GLOBALS['__imagick_probe_cache'] = $result;
    return $result;
}

/**
 * GD + Imagick 能力矩阵（请求内缓存；测试可通过 $GLOBALS['__test_image_capability_matrix'] 覆盖）
 *
 * @return array
 */
function image_capability_matrix()
{
    if (array_key_exists('__test_image_capability_matrix', $GLOBALS)) {
        return $GLOBALS['__test_image_capability_matrix'];
    }
    if (isset($GLOBALS['__image_capability_matrix_cache']) && is_array($GLOBALS['__image_capability_matrix_cache'])) {
        return $GLOBALS['__image_capability_matrix_cache'];
    }

    $im = imagick_probe();
    $imUsable = ! empty($im['usable']);
    $gdAvif = gd_supports_avif();
    $matrix = array(
        'gd' => array(
            'loaded' => extension_loaded('gd'),
            'jpeg' => gd_supports_jpeg(),
            'webp' => gd_supports_webp(),
            'freetype' => gd_supports_freetype(),
            'gif' => function_exists('imagecreatefromgif'),
            'avif' => $gdAvif,
            'heic' => false,
            'animated_gif' => false
        ),
        'imagick' => $im,
        'avif' => array(
            'gd' => $gdAvif,
            'imagick' => $imUsable && ! empty($im['formats']['AVIF'])
        ),
        'heic' => array(
            'gd' => false,
            'imagick' => $imUsable && ! empty($im['formats']['HEIC'])
        ),
        'animated_gif' => array(
            'gd' => false,
            'imagick' => $imUsable && ! empty($im['animated_gif'])
        ),
        'webp' => array(
            'gd' => gd_supports_webp(),
            'imagick' => $imUsable && ! empty($im['formats']['WEBP'])
        )
    );

    $GLOBALS['__image_capability_matrix_cache'] = $matrix;
    return $matrix;
}

/**
 * 读取 image_backend 配置，缺省 / 非法值视为 auto
 *
 * @return string auto|gd_only|prefer_imagick
 */
function image_backend_config()
{
    $mode = '';
    if (class_exists('core\\basic\\Config', false)) {
        $mode = (string) \core\basic\Config::get('image_backend');
    }
    $mode = strtolower(trim($mode));
    if ($mode === '' || ! in_array($mode, array('auto', 'gd_only', 'prefer_imagick'), true)) {
        return 'auto';
    }
    return $mode;
}

/**
 * 高层窄分派：返回 gd|imagick（不改 gd_load_image 语义、不做多态）
 *
 * AVIF：按能力择优。gd_only 强制 GD（不能解码则由调用方降级）；
 * prefer_imagick 在 Imagick 安全可用且支持 AVIF 时走 imagick，否则 GD；
 * auto 优先 GD，GD 不能时收 Imagick。
 * 其余格式仍 phase-1：prefer_imagick 仅记录意图，实际仍走 GD。
 *
 * @param string $op 操作名（resize/watermark/reencode/info）
 * @param mixed $type 图片类型或扩展名
 * @return string gd|imagick
 */
function image_backend_for($op, $type = null)
{
    if (array_key_exists('__test_image_backend_for', $GLOBALS)) {
        return (string) $GLOBALS['__test_image_backend_for'];
    }
    unset($op);

    $mode = image_backend_config();
    if ($mode === 'gd_only') {
        return 'gd';
    }

    if (image_type_arg_is_avif($type)) {
        $matrix = image_capability_matrix();
        $gdOk = gd_avif_gd_available();
        $imOk = ! empty($matrix['avif']['imagick']);
        if ($mode === 'prefer_imagick') {
            return $imOk ? 'imagick' : 'gd';
        }
        if ($gdOk) {
            return 'gd';
        }
        if ($imOk) {
            return 'imagick';
        }
        return 'gd';
    }

    if ($mode === 'prefer_imagick') {
        $probe = imagick_probe();
        if (! empty($probe['usable'])) {
            return 'imagick';
        }
        return 'gd';
    }
    return 'gd';
}

/**
 * 当前实际处理路径（供系统信息页展示）
 *
 * 与 image_backend_for() 的「分派意图」区分：JPEG/PNG 等主路径仍走 GD；
 * 仅 AVIF 在能力允许时走 Imagick，系统信息主路径仍报 gd，能力见 image_cap_avif。
 *
 * @return string gd|imagick
 */
function image_backend_effective()
{
    // 主路径仍为 GD；AVIF 择优见 image_backend_for('reencode', 'avif')
    return 'gd';
}

/**
 * 按 GIF 块边界扫描（不依赖 GD）
 * 成功返回 array(frames, gce, trailer_pos, safe)，失败返回 false
 */
function gif_scan_bytes($data)
{
    $len = strlen($data);
    if ($len < 14) {
        return false;
    }
    $sig = substr($data, 0, 6);
    if ($sig !== 'GIF87a' && $sig !== 'GIF89a') {
        return false;
    }

    // Logical Screen Descriptor：签名后 7 字节；packed 决定是否有全局调色板
    $packed = ord($data[10]);
    $pos = 13;
    if ($packed & 0x80) {
        $gctSize = 3 * (1 << (($packed & 0x07) + 1));
        $pos += $gctSize;
        if ($pos > $len) {
            return false;
        }
    }

    $frames = 0;
    $gce = 0;
    $trailerPos = null;

    while ($pos < $len) {
        $block = ord($data[$pos]);
        if ($block === 0x3B) {
            // 块边界上的第一个 Trailer，其后即为附加数据（GIFAR 检测以此为准）
            $trailerPos = $pos;
            break;
        }
        if ($block === 0x21) {
            // Extension：label + 若干 data sub-block，以 0 长度块结束
            if ($pos + 2 > $len) {
                return false;
            }
            $label = ord($data[$pos + 1]);
            if ($label === 0xF9) {
                $gce++;
            }
            $pos += 2;
            while ($pos < $len) {
                $subLen = ord($data[$pos]);
                $pos++;
                if ($subLen === 0) {
                    break;
                }
                $pos += $subLen;
                if ($pos > $len) {
                    return false;
                }
            }
            continue;
        }
        if ($block === 0x2C) {
            // Image Descriptor(10) + 可选局部调色板 + LZW 最小码长 + image data sub-blocks
            if ($pos + 10 > $len) {
                return false;
            }
            $frames++;
            $localPacked = ord($data[$pos + 9]);
            $pos += 10;
            if ($localPacked & 0x80) {
                $lctSize = 3 * (1 << (($localPacked & 0x07) + 1));
                $pos += $lctSize;
                if ($pos > $len) {
                    return false;
                }
            }
            if ($pos >= $len) {
                return false;
            }
            $pos++; // LZW minimum code size
            while ($pos < $len) {
                $subLen = ord($data[$pos]);
                $pos++;
                if ($subLen === 0) {
                    break;
                }
                $pos += $subLen;
                if ($pos > $len) {
                    return false;
                }
            }
            continue;
        }
        return false;
    }

    if ($trailerPos === null || $frames < 1) {
        return false;
    }
    // 允许极少填充；拒绝 trailer 后大块附加数据
    $after = $len - $trailerPos - 1;
    return array(
        'frames' => $frames,
        'gce' => $gce,
        'trailer_pos' => $trailerPos,
        'safe' => ($after <= 16)
    );
}

// 读取并扫描 GIF；非 GIF / 无法解析时返回 false
function gif_scan_file($path)
{
    if (! is_file($path) || ! is_readable($path)) {
        return false;
    }
    $info = @getimagesize($path);
    if (! $info || (int) $info[2] !== IMAGETYPE_GIF) {
        return false;
    }
    $data = @file_get_contents($path);
    if ($data === false) {
        return false;
    }
    return gif_scan_bytes($data);
}

// 是否为动画 GIF：块边界上 Image Descriptor 数 > 1（避免 LZW/注释里的伪 GCE 误判）
function is_animated_gif($path)
{
    $scan = gif_scan_file($path);
    return $scan !== false && $scan['frames'] > 1;
}

// 轻量校验 GIF 结构（块遍历定位真正 trailer，补偿跳过重编码时的剥离能力）
function gif_structure_is_safe($path)
{
    $scan = gif_scan_file($path);
    return $scan !== false && ! empty($scan['safe']);
}

// 当前环境是否可用 GD 对该图片做重编码/缩放/水印
function gd_can_post_process_image($path)
{
    if (! is_file($path)) {
        return array(false, '');
    }
    $info = @getimagesize($path);
    if (! $info || empty($info[2])) {
        return array(false, '');
    }
    if ($info[2] == image_type_webp() && ! gd_webp_gd_available()) {
        return array(false, '当前环境不支持 WebP 缩放/水印，已保留原图');
    }
    if ($info[2] == image_type_avif() && ! gd_avif_gd_available()) {
        return array(false, '当前环境不支持 AVIF 缩放/水印，已保留原图');
    }
    // GD 不支持多帧 GIF，动画必须跳过重编码/缩放/水印以免压成静态图
    if ((int) $info[2] === IMAGETYPE_GIF && is_animated_gif($path)) {
        return array(false, '动画GIF已保留原图（跳过缩放/水印）');
    }
    return array(true, '');
}

// 上传后处理跳过原因（供单测断言 WebP/GIF 等保留原图路径）
function upload_post_process_last_notice()
{
    return isset($GLOBALS['_upload_post_process_notice']) ? (string) $GLOBALS['_upload_post_process_notice'] : '';
}

// 从文件创建 GD 图像资源
function image_create_from_file($path, $type)
{
    switch ($type) {
        case IMAGETYPE_GIF:
            return @imagecreatefromgif($path);
        case IMAGETYPE_JPEG:
            return @imagecreatefromjpeg($path);
        case IMAGETYPE_PNG:
            return @imagecreatefrompng($path);
        case IMAGETYPE_BMP:
            return function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($path) : false;
        default:
            if ($type == image_type_webp()) {
                return gd_supports_webp() ? @imagecreatefromwebp($path) : false;
            }
            if ($type == image_type_avif()) {
                return gd_supports_avif() ? @imagecreatefromavif($path) : false;
            }
            return false;
    }
}

// 将 GD 图像资源写入文件
function image_save_to_file($img, $path, $type, $img_quality = 90)
{
    switch ($type) {
        case IMAGETYPE_GIF:
            // imagegif 仅接受 (image, filename)
            return imagegif($img, $path);
        case IMAGETYPE_JPEG:
            return imagejpeg($img, $path, $img_quality);
        case IMAGETYPE_PNG:
            return imagepng($img, $path, $img_quality / 10);
        case IMAGETYPE_BMP:
            return function_exists('imagebmp') ? imagebmp($img, $path) : false;
        default:
            if ($type == image_type_webp()) {
                return gd_supports_webp() ? imagewebp($img, $path, $img_quality) : false;
            }
            if ($type == image_type_avif()) {
                return gd_supports_avif() ? imageavif($img, $path, $img_quality) : false;
            }
            return imagejpeg($img, $path, $img_quality);
    }
}

// 为缩放/裁剪画布按类型正确设置透明通道（PNG/WebP/AVIF 保留完整 Alpha，GIF 沿用调色板透明色）
function gd_prepare_canvas_transparency($canvas, $type, $src_img = null)
{
    if ($type == IMAGETYPE_PNG || $type == image_type_webp() || $type == image_type_avif()) {
        // 关闭混合、开启存储 Alpha，用带 Alpha 的全透明色填充，保留半透明边缘
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
    } elseif ($type == IMAGETYPE_GIF) {
        // GIF 无 Alpha 通道，只有单一透明色索引；重采样到真彩画布时调色板透明语义会丢失，
        // 因此把源图透明色作为画布透明色填充，源图透明区域重采样后仍等于该颜色即保持透明。
        $trans_index = ($src_img !== null) ? imagecolortransparent($src_img) : -1;
        if ($trans_index >= 0) {
            $tc = imagecolorsforindex($src_img, $trans_index);
            $bg = imagecolorallocate($canvas, $tc['red'], $tc['green'], $tc['blue']);
        } else {
            $bg = imagecolorallocate($canvas, 255, 255, 255);
        }
        imagefill($canvas, 0, 0, $bg);
        imagecolortransparent($canvas, $bg);
    }
}

// 判断路径扩展名是否为 SVG/SVGZ
function is_svg_upload_path($path)
{
    $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
    return $ext === 'svg' || $ext === 'svgz';
}

// 判断路径扩展名是否为 AVIF
function is_avif_upload_path($path)
{
    return strtolower(pathinfo((string) $path, PATHINFO_EXTENSION)) === 'avif';
}

/** GD/Imagick 均无法处理 AVIF 时的拒收文案（调用方负责删已落盘文件） */
function avif_unsupported_error()
{
    return '当前环境不支持 AVIF 图片处理！';
}

// 上传后是否需走图片后处理（栅格 / SVG 净化 / AVIF 校验）
function upload_should_post_process_image($path)
{
    return is_image($path) || is_svg_upload_path($path) || is_avif_upload_path($path);
}

// 上传图片响应 MIME（供契约测试与 rewrite 规则对照）
function upload_image_content_type($ext)
{
    $ext = strtolower(ltrim((string) $ext, '.'));
    $map = array(
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',
        'avif' => 'image/avif'
    );
    return isset($map[$ext]) ? $map[$ext] : '';
}

/**
 * 上传资源安全响应头映射（纯函数，供单测与 rewrite 规则对照）
 *
 * @param string $ext
 * @return array 关联数组，如 Content-Type => image/svg+xml
 */
function upload_security_headers_map($ext)
{
    $map = array(
        'X-Content-Type-Options' => 'nosniff'
    );
    $ext = strtolower(ltrim((string) $ext, '.'));
    $ctype = upload_image_content_type($ext);
    if ($ctype !== '') {
        $map = array('Content-Type' => $ctype) + $map;
    }
    if ($ext === 'svgz') {
        $map['Content-Encoding'] = 'gzip';
    }
    return $map;
}

// 上传目录 Apache 规则模板路径
function upload_htaccess_template_path()
{
    if (! defined('ROOT_PATH')) {
        return '';
    }
    return ROOT_PATH . 'rewrite/static-upload.htaccess';
}

// 上传目录实际部署的 .htaccess 路径
function upload_htaccess_deploy_path()
{
    if (! defined('DOC_PATH') || ! defined('STATIC_DIR')) {
        return '';
    }
    return rtrim(str_replace('\\', '/', DOC_PATH . STATIC_DIR . '/upload'), '/') . '/.htaccess';
}

// 校验上传目录 htaccess 内容是否含 MIME/nosniff 关键规则
function upload_htaccess_content_is_valid($content)
{
    if (! is_string($content) || $content === '') {
        return false;
    }
    return strpos($content, 'image/svg+xml') !== false
        && strpos($content, 'image/avif') !== false
        && strpos($content, 'X-Content-Type-Options') !== false
        && stripos($content, 'nosniff') !== false;
}

/**
 * 确保 static/upload/.htaccess 已从 rewrite 模板部署（缺文件才写入）
 *
 * @param bool $repair true 时覆盖已存在但不含 MIME/nosniff 的文件（升级/开启伪静态）
 * @return bool 已有有效规则、已部署，或已有自定义文件（热路径不覆盖）
 */
function upload_ensure_htaccess($repair = false)
{
    $dest = upload_htaccess_deploy_path();
    $src = upload_htaccess_template_path();
    if ($dest === '' || $src === '') {
        return false;
    }
    if (! is_file($dest)) {
        unset($GLOBALS['__upload_htaccess_ensured']);
    }
    if (! $repair && ! empty($GLOBALS['__upload_htaccess_ensured'])) {
        return true;
    }
    if (is_file($dest)) {
        $cur = @file_get_contents($dest);
        if (upload_htaccess_content_is_valid($cur)) {
            $GLOBALS['__upload_htaccess_ensured'] = true;
            return true;
        }
        if (! $repair) {
            // 已有文件但不含关键规则：视为站点自定义，热路径不覆盖
            $GLOBALS['__upload_htaccess_ensured'] = true;
            return true;
        }
    }
    if (! is_file($src) || ! is_readable($src)) {
        return false;
    }
    $tpl = @file_get_contents($src);
    if (! upload_htaccess_content_is_valid($tpl)) {
        return false;
    }
    $dir = dirname($dest);
    if (! is_dir($dir)) {
        if (! function_exists('check_dir') || ! check_dir($dir, true)) {
            return false;
        }
    }
    $ok = @file_put_contents($dest, $tpl) !== false;
    if ($ok) {
        $GLOBALS['__upload_htaccess_ensured'] = true;
    }
    return $ok;
}

// AVIF 文件头嗅探（getimagesize 不可用时兜底）
function avif_file_looks_valid($path)
{
    if (! is_file($path) || ! is_readable($path)) {
        return false;
    }
    $info = @getimagesize($path);
    if ($info && ! empty($info[2]) && (int) $info[2] === (int) image_type_avif()) {
        return true;
    }
    $fh = @fopen($path, 'rb');
    if (! $fh) {
        return false;
    }
    $head = @fread($fh, 64);
    @fclose($fh);
    if ($head === false || strlen($head) < 12) {
        return false;
    }
    return imagick_magic_identify($head) === 'AVIF';
}

// SVGZ 限量解压（大小与压缩比）；成功 array($xml, true)，失败 array($err, false)
function svgz_decompress_limited($path, $max_bytes = 2097152, $max_ratio = 100)
{
    if (! is_file($path) || ! is_readable($path)) {
        return array('SVGZ文件不可读！', false);
    }
    $in_len = (int) filesize($path);
    if ($in_len <= 0) {
        return array('SVGZ文件无效！', false);
    }
    if ($in_len > $max_bytes) {
        return array('SVGZ文件过大！', false);
    }
    $max_out = (int) min($max_bytes, max($in_len, $in_len * $max_ratio));
    if (! function_exists('gzopen')) {
        return array('服务器不支持SVGZ解压！', false);
    }
    $zh = @gzopen($path, 'rb');
    if (! $zh) {
        return array('SVGZ解压失败！', false);
    }
    $out = '';
    while (! gzeof($zh)) {
        $chunk = @gzread($zh, 8192);
        if ($chunk === false) {
            gzclose($zh);
            return array('SVGZ解压失败！', false);
        }
        if ($chunk === '') {
            break;
        }
        $out .= $chunk;
        if (strlen($out) > $max_out) {
            gzclose($zh);
            return array('SVGZ解压后过大！', false);
        }
    }
    gzclose($zh);
    $out_len = strlen($out);
    if ($out_len === 0) {
        return array('SVGZ解压结果为空！', false);
    }
    if ($in_len > 0 && ($out_len / $in_len) > $max_ratio) {
        return array('SVGZ压缩比过高！', false);
    }
    return array($out, true);
}

// 判断 URL/引用是否允许出现在 SVG 中（仅同文档 fragment）
function svg_href_is_safe($value)
{
    $value = trim((string) $value);
    if ($value === '' || $value === '#') {
        return true;
    }
    // 仅允许 #id 片段，拒绝 javascript:/data:/http(s):/相对外链
    if ($value[0] === '#' && strpos($value, ':') === false) {
        return (bool) preg_match('/^#[A-Za-z_][\w.-]*$/', $value);
    }
    return false;
}

// url(...) 内部引用是否安全（仅同文档 #id）
function svg_css_url_inner_is_safe($inner)
{
    $inner = trim((string) $inner);
    $inner = trim($inner, "\"'");
    $inner = trim($inner);
    return svg_href_is_safe($inner) && $inner !== '' && isset($inner[0]) && $inner[0] === '#';
}

// 属性值是否含有不安全的 url(...)（fill/filter/clip-path/mask/marker/style 等）
function svg_attr_value_has_unsafe_url($val)
{
    $val = (string) $val;
    if (! preg_match('/url\s*\(/i', $val)) {
        return false;
    }
    // 匹配 url("...") / url('...') / url(...)
    if (! preg_match_all('/url\s*\(\s*(?:([\'"])(.*?)\1|([^)]*?))\s*\)/is', $val, $matches, PREG_SET_ORDER)) {
        // 存在 url( 但无法完整解析，视为不安全
        return true;
    }
    foreach ($matches as $m) {
        $inner = isset($m[3]) && $m[3] !== '' ? $m[3] : (isset($m[2]) ? $m[2] : '');
        if (! svg_css_url_inner_is_safe($inner)) {
            return true;
        }
    }
    return false;
}

// 输出中是否仍残留非同文档 fragment 的 url(...)
function svg_output_has_unsafe_url($out)
{
    $out = (string) $out;
    if (! preg_match('/url\s*\(/i', $out)) {
        return false;
    }
    // 任一 url( 后不是可选引号+# 即视为外部/危险引用
    if (preg_match('/url\s*\(\s*[\'"]?\s*[^#\s\'")]/i', $out)) {
        return true;
    }
    if (! preg_match_all('/url\s*\(\s*(?:([\'"])(.*?)\1|([^)]*?))\s*\)/is', $out, $matches, PREG_SET_ORDER)) {
        return true;
    }
    foreach ($matches as $m) {
        $inner = isset($m[3]) && $m[3] !== '' ? $m[3] : (isset($m[2]) ? $m[2] : '');
        if (! svg_css_url_inner_is_safe($inner)) {
            return true;
        }
    }
    return false;
}

// 移除 DOM 属性（兼容命名空间 xlink:href）
function svg_dom_remove_attr(DOMElement $el, DOMAttr $attr)
{
    if ($attr->namespaceURI) {
        $el->removeAttributeNS($attr->namespaceURI, $attr->localName ? $attr->localName : $attr->name);
    } else {
        $el->removeAttribute($attr->name);
    }
}

// 净化 SVG 字符串：剥离脚本、事件、外部引用与逃逸载荷；成功 array($xml, true)，失败 array($err, false)
function sanitize_svg_string($xml)
{
    $xml = (string) $xml;
    if ($xml === '') {
        return array('SVG内容为空！', false);
    }
    // 拒绝实体/DOCTYPE 逃逸
    if (preg_match('/<!ENTITY/i', $xml) || preg_match('/<!DOCTYPE/i', $xml)) {
        return array('SVG含有不安全的文档类型声明！', false);
    }
    if (! class_exists('DOMDocument', false)) {
        return array('服务器缺少DOM扩展，无法净化SVG！', false);
    }

    $previous = libxml_use_internal_errors(true);
    $disableEntities = null;
    if (PHP_VERSION_ID < 80000 && function_exists('libxml_disable_entity_loader')) {
        $disableEntities = libxml_disable_entity_loader(true);
    }

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = true;
    $dom->formatOutput = false;
    $loaded = @$dom->loadXML($xml, LIBXML_NONET);
    if (PHP_VERSION_ID < 80000 && $disableEntities !== null) {
        libxml_disable_entity_loader($disableEntities);
    }
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (! $loaded || ! $dom->documentElement) {
        return array('SVG解析失败！', false);
    }

    $root = $dom->documentElement;
    if (strtolower($root->localName ? $root->localName : $root->nodeName) !== 'svg') {
        return array('不是有效的SVG根元素！', false);
    }

    $denyTags = array(
        'script', 'foreignobject', 'iframe', 'object', 'embed', 'applet',
        'form', 'input', 'button', 'textarea', 'select', 'option',
        'link', 'meta', 'base', 'handler', 'listener', 'style',
        'set', 'animate', 'animatemotion', 'animatetransform', 'animatecolor', 'mpath'
    );
    $hrefLocalNames = array('href', 'src', 'action', 'formaction', 'data', 'poster');

    $remove = array();
    $nodes = $dom->getElementsByTagName('*');
    // 倒序收集危险节点，避免 live NodeList 索引错乱
    for ($i = $nodes->length - 1; $i >= 0; $i --) {
        $el = $nodes->item($i);
        if (! $el instanceof DOMElement) {
            continue;
        }
        $tag = strtolower($el->localName ? $el->localName : $el->nodeName);
        if (in_array($tag, $denyTags, true)) {
            $remove[] = $el;
            continue;
        }
        if (! $el->hasAttributes()) {
            continue;
        }
        $attrs = array();
        foreach ($el->attributes as $attr) {
            $attrs[] = $attr;
        }
        foreach ($attrs as $attr) {
            $local = strtolower($attr->localName ? $attr->localName : $attr->name);
            $qualified = strtolower($attr->name);
            $val = $attr->value;
            // 事件属性
            if (strpos($local, 'on') === 0 || strpos($qualified, 'on') === 0) {
                svg_dom_remove_attr($el, $attr);
                continue;
            }
            // 样式表达式
            if ($local === 'style' && preg_match('/expression\s*\(/i', $val)) {
                svg_dom_remove_attr($el, $attr);
                continue;
            }
            // 全属性 url(...)：仅允许同文档 url(#id)（覆盖 fill/filter/clip-path/mask/marker/style 等）
            if (svg_attr_value_has_unsafe_url($val)) {
                svg_dom_remove_attr($el, $attr);
                continue;
            }
            $isHref = in_array($local, $hrefLocalNames, true)
                || $qualified === 'xlink:href'
                || substr($qualified, -5) === ':href';
            if ($isHref && ! svg_href_is_safe($val)) {
                svg_dom_remove_attr($el, $attr);
                continue;
            }
            // 危险协议
            if (preg_match('/^\s*(javascript|vbscript|data)\s*:/i', $val)) {
                svg_dom_remove_attr($el, $attr);
            }
        }
    }
    foreach ($remove as $el) {
        if ($el->parentNode) {
            $el->parentNode->removeChild($el);
        }
    }

    // 禁止 use 元素拉取外部资源：无安全 href 则删除
    $uses = $dom->getElementsByTagName('use');
    $useRemove = array();
    for ($i = 0; $i < $uses->length; $i ++) {
        $use = $uses->item($i);
        if (! $use instanceof DOMElement) {
            continue;
        }
        $href = $use->getAttribute('href');
        if ($href === '' && $use->hasAttributeNS('http://www.w3.org/1999/xlink', 'href')) {
            $href = $use->getAttributeNS('http://www.w3.org/1999/xlink', 'href');
        }
        if ($href === '' || ! svg_href_is_safe($href)) {
            $useRemove[] = $use;
        }
    }
    foreach ($useRemove as $el) {
        if ($el->parentNode) {
            $el->parentNode->removeChild($el);
        }
    }

    // 空壳 image（无安全资源）一并删除
    $images = $dom->getElementsByTagName('image');
    $imgRemove = array();
    for ($i = 0; $i < $images->length; $i ++) {
        $img = $images->item($i);
        if (! $img instanceof DOMElement) {
            continue;
        }
        $href = $img->getAttribute('href');
        if ($href === '' && $img->hasAttributeNS('http://www.w3.org/1999/xlink', 'href')) {
            $href = $img->getAttributeNS('http://www.w3.org/1999/xlink', 'href');
        }
        if ($href === '' || ! svg_href_is_safe($href)) {
            $imgRemove[] = $img;
        }
    }
    foreach ($imgRemove as $el) {
        if ($el->parentNode) {
            $el->parentNode->removeChild($el);
        }
    }

    $out = $dom->saveXML($dom->documentElement);
    if ($out === false || $out === '') {
        return array('SVG净化输出失败！', false);
    }
    // 二次确认无脚本/危险协议残留（忽略 xmlns 中的 http）
    if (preg_match('/<\s*script\b/i', $out) || preg_match('/\bon[a-z]+\s*=/i', $out)) {
        return array('SVG仍含有危险脚本内容！', false);
    }
    if (preg_match('/(?:href|xlink:href|src)\s*=\s*([\'"])\s*(?:https?:|javascript:|data:)/i', $out)) {
        return array('SVG仍含有外部或危险引用！', false);
    }
    if (svg_output_has_unsafe_url($out)) {
        return array('SVG仍含有外部或危险引用！', false);
    }
    if (preg_match('/<\s*(?:set|animate|animatemotion|animatetransform|animatecolor|mpath|style)\b/i', $out)) {
        return array('SVG仍含有不安全的动画或样式内容！', false);
    }
    return array('<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $out, true);
}

// 净化已落盘的 SVG/SVGZ；SVGZ 解压后写回净化内容（保持 .svgz 则再压缩）
function sanitize_uploaded_svg($path)
{
    if (! is_file($path) || ! is_readable($path)) {
        return 'SVG文件不可读！';
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'svgz') {
        list($xml, $ok) = svgz_decompress_limited($path);
        if ($ok !== true) {
            return is_string($xml) ? $xml : 'SVGZ解压失败！';
        }
    } else {
        $xml = @file_get_contents($path);
        if ($xml === false) {
            return 'SVG读取失败！';
        }
        if (strlen($xml) > 2097152) {
            return 'SVG文件过大！';
        }
    }

    list($clean, $ok) = sanitize_svg_string($xml);
    if ($ok !== true) {
        return is_string($clean) ? $clean : 'SVG净化失败！';
    }

    if ($ext === 'svgz') {
        if (! function_exists('gzencode')) {
            return '服务器不支持SVGZ压缩！';
        }
        $gz = gzencode($clean, 9);
        if ($gz === false) {
            return 'SVGZ回写压缩失败！';
        }
        if (@file_put_contents($path, $gz) === false) {
            return 'SVGZ保存失败！';
        }
    } else {
        if (@file_put_contents($path, $clean) === false) {
            return 'SVG保存失败！';
        }
    }
    return true;
}


/**
 * $type 是否表示 AVIF（IMAGETYPE_、扩展名、路径）
 *
 * @param mixed $type
 * @return bool
 */
function image_type_arg_is_avif($type)
{
    if ($type === null || $type === '') {
        return false;
    }
    if (is_string($type)) {
        $t = strtolower(ltrim($type, '.'));
        if ($t === 'avif' || $t === 'avis') {
            return true;
        }
        if (strpos($type, '/') !== false || strpos($type, '\\') !== false) {
            return is_avif_upload_path($type);
        }
        return false;
    }
    return (int) $type === (int) image_type_avif();
}

/**
 * Imagick 处理 AVIF（仅经 imagick_create）：重编码 + 可选缩放；水印跳过并给出提示
 *
 * @param string $path
 * @param bool $watermark
 * @param mixed $max_width
 * @param mixed $max_height
 * @param int $quality
 * @return bool|string true 或错误描述
 */
function imagick_post_process_avif($path, $watermark = false, $max_width = null, $max_height = null, $quality = 90)
{
    list($im, $err) = imagick_create($path);
    if ($im === null) {
        return $err !== '' ? $err : 'Imagick 无法处理该图片！';
    }
    try {
        if (! $max_width && class_exists('core\\basic\\Config', false)) {
            $max_width = \core\basic\Config::get('upload.max_width') ?: 999999999;
        }
        if (! $max_height && class_exists('core\\basic\\Config', false)) {
            $max_height = \core\basic\Config::get('upload.max_height') ?: 999999999;
        }
        $width = (int) $im->getImageWidth();
        $height = (int) $im->getImageHeight();
        $max_width = (int) ($max_width ?: 999999999);
        $max_height = (int) ($max_height ?: 999999999);
        if ($width > 0 && $height > 0 && ($width > $max_width || $height > $max_height)) {
            if ($max_width && $max_height) {
                $scale = min($max_width / $width, $max_height / $height);
            } elseif ($max_width) {
                $scale = $max_width / $width;
            } else {
                $scale = $max_height / $height;
            }
            if ($scale < 1) {
                $nw = max(1, (int) floor($scale * $width));
                $nh = max(1, (int) floor($scale * $height));
                if (method_exists($im, 'thumbnailImage')) {
                    $im->thumbnailImage($nw, $nh, true);
                }
            }
        }
        if (method_exists($im, 'setImageFormat')) {
            $im->setImageFormat('AVIF');
        }
        if (method_exists($im, 'setImageCompressionQuality')) {
            $im->setImageCompressionQuality((int) $quality);
        }
        $tmp = $path . '.imagick-avif.tmp';
        $ok = false;
        if (method_exists($im, 'writeImage')) {
            $ok = (bool) @$im->writeImage($tmp);
        }
        if (! $ok || ! is_file($tmp) || filesize($tmp) < 12) {
            @unlink($tmp);
            return 'Imagick AVIF 保存失败！';
        }
        if (! @rename($tmp, $path)) {
            $copied = @copy($tmp, $path);
            @unlink($tmp);
            if (! $copied) {
                return 'Imagick AVIF 保存失败！';
            }
        }
        if ($watermark) {
            $GLOBALS['_upload_post_process_notice'] = '当前环境 AVIF 由 Imagick 处理，已跳过水印';
        }
        return true;
    } catch (\Throwable $e) {
        return 'Imagick 处理 AVIF 失败！';
    } finally {
        if (is_object($im)) {
            if (method_exists($im, 'clear')) {
                @$im->clear();
            }
            if (method_exists($im, 'destroy')) {
                @$im->destroy();
            }
        }
    }
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
        IMAGETYPE_BMP,
        image_type_webp(),
        image_type_avif()
    );
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
    if ($type == image_type_webp() && ! gd_webp_gd_available()) {
        return true;
    }
    if ($type == image_type_avif() && ! gd_avif_gd_available()) {
        return true;
    }
    // 动画 GIF：GD 只能读写首帧，跳过重编码以保留动图；结构不合法则拒绝
    if ((int) $type === IMAGETYPE_GIF && is_animated_gif($path)) {
        return gif_structure_is_safe($path) ? true : 'GIF文件结构不合法！';
    }
    $need = gd_estimate_image_memory($width, $height, 2);
    if (! gd_has_enough_memory($need)) {
        return '图片尺寸过大，服务器内存不足，无法处理图片！';
    }
    list($img, $err) = gd_load_image($path, $type);
    if ($img === false) {
        return $err;
    }
    // PNG/WebP/AVIF 保存前显式保留 Alpha，避免透明区域被写成黑底
    if ($type == IMAGETYPE_PNG || $type == image_type_webp() || $type == image_type_avif()) {
        imagealphablending($img, false);
        imagesavealpha($img, true);
    }
    $ok = gd_save_image($img, $path, $type, $img_quality);
    gd_free_image($img);
    return $ok ? true : '图片重编码保存失败！';
}

// 上传后缩放/水印（UEditor 与通用入口共用）
function upload_post_process_image($abs_path, $watermark = null, $graceful = false)
{
    unset($GLOBALS['_upload_post_process_notice']);
    if ($watermark === null) {
        $watermark = class_exists('core\\basic\\Config', false)
            ? (bool) Config::get('watermark_open')
            : false;
    } else {
        $watermark = (bool) $watermark;
    }
    if (! is_file($abs_path)) {
        return true;
    }
    // SVG/SVGZ 为主动内容：必须净化，不走任何图像后端栅格化
    if (is_svg_upload_path($abs_path)) {
        return sanitize_uploaded_svg($abs_path);
    }
    // AVIF：魔数校验；按能力择优 GD / Imagick；都不可用则拒收（调用方删文件）
    if (is_avif_upload_path($abs_path)) {
        if (! avif_file_looks_valid($abs_path)) {
            return '不是有效的AVIF图片！';
        }
        $backend = image_backend_for('reencode', 'avif');
        if ($backend === 'imagick') {
            $imRe = imagick_post_process_avif($abs_path, $watermark);
            if ($imRe === true) {
                return true;
            }
            if (! gd_avif_gd_available() || ! is_image($abs_path)) {
                return avif_unsupported_error();
            }
        } elseif (! is_image($abs_path) || ! gd_avif_gd_available()) {
            return avif_unsupported_error();
        }
    }
    if (! is_image($abs_path)) {
        return true;
    }
    // 动画 GIF 跳过 GD 前先做结构校验（跳过重编码会失去剥离附加数据能力）
    if (is_animated_gif($abs_path) && ! gif_structure_is_safe($abs_path)) {
        return 'GIF文件结构不合法！';
    }
    list($can_process, $skip_msg) = gd_can_post_process_image($abs_path);
    if (! $can_process) {
        if ($skip_msg !== '') {
            $GLOBALS['_upload_post_process_notice'] = $skip_msg;
        }
        return true;
    }
    if (($re = reencode_image($abs_path)) !== true) {
        return $re;
    }
    if (($re = resize_img($abs_path, $abs_path)) !== true) {
        return $re;
    }
    if ($watermark) {
        $wm = watermark_img($abs_path);
        if ($wm !== true) {
            if ($graceful) {
                return true;
            }
            return $wm;
        }
    }
    return true;
}

// PHP 上传错误码转可读说明
function upload_file_error_message($code)
{
    switch ((int) $code) {
        case UPLOAD_ERR_INI_SIZE:
            return '文件超过 upload_max_filesize 限制';
        case UPLOAD_ERR_FORM_SIZE:
            return '文件超过表单 MAX_FILE_SIZE 限制！';
        case UPLOAD_ERR_PARTIAL:
            return '文件未完整上传，请重试！';
        case UPLOAD_ERR_NO_FILE:
            return '未选择上传文件！';
        case UPLOAD_ERR_NO_TMP_DIR:
            return '服务器缺少临时上传目录！';
        case UPLOAD_ERR_CANT_WRITE:
            return '文件写入临时目录失败！';
        case UPLOAD_ERR_EXTENSION:
            return '服务器扩展阻止了文件上传！';
        default:
            return '上传失败，错误代码' . (int) $code;
    }
}

// 检测 POST 超出 post_max_size（此时 $_FILES 常为空）
function upload_detect_post_max_exceeded()
{
    if (! empty($_FILES)) {
        return false;
    }
    $length = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    if ($length <= 0) {
        return false;
    }
    $post_max = parse_ini_size_bytes(ini_get('post_max_size'));
    return $post_max !== PHP_INT_MAX && $length > $post_max;
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
    unset($GLOBALS['_upload_post_process_notice']);
    if (upload_detect_post_max_exceeded()) {
        return '表单数据超过 post_max_size 限制';
    }
    if (! isset($_FILES[$input_name])) {
        return '未接收到上传文件，请检查表单字段';
    }
    $files = $_FILES[$input_name];
    
    // 定义允许上传的扩展（config 子集 ∩ 代码母集）
    if (! $file_ext) {
        $array_ext_allow = upload_config_extensions();
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
                $err = upload_file_error_message($files['error'][$i]);
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
            $err = upload_file_error_message($files['error']);
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
    // 确保上传目录 Apache MIME/nosniff/禁脚本规则已部署
    upload_ensure_htaccess();
    
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
        'webp',
        'avif',
        'svg',
        'svgz'
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
        // SVG/SVGZ：主动内容，必须净化
        if ($file_ext === 'svg' || $file_ext === 'svgz') {
            $re = sanitize_uploaded_svg($file_path);
            if ($re !== true) {
                @unlink($file_path);
                return $re;
            }
            return $save_file;
        }
        // AVIF：魔数校验；按能力择优
        if ($file_ext === 'avif') {
            if (! avif_file_looks_valid($file_path)) {
                @unlink($file_path);
                return '不是有效的AVIF图片！';
            }
            $backend = image_backend_for('reencode', 'avif');
            if ($backend === 'imagick') {
                $imRe = imagick_post_process_avif($file_path, (bool) $watermark, $max_width, $max_height);
                if ($imRe === true) {
                    return $save_file;
                }
                if (! gd_avif_gd_available() || ! is_image($file_path)) {
                    @unlink($file_path);
                    return avif_unsupported_error();
                }
            } elseif (! is_image($file_path) || ! gd_avif_gd_available()) {
                @unlink($file_path);
                return avif_unsupported_error();
            }
        }
        // 动画 GIF：校验结构后跳过 GD，避免压平为静态图
        if ($file_ext === 'gif' && is_animated_gif($file_path) && ! gif_structure_is_safe($file_path)) {
            @unlink($file_path);
            return 'GIF文件结构不合法！';
        }
        list($can_process, $skip_msg) = gd_can_post_process_image($file_path);
        if (! $can_process) {
            if ($skip_msg !== '') {
                $GLOBALS['_upload_post_process_notice'] = $skip_msg;
            }
            return $save_file;
        }
        if (($re = reencode_image($file_path)) !== true) {
            @unlink($file_path);
            return $re;
        }
        if (($reset = resize_img($file_path, $file_path, $max_width, $max_height)) !== true) {
            @unlink($file_path);
            return $reset;
        }
        if ($watermark) {
            $wm = watermark_img($file_path);
            if ($wm !== true) {
                return $save_file;
            }
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
    if (! $out_image) {
        $out_image = $src_image;
    }
    if (! $max_width) {
        $max_width = Config::get('upload.max_width') ?: 999999999;
    }
    if (! $max_height) {
        $max_height = Config::get('upload.max_height') ?: 999999999;
    }
    
    $size_info = @getimagesize($src_image);
    if (! $size_info || empty($size_info[2])) {
        return '上传文件不是有效的图片！';
    }
    list ($width, $height, $type, $attr) = $size_info;

    // 动画 GIF：GD 缩放会丢帧，整文件拷贝保留动图
    if ((int) $type === IMAGETYPE_GIF && is_animated_gif($src_image)) {
        check_dir(dirname($out_image), true);
        if ($src_image != $out_image) {
            if (! copy($src_image, $out_image)) {
                return '缩放图片时拷贝到目的地址失败！';
            }
        }
        return true;
    }
    
    check_dir(dirname($out_image), true);
    
    if ($width <= $max_width && $height <= $max_height) {
        if ($src_image != $out_image) {
            if (! copy($src_image, $out_image)) {
                return '缩放图片时拷贝到目的地址失败！';
            }
        }
        return true;
    }
    
    if ($max_width && $max_height) {
        $scale = min($max_width / $width, $max_height / $height);
    } elseif ($max_width) {
        $scale = $max_width / $width;
    } elseif ($max_height) {
        $scale = $max_height / $height;
    } else {
        return true;
    }
    
    if ($scale < 1) {
        $new_width = max(1, (int) floor($scale * $width));
        $new_height = max(1, (int) floor($scale * $height));

        $need = gd_estimate_image_memory($width, $height, 2) + gd_estimate_image_memory($new_width, $new_height, 2);
        if (! gd_has_enough_memory($need)) {
            return '图片尺寸过大，服务器内存不足，无法缩放图片！';
        }

        list($img, $err) = gd_load_image($src_image, $type);
        if ($img === false) {
            return $err;
        }

        $new_img = @imagecreatetruecolor($new_width, $new_height);
        if (! $new_img) {
            gd_free_image($img);
            return '创建缩放画布失败！';
        }
        
        if ($type == IMAGETYPE_GIF || $type == IMAGETYPE_PNG || $type == image_type_webp() || $type == image_type_avif()) {
            gd_prepare_canvas_transparency($new_img, $type, $img);
        }
        if (! @imagecopyresampled($new_img, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height)) {
            gd_free_image($img);
            gd_free_image($new_img);
            return '缩放图片失败！';
        }
        if (! gd_save_image($new_img, $out_image, $type, $img_quality)) {
            gd_free_image($new_img);
            gd_free_image($img);
            return '缩放图片保存失败！';
        }
        gd_free_image($new_img);
        gd_free_image($img);
    }
    return true;
}

// 剪切图片（成功 true，失败返回错误描述；无宽高时 return null 保持兼容）
function cut_img($src_image, $out_image = null, $new_width = null, $new_height = null, $img_quality = 90)
{
    if (! $out_image) {
        $out_image = $src_image;
    }
    if (! $new_width && ! $new_height) {
        return;
    }

    $size_info = @getimagesize($src_image);
    if (! $size_info || empty($size_info[2])) {
        return '上传文件不是有效的图片！';
    }
    list ($width, $height, $type) = $size_info;
    if ($width < 1 || $height < 1) {
        return '上传文件不是有效的图片！';
    }

    // 动画 GIF：GD 裁剪会丢帧，整文件拷贝保留动图
    if ((int) $type === IMAGETYPE_GIF && is_animated_gif($src_image)) {
        check_dir(dirname($out_image), true);
        if ($src_image != $out_image) {
            if (! copy($src_image, $out_image)) {
                return '裁剪图片时拷贝到目的地址失败！';
            }
        }
        return true;
    }

    // 不限定则按另一边等比例缩放
    if (! $new_width) {
        $new_width = floor($width * ($new_height / $height));
    }
    if (! $new_height) {
        $new_height = floor($height * ($new_width / $width));
    }
    $new_width = max(1, (int) $new_width);
    $new_height = max(1, (int) $new_height);

    // 按目标宽高比在原图中计算最大可用源区域，并居中取样，避免拉伸与主体偏移
    $dst_ratio = $new_width / $new_height;
    $src_ratio = $width / $height;
    if ($src_ratio > $dst_ratio) { // 源图偏宽：取全高，宽度按目标比例居中截取
        $cut_height = $height;
        $cut_width = (int) round($height * $dst_ratio);
    } else { // 源图偏高或等比：取全宽，高度按目标比例居中截取
        $cut_width = $width;
        $cut_height = (int) round($width / $dst_ratio);
    }
    $cut_width = max(1, min($cut_width, $width));
    $cut_height = max(1, min($cut_height, $height));
    $src_x = (int) floor(($width - $cut_width) / 2);
    $src_y = (int) floor(($height - $cut_height) / 2);

    $need = gd_estimate_image_memory($width, $height, 2) + gd_estimate_image_memory($new_width, $new_height, 2);
    if (! gd_has_enough_memory($need)) {
        return '图片尺寸过大，服务器内存不足，无法裁剪图片！';
    }

    list($img, $err) = gd_load_image($src_image, $type);
    if ($img === false) {
        return $err;
    }

    $new_img = @imagecreatetruecolor($new_width, $new_height);
    if (! $new_img) {
        gd_free_image($img);
        return '创建裁剪画布失败！';
    }

    // 按类型正确处理透明通道，避免黑底/白边/透明失真
    if ($type == IMAGETYPE_GIF || $type == IMAGETYPE_PNG || $type == image_type_webp() || $type == image_type_avif()) {
        gd_prepare_canvas_transparency($new_img, $type, $img);
    }

    // 高质量重采样 + 居中源区域取样
    if (! @imagecopyresampled($new_img, $img, 0, 0, $src_x, $src_y, $new_width, $new_height, $cut_width, $cut_height)) {
        gd_free_image($img);
        gd_free_image($new_img);
        return '裁剪图片失败！';
    }

    check_dir(dirname($out_image), true);
    if (! gd_save_image($new_img, $out_image, $type, $img_quality)) {
        gd_free_image($new_img);
        gd_free_image($img);
        return '裁剪图片保存失败！';
    }
    gd_free_image($new_img);
    gd_free_image($img);
    return true;
}

// 图片水印（成功返回 true，失败返回错误描述，未开启返回 true）
function watermark_img($src_image, $out_image = null, $position = null, $watermark_image = null, $watermark_text = '', $watermark_text_size = null, $watermark_text_color = null)
{
    if (! Config::get('watermark_open')) {
        return true;
    }
    
    if (! $out_image) {
        $out_image = $src_image;
    }
    if (! is_file($src_image)) {
        return '原图不存在，无法添加水印！';
    }
    
    if (! $watermark_text) {
        $watermark_text = Config::get('watermark_text') ?: 'PbootCMS';
    }
    $watermark_pic = $watermark_image ?: Config::get('watermark_pic');
    if (! $watermark_text && ! $watermark_pic) {
        return true;
    }
    
    $size = @getimagesize($src_image);
    if (! $size || empty($size[2])) {
        return '原图不是有效图片，无法添加水印！';
    }
    list ($width1, $height1, $type1) = $size;

    // 动画 GIF：加水印会经 GD 压成静态图，跳过以保留动图
    if ((int) $type1 === IMAGETYPE_GIF && is_animated_gif($src_image)) {
        if ($src_image != $out_image) {
            check_dir(dirname($out_image), true);
            if (! copy($src_image, $out_image)) {
                return '水印处理时拷贝到目的地址失败！';
            }
        }
        return true;
    }
    
    $need = gd_estimate_image_memory($width1, $height1, 4);
    if (! gd_has_enough_memory($need)) {
        return '图片尺寸过大，服务器内存不足，无法添加水印！';
    }

    list($img1, $err) = gd_load_image($src_image, $type1);
    if ($img1 === false) {
        return $err;
    }

    $img2 = null;
    $width2 = 0;
    $height2 = 0;

    if ($watermark_pic) {
        $wm_path = upload_resolve_public_path($watermark_pic);
        if (! is_file($wm_path)) {
            gd_free_image($img1);
            return '水印图片不存在！';
        }
        $wm_size = @getimagesize($wm_path);
        if (! $wm_size || empty($wm_size[2])) {
            gd_free_image($img1);
            return '水印图片无效！';
        }
        list ($width2, $height2, $type2) = $wm_size;
        list($img2, $err) = gd_load_image($wm_path, $type2);
        if ($img2 === false) {
            gd_free_image($img1);
            return $err;
        }
    } else {
        if (! gd_supports_freetype()) {
            gd_free_image($img1);
            return '服务器未启用FreeType，无法使用文字水印！';
        }
        if (! $watermark_text_size) {
            $watermark_text_size = Config::get('watermark_text_size') ?: 16;
        }
        if (! $watermark_text_color) {
            $watermark_text_color = Config::get('watermark_text_color') ?: '100,100,100';
        }
        $colors = explode(',', $watermark_text_color);
        $font_rel = Config::get('watermark_text_font');
        if (! $font_rel) {
            gd_free_image($img1);
            return '水印字体文件未配置！';
        }
        $font = upload_resolve_public_path($font_rel);
        if (! is_file($font)) {
            gd_free_image($img1);
            return '水印字体文件不存在！';
        }
        
        $fontsize = (int) $watermark_text_size;
        $width2 = mb_strlen($watermark_text, 'UTF-8') * ($fontsize + 10) + 20;
        $height2 = $fontsize + 10;
        $img2 = @imagecreatetruecolor($width2, $height2);
        if (! $img2) {
            gd_free_image($img1);
            return '创建水印画布失败！';
        }
        $color = imagecolorallocate($img2, 255, 255, 255);
        imagefill($img2, 0, 0, $color);
        imagecolortransparent($img2, $color);
        $textcolor = imagecolorallocate($img2, (int) $colors[0], (int) ($colors[1] ?? 0), (int) ($colors[2] ?? 0));
        if (@imagettftext($img2, $fontsize, 0, 5, $fontsize + 5, $textcolor, $font, $watermark_text) === false) {
            gd_free_image($img1);
            gd_free_image($img2);
            return '文字水印渲染失败！';
        }
    }
    
    $width2 = max(1, (int) $width2);
    $height2 = max(1, (int) $height2);

    if ($width1 < $width2 * 3 || $height1 < $height2) {
        $scale = min(($width1 / 3) / $width2, ($height1 / 2) / $height2);
        $new_width = max(1, (int) floor($scale * $width2));
        $new_height = max(1, (int) floor($scale * $height2));
    } else {
        $new_width = $width2;
        $new_height = $height2;
    }
    
    if (! $position) {
        $position = Config::get('watermark_position') ?: 4;
    }
    switch ((string) $position) {
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
            $x = (int) (($width1 - $new_width) / 2);
            $y = (int) (($height1 - $new_height) / 2);
            break;
        default:
            $x = $width1 - $new_width - 15;
            $y = $height1 - $new_height - 15;
            break;
    }
    $x = max(0, (int) $x);
    $y = max(0, (int) $y);
    
    $out = @imagecreatetruecolor($width1, $height1);
    if (! $out) {
        gd_free_image($img1);
        gd_free_image($img2);
        return '创建输出画布失败！';
    }
    if ($type1 == IMAGETYPE_GIF) {
        // GIF：按源透明色铺底，开启混合以便透明像素透出底色；勿用 Alpha 白底（imagegif 会压成不透明浅色）
        gd_prepare_canvas_transparency($out, $type1, $img1);
        imagealphablending($out, true);
    } elseif ($type1 == IMAGETYPE_PNG || $type1 == image_type_webp() || $type1 == image_type_avif()) {
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $transparent = imagecolorallocatealpha($out, 255, 255, 255, 127);
        imagefill($out, 0, 0, $transparent);
    }
    if (! @imagecopy($out, $img1, 0, 0, 0, 0, $width1, $height1)) {
        gd_free_image($img1);
        gd_free_image($img2);
        gd_free_image($out);
        return '合成原图失败！';
    }
    if ($type1 == IMAGETYPE_PNG || $type1 == image_type_webp() || $type1 == image_type_avif()) {
        imagealphablending($out, true);
    }
    if (! @imagecopyresized($out, $img2, $x, max(0, $y - 10), 0, 0, $new_width, $new_height, $width2, $height2)) {
        gd_free_image($img1);
        gd_free_image($img2);
        gd_free_image($out);
        return '叠加水印失败！';
    }

    // GIF 保存前重新标记透明色，避免真彩→调色板丢失透明索引
    if ($type1 == IMAGETYPE_GIF) {
        $trans_index = imagecolortransparent($img1);
        if ($trans_index >= 0) {
            $tc = imagecolorsforindex($img1, $trans_index);
            $bg = imagecolorallocate($out, $tc['red'], $tc['green'], $tc['blue']);
            imagecolortransparent($out, $bg);
        }
    }

    check_dir(dirname($out_image), true);

    if (! gd_save_image($out, $out_image, $type1, 90)) {
        gd_free_image($img1);
        gd_free_image($img2);
        gd_free_image($out);
        return '保存水印图片失败！';
    }

    gd_free_image($img1);
    gd_free_image($img2);
    gd_free_image($out);
    return true;
}

// ---------- 上传资源 public URL / CDN 前缀（第二阶段：仅输出层，不改本地存储） ----------

function upload_public_base()
{
    return trim(Config::get('upload_public_url') ?: '');
}

function upload_public_enabled()
{
    return Config::get('upload_public_url_enable') == '1' && upload_public_base() !== '';
}

// 上传目录 URL 前缀（规范化后始终不含 SITE_DIR）
function upload_relative_prefix()
{
    return '/static/upload/';
}

function upload_local_path($path)
{
    if (! $path) {
        return $path;
    }
    $path = str_replace('\\', '/', $path);
    if (preg_match('#^https?://#i', $path)) {
        $base = upload_public_base();
        if ($base && stripos($path, rtrim($base, '/')) === 0) {
            $path = substr($path, strlen(rtrim($base, '/')));
        } else {
            return null;
        }
    }
    if (defined('SITE_DIR') && SITE_DIR && strpos($path, SITE_DIR . '/') === 0) {
        $path = substr($path, strlen(SITE_DIR));
    }
    if (defined('ROOT_PATH') && strpos($path, ROOT_PATH) === 0) {
        $path = str_replace(ROOT_PATH, '', $path);
    }
    return '/' . ltrim($path, '/');
}

function upload_is_upload_path($path)
{
    $path = upload_local_path($path);
    if (! $path) {
        return false;
    }
    return (strpos($path, upload_relative_prefix()) === 0);
}

function upload_public_url($path)
{
    if (! $path) {
        return $path;
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $path = str_replace('\\', '/', $path);
    if (defined('SITE_DIR') && SITE_DIR && strpos($path, SITE_DIR . '/') === 0) {
        $path = substr($path, strlen(SITE_DIR));
    }
    $path = '/' . ltrim($path, '/');
    if (upload_public_enabled() && upload_is_upload_path($path)) {
        return rtrim(upload_public_base(), '/') . $path;
    }
    return (defined('SITE_DIR') ? SITE_DIR : '') . $path;
}

function upload_strip_public_url($path)
{
    if (! $path) {
        return $path;
    }
    $local = upload_local_path($path);
    return $local !== null ? $local : $path;
}

function normalize_upload_saved_path($path)
{
    return upload_strip_public_url($path);
}

function normalize_upload_saved_paths($paths)
{
    if (! $paths) {
        return $paths;
    }
    $parts = explode(',', $paths);
    foreach ($parts as $key => $part) {
        $parts[$key] = normalize_upload_saved_path(trim($part));
    }
    return implode(',', array_filter($parts, 'strlen'));
}

function upload_html_upload_url_pattern()
{
    // UEditor: /cms/static/upload/...；DB 字段: /static/upload/...
    $prefixes = array(preg_quote(STATIC_DIR, '#'));
    if (defined('SITE_DIR') && SITE_DIR !== '') {
        $prefixes[] = preg_quote('/static', '#');
    }
    $staticAlt = '(?:' . implode('|', array_unique($prefixes)) . ')';
    return '#((?:src|href)\s*=\s*)([\'"])(' . $staticAlt . '/upload/[^\'"]*)\2#i';
}

function rewrite_static_urls_in_html($html)
{
    if (! $html || ! upload_public_enabled()) {
        return $html;
    }
    return preg_replace_callback(upload_html_upload_url_pattern(), function ($m) {
        return $m[1] . $m[2] . upload_public_url($m[3]) . $m[2];
    }, $html);
}

/**
 * 对外输出 HTML 中的上传资源 URL（API 等场景）
 * - CDN 开启：走 rewrite_static_urls_in_html
 * - CDN 关闭：保持旧 API 行为，补全源站绝对 URL
 */
function upload_output_html($html)
{
    if (! $html) {
        return $html;
    }
    if (upload_public_enabled()) {
        return rewrite_static_urls_in_html($html);
    }
    return preg_replace_callback(upload_html_upload_url_pattern(), function ($m) {
        return $m[1] . $m[2] . get_http_url() . upload_public_url($m[3]) . $m[2];
    }, $html);
}

/**
 * API 单字段上传资源 URL（CDN 开：CDN 绝对 URL；CDN 关：相对路径 + SITE_DIR）
 */
function upload_output_url($path)
{
    if (! $path || preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return upload_public_url($path);
}

function upload_output_paths($paths)
{
    if (! $paths) {
        return $paths;
    }
    $parts = explode(',', $paths);
    foreach ($parts as $i => $part) {
        $parts[$i] = upload_output_url(trim($part));
    }
    return implode(',', array_filter($parts, 'strlen'));
}

function upload_output_content_assets($data)
{
    if (! $data) {
        return $data;
    }
    if (! empty($data->ico)) {
        $data->ico = upload_output_url($data->ico);
    }
    if (! empty($data->pics)) {
        $data->pics = upload_output_paths($data->pics);
    }
    if (! empty($data->enclosure)) {
        $data->enclosure = upload_output_url($data->enclosure);
    }
    return $data;
}

function upload_output_sort_assets($data)
{
    if (! $data) {
        return $data;
    }
    if (! empty($data->ico)) {
        $data->ico = upload_output_url($data->ico);
    }
    if (! empty($data->pic)) {
        $data->pic = upload_output_url($data->pic);
    }
    return $data;
}

function upload_output_sorts_tree($tree)
{
    if (! is_array($tree)) {
        return $tree;
    }
    foreach ($tree as $node) {
        upload_output_sort_assets($node);
        if (! empty($node->son)) {
            $node->son = upload_output_sorts_tree($node->son);
        }
    }
    return $tree;
}

function upload_output_rows($rows, array $fields)
{
    if (! is_array($rows)) {
        return $rows;
    }
    foreach ($rows as $row) {
        foreach ($fields as $field) {
            if (! empty($row->$field)) {
                $row->$field = upload_output_url($row->$field);
            }
        }
    }
    return $rows;
}

function upload_output_site_data($data, $name = null)
{
    if ($name) {
        if ($name === 'logo' && $data) {
            return upload_output_url($data);
        }
        return $data;
    }
    if ($data && ! empty($data->logo)) {
        $data->logo = upload_output_url($data->logo);
    }
    return $data;
}

function upload_output_company_data($data, $name = null)
{
    if ($name) {
        if ($name === 'weixin' && $data) {
            return upload_output_url($data);
        }
        return $data;
    }
    if ($data && ! empty($data->weixin)) {
        $data->weixin = upload_output_url($data->weixin);
    }
    return $data;
}
