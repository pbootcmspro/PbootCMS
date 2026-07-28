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
         // 图片（7）
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'ico',
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
    $image_set = array('.png', '.jpg', '.jpeg', '.gif', '.bmp', '.webp');
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

/**
 * 清除图像后端能力探测缓存（测试或配置变更后可调用）
 */
function image_backend_clear_probe_cache()
{
    unset($GLOBALS['__imagick_probe_cache'], $GLOBALS['__image_capability_matrix_cache']);
}

/**
 * Imagick 能力探测（请求内缓存；测试可通过 $GLOBALS['__test_imagick_probe'] 覆盖）
 *
 * @return array loaded, version, formats, delegates, animated_gif
 */
function imagick_probe()
{
    if (array_key_exists('__test_imagick_probe', $GLOBALS)) {
        return $GLOBALS['__test_imagick_probe'];
    }
    if (isset($GLOBALS['__imagick_probe_cache']) && is_array($GLOBALS['__imagick_probe_cache'])) {
        return $GLOBALS['__imagick_probe_cache'];
    }

    $result = array(
        'loaded' => false,
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
        'animated_gif' => false
    );

    if (! extension_loaded('imagick') || ! class_exists('Imagick', false)) {
        $GLOBALS['__imagick_probe_cache'] = $result;
        return $result;
    }

    $result['loaded'] = true;
    try {
        if (method_exists('Imagick', 'getVersion')) {
            $ver = \Imagick::getVersion();
            if (is_array($ver) && ! empty($ver['versionString'])) {
                $result['version'] = (string) $ver['versionString'];
            } elseif (is_string($ver)) {
                $result['version'] = $ver;
            }
        }

        $formats = array();
        if (method_exists('Imagick', 'queryFormats')) {
            $list = @\Imagick::queryFormats();
            if (is_array($list)) {
                foreach ($list as $f) {
                    $formats[strtoupper((string) $f)] = true;
                }
            }
        }
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
    $gdAvif = function_exists('imagecreatefromavif') && function_exists('imageavif');
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
            'imagick' => ! empty($im['formats']['AVIF'])
        ),
        'heic' => array(
            'gd' => false,
            'imagick' => ! empty($im['formats']['HEIC'])
        ),
        'animated_gif' => array(
            'gd' => false,
            'imagick' => ! empty($im['animated_gif'])
        ),
        'webp' => array(
            'gd' => gd_supports_webp(),
            'imagick' => ! empty($im['formats']['WEBP'])
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
 * 高层窄分派意图：返回 gd|imagick（不改 gd_load_image 语义、不做多态）
 *
 * 本阶段 Imagick 仅探测不接管：auto 恒为 gd；prefer_imagick 在扩展可用时返回 imagick（供后续接缝），
 * 现有缩放/水印/重编码路径仍只走 GD，零行为变化。gd_only 为逃生阀强制 GD。
 * 注意：系统信息「实际处理」请用 image_backend_effective()，勿把本函数返回值当成已接管。
 *
 * @param string $op 操作名（resize/watermark/reencode/info 等，本阶段预留）
 * @param mixed $type 图片类型（本阶段预留）
 * @return string gd|imagick
 */
function image_backend_for($op, $type = null)
{
    if (array_key_exists('__test_image_backend_for', $GLOBALS)) {
        return (string) $GLOBALS['__test_image_backend_for'];
    }
    // $op / $type 预留：后续按操作与格式能力矩阵择优 Imagick
    unset($op, $type);

    $mode = image_backend_config();
    if ($mode === 'gd_only') {
        return 'gd';
    }
    if ($mode === 'prefer_imagick') {
        $probe = imagick_probe();
        if (! empty($probe['loaded'])) {
            return 'imagick';
        }
        return 'gd';
    }
    // auto：本阶段仍默认 GD（Imagick 不接管）
    return 'gd';
}

/**
 * 当前实际处理路径（供系统信息页展示）
 *
 * 与 image_backend_for() 的「分派意图」区分：本阶段缩放/水印/重编码仍只走 GD，
 * 即便配置为 prefer_imagick 且扩展可用，此处仍返回 gd，避免「生效=imagick」误导。
 * 后续某条路径真正接管后再按实际实现返回 imagick。
 *
 * @return string gd|imagick
 */
function image_backend_effective()
{
    // 本阶段无 Imagick 接管实现；gd_only / auto / prefer_imagick 的实际处理均为 GD
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
    // GD 不支持多帧 GIF，动画必须跳过重编码/缩放/水印以免压成静态图
    if ((int) $info[2] === IMAGETYPE_GIF && is_animated_gif($path)) {
        return array(false, '动画GIF已保留原图（跳过缩放/水印）');
    }
    return array(true, '');
}

// 上传后处理跳过原因（供 UEditor 等展示提示）
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
            return imagejpeg($img, $path, $img_quality);
    }
}

// 为缩放/裁剪画布按类型正确设置透明通道（PNG/WebP 保留完整 Alpha，GIF 沿用调色板透明色）
function gd_prepare_canvas_transparency($canvas, $type, $src_img = null)
{
    if ($type == IMAGETYPE_PNG || $type == image_type_webp()) {
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
        image_type_webp()
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
    // PNG/WebP 保存前显式保留 Alpha，避免透明区域被写成黑底
    if ($type == IMAGETYPE_PNG || $type == image_type_webp()) {
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
    if (! is_file($abs_path) || ! is_image($abs_path)) {
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
    if ($watermark === null) {
        $watermark = (bool) Config::get('watermark_open');
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
        // 动画 GIF：校验结构后跳过 GD，避免压平为静态图
        if ($file_ext === 'gif' && is_animated_gif($file_path) && ! gif_structure_is_safe($file_path)) {
            @unlink($file_path);
            return 'GIF文件结构不合法！';
        }
        list($can_process, $skip_msg) = gd_can_post_process_image($file_path);
        if (! $can_process) {
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
        
        if ($type == IMAGETYPE_GIF || $type == IMAGETYPE_PNG || $type == image_type_webp()) {
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
    if ($type == IMAGETYPE_GIF || $type == IMAGETYPE_PNG || $type == image_type_webp()) {
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
    } elseif ($type1 == IMAGETYPE_PNG || $type1 == image_type_webp()) {
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
    if ($type1 == IMAGETYPE_PNG || $type1 == image_type_webp()) {
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
