<?php
/**
 * @ copyright (C)2016-2099 Hnaoyun Inc.
 * @ author XingMeng
 * @ email hnxsh@foxmail.com
 * @ date 2017年4月14日
 * 公共处理函数
 */
use core\basic\Config;

// 获取字符串型自动编码
function get_auto_code($string, $start = '1')
{
    if (! $string)
        return $start;
    if (is_numeric($string)) { // 如果纯数字则直接加1
        return sprintf('%0' . strlen($string) . 's', $string + 1);
    } else { // 非纯数字则先分拆
        $reg = '/^([a-zA-Z-_]+)([0-9]+)$/';
        $str = preg_replace($reg, '$1', $string); // 字母部分
        $num = preg_replace($reg, '$2', $string); // 数字部分
        return $str . sprintf('%0' . (strlen($string) - strlen($str)) . 's', $num + 1);
    }
}

// 获取指定分类列表
function get_type($tcode)
{
    $type_model = model('admin.system.Type');
    if (! ! $result = $type_model->getItem($tcode)) {
        return $result;
    } else {
        return array();
    }
}

// 生成区域选择
function make_area_Select($tree, $selectid = null)
{
    $list_html = '';
    global $blank;
    if (! isset($blank) || $blank === null) {
        $blank = '';
    }
    foreach ($tree as $values) {
        // 默认选择项
        if ($selectid == $values->acode) {
            $select = "selected='selected'";
        } else {
            $select = '';
        }
        
        // 禁用父栏目选择功能
        if ($values->son) {
            $disabled = "disabled='disabled'";
        } else {
            $disabled = '';
        }
        $list_html .= "<option value='{$values->acode}' $select $disabled>{$blank}{$values->acode} {$values->name}";
        
        // 子菜单处理
        if ($values->son) {
            $blank .= '　　';
            $list_html .= make_area_Select($values->son, $selectid);
        }
    }
    // 循环完后回归位置（PHP 8：$blank 未进入子级时为 ''，不可对 null 调用 substr）
    if ($blank !== '' && strlen($blank) >= 6) {
        $blank = substr($blank, 0, - 6);
    }
    return $list_html;
}

// 检测指定的方法是否拥有权限
function check_level($btnAction, $isPath = false)
{
    $user_level = session('levels');
    if ($isPath) {
        if (in_array($btnAction, $user_level)) {
            return true;
        }
    } else {
        if (in_array('/' . M . '/' . C . '/' . $btnAction, $user_level) || session('id') == 1) {
            return true;
        }
    }
}

// 获取返回按钮
function get_btn_back($btnName = '返 回')
{
    if (! ! $backurl = get('backurl')) {
        $url = base64_decode($backurl);
    } elseif (isset($_SERVER["HTTP_REFERER"])) {
        $url = $_SERVER["HTTP_REFERER"];
    } else {
        $url = url('/' . M . '/' . C . '/index');
    }

    $url = sanitize_redirect_url($url, url('/' . M . '/' . C . '/index'));
    
    $btn_html = "<a href='" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "' class='layui-btn layui-btn-primary'>$btnName</a>";
    return $btn_html;
}

// 获取新增按钮
function get_btn_add($btnName = '新 增')
{
    $user_level = session('levels');
    if (! in_array('/' . M . '/' . C . '/add', $user_level) && session('id') != 1)
        return;
    $btn_html = "<a href='" . url("/" . M . '/' . C . "/add") . get_btn_qs() . "' class='layui-btn layui-btn-primary'>$btnName</a>";
    return $btn_html;
}

// 获取更多按钮
function get_btn_more($idValue, $id = 'id', $btnName = '详情')
{
    $btn_html = "<a href='" . url("/" . M . '/' . C . "/index/$id/$idValue") . "' class='layui-btn layui-btn-xs layui-btn-primary' title='$btnName'>$btnName</a>";
    return $btn_html;
}

// 获取删除按钮
function get_btn_del($idValue, $id = 'id', $btnName = '删除')
{
    $user_level = session('levels');
    if (! in_array('/' . M . '/' . C . '/del', $user_level) && session('id') != 1)
        return;
    $btn_html = "<a href='" . url('/' . M . '/' . C . "/del/$id/$idValue") . "' onclick='return confirm(\"您确定要删除么？\")' class='layui-btn layui-btn-xs layui-btn-danger' title='$btnName'>$btnName</a>";
    return $btn_html;
}

// 获取 POST + formcheck 删除按钮（用于已改为仅接受 POST 的删除端点）
function get_btn_del_post($idValue, $id = 'id', $btnName = '删除')
{
    $user_level = session('levels');
    if (! in_array('/' . M . '/' . C . '/del', $user_level) && session('id') != 1)
        return;
    $action = htmlspecialchars(url('/' . M . '/' . C . "/del/$id/$idValue"), ENT_QUOTES, 'UTF-8');
    $token = htmlspecialchars((string) session('formcheck'), ENT_QUOTES, 'UTF-8');
    $btnName = htmlspecialchars($btnName, ENT_QUOTES, 'UTF-8');
    $btn_html = "<form action='" . $action . "' method='post' style='display:inline' onsubmit='return confirm(\"您确定要删除么？\")'>"
        . "<input type='hidden' name='formcheck' value='" . $token . "'>"
        . "<button type='submit' class='layui-btn layui-btn-xs layui-btn-danger' title='" . $btnName . "'>" . $btnName . "</button>"
        . "</form>";
    return $btn_html;
}

// 获取修改按钮
function get_btn_mod($idValue, $id = 'id', $btnName = '修改')
{
    $user_level = session('levels');
    if (! in_array('/' . M . '/' . C . '/mod', $user_level) && session('id') != 1)
        return;
    $btn_html = "<a href='" . url("/" . M . '/' . C . "/mod/$id/$idValue") . get_btn_qs() . "'  class='layui-btn layui-btn-xs'>$btnName</a>";
    return $btn_html;
}

// 获取其它按钮
function get_btn($btnName, $theme, $btnAction, $idValue, $id = 'id')
{
    $user_level = session('levels');
    if (! in_array('/' . M . '/' . C . '/' . $btnAction, $user_level) && session('id') != 1)
        return;
    $btn_html = "<a href='" . url("/" . M . '/' . C . "/$btnAction/$id/$idValue") . get_btn_qs() . "'  class='layui-btn layui-btn-xs $theme'>$btnName</a>";
    return $btn_html;
}

// 获取按钮返回参数
function get_btn_qs()
{
    if (isset($_SERVER["QUERY_STRING"]) && ! ! $qs = $_SERVER["QUERY_STRING"]) {
        return "&backurl=" . base64_encode(URL);
    } else {
        return "?backurl=" . base64_encode(URL);
    }
}

// 获取返回URL
function get_backurl()
{
    if (! ! $backurl = get('backurl')) {
        if (isset($_SERVER["QUERY_STRING"]) && ! ! get('p')) {
            return "&backurl=" . $backurl;
        } else {
            return "?backurl=" . $backurl;
        }
    } else {
        return;
    }
}

// 获取返回tab跳转地址
function get_tab($tid)
{
    if (isset($_SERVER["QUERY_STRING"]) && ! ! get('p')) {
        return "&#tab=" . $tid;
    } else {
        return "?#tab=" . $tid;
    }
}

// 缓存语言信息
function cache_config($refresh = false)
{
    $model = model('admin.system.Config');

    // 系统配置缓存；迁移仅在缓存缺失或强制刷新时执行，避免稳定态冗余查询
    $config_cache = RUN_PATH . '/config/' . md5('config') . '.php';
    if (! file_exists($config_cache) || $refresh) {
        if ($model->migrateTplHtmlCacheConfig()) {
            $refresh = true;
        }
        $config = $model->getConfig();
        unset($config['sn']);
        unset($config['sn_user']);
        Config::set(md5('config'), $config, false, true);
        // 两项未齐时删掉刚写的配置缓存，下次请求继续尝试迁移（避免失败后永久跳过）
        if (! $model->hasTplHtmlCacheConfig() && file_exists($config_cache)) {
            @unlink($config_cache);
        }
    }
    
    // 多语言缓存
    $lg_cache = RUN_PATH . '/config/' . md5('area') . '.php';
    if (! file_exists($lg_cache) || $refresh) {
        $model = model('admin.system.Config');
        $area = $model->getAreaTheme(); // 获取所有语言
        $map = array();
        foreach ($area as $key => $value) {
            $map[$value['acode']] = $value;
        }
        if (! $map) {
            error('系统没有任何可用区域，请核对后再试！');
        }
        $lgs['lgs'] = $map;
        Config::set(md5('area'), $lgs, false, true);
    }
}

// 获取默认语言
function get_default_lg()
{
    $default = current(Config::get('lgs'));
    return $default['acode'];
}

// 获取当前语言并进行安全处理
function get_lg()
{
    $lg = cookie('lg');
    $lgs = Config::get('lgs');
    if (! $lg || ! preg_match('/^[\w\-]+$/', $lg) || (is_array($lgs) && ! isset($lgs[$lg]))) {
        $lg = get_default_lg();
        cookie('lg', $lg);
    }
    return $lg;
}

// 获取当前语言主题，未取到时回退默认主题，避免返回空值后被静默当作default
function get_theme()
{
    $lgs = Config::get('lgs');
    $lg = get_lg();
    if (isset($lgs[$lg]['theme'])) {
        return $lgs[$lg]['theme'];
    }
    return 'default';
}

// 推送百度
function post_baidu($api, $urls)
{
    $ch = curl_init();
    $options = array(
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_URL => $api,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => implode("\n", $urls),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: text/plain'
        )
    );
    curl_setopt_array($ch, $options);
    $result = json_decode(curl_exec($ch));
    return $result;
}

// 校验IndexNow密钥格式：8-128位字母、数字或连字符
function check_indexnow_key($key)
{
    return (bool) preg_match('/^[A-Za-z0-9\-]{8,128}$/', (string) $key);
}

// 生成IndexNow密钥
function make_indexnow_key()
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(16));
    }
    return md5(uniqid(mt_rand(), true));
}

// IndexNow密钥文件物理路径，位于部署根目录
function get_indexnow_key_file($key)
{
    return rtrim(ROOT_PATH, '/\\') . '/' . $key . '.txt';
}

// IndexNow密钥文件访问地址，未配置时按部署目录推导；二级目录部署时密钥仅对该目录下的地址有效
function get_indexnow_key_location($key, $key_location = '')
{
    $key_location = trim((string) $key_location);
    if ($key_location !== '') {
        // 已是绝对地址则原样返回；相对路径补全域名，避免接口收到本地路径
        if (preg_match('#^https?://#i', $key_location)) {
            return $key_location;
        }
        $rel = ltrim(str_replace('\\', '/', $key_location), '/');
        // 与 resolve_indexnow_local_key_file() 一致：容忍误带的 SITE_DIR 前缀，避免 /cms/cms/ 双前缀
        if (defined('SITE_DIR') && SITE_DIR !== '') {
            $siteDir = trim(str_replace('\\', '/', SITE_DIR), '/');
            if ($siteDir !== '' && ($rel === $siteDir || strpos($rel, $siteDir . '/') === 0)) {
                $rel = ltrim(substr($rel, strlen($siteDir)), '/');
            }
        }
        return get_http_url() . SITE_DIR . '/' . $rel;
    }
    return get_http_url() . SITE_DIR . '/' . $key . '.txt';
}

// 判断密钥文件 URL 的 host 是否为本站（相对路径不走此函数）
function indexnow_is_same_site_host($host, $port = null)
{
    $host = strtolower((string) $host);
    if ($host === '') {
        return false;
    }
    $siteHost = strtolower(get_http_host(true));
    $httpHost = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';
    $httpHost = preg_replace('/:\d+$/', '', $httpHost);
    if ($host !== $siteHost && $host !== $httpHost) {
        return false;
    }
    if ($port !== null && $port !== '') {
        $serverPort = isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : (is_https() ? 443 : 80);
        return (int) $port === $serverPort;
    }
    return true;
}

// 是否为远程非本站密钥地址（禁止自动拉取，防 SSRF）
function is_indexnow_remote_key_location($key_location)
{
    $key_location = trim((string) $key_location);
    if ($key_location === '' || ! preg_match('#^https?://#i', $key_location)) {
        return false;
    }
    $parts = parse_url($key_location);
    if (empty($parts['host'])) {
        return true;
    }
    return ! indexnow_is_same_site_host($parts['host'], isset($parts['port']) ? $parts['port'] : null);
}

// 将密钥文件地址解析为站点内本地文件路径：相对路径、本站绝对 URL 可解析；远程非本站或非法路径返回空
function resolve_indexnow_local_key_file($key_location)
{
    $key_location = trim((string) $key_location);
    if ($key_location === '') {
        return '';
    }

    $rel = '';
    if (preg_match('#^https?://#i', $key_location)) {
        $parts = parse_url($key_location);
        if (empty($parts['host']) || empty($parts['path']) || $parts['path'] === '/') {
            return '';
        }
        if (! indexnow_is_same_site_host($parts['host'], isset($parts['port']) ? $parts['port'] : null)) {
            return '';
        }
        $rel = str_replace('\\', '/', $parts['path']);
    } else {
        $rel = str_replace('\\', '/', $key_location);
    }

    // 二级目录部署时 URL/误填路径可能带 SITE_DIR 前缀，映射到 ROOT_PATH 前去掉
    if (defined('SITE_DIR') && SITE_DIR !== '') {
        $siteDir = rtrim(str_replace('\\', '/', SITE_DIR), '/');
        if ($siteDir !== '' && (strpos($rel, $siteDir . '/') === 0 || $rel === $siteDir)) {
            $rel = substr($rel, strlen($siteDir));
        }
    }

    $rel = ltrim($rel, '/');
    if ($rel === '' || strpos($rel, '..') !== false) {
        return '';
    }

    $root = rtrim(ROOT_PATH, '/\\');
    $file = $root . '/' . $rel;
    $realRoot = realpath($root);
    $dir = dirname($file);
    if ($realRoot && is_dir($dir)) {
        $realDir = realpath($dir);
        // 须落在 ROOT_PATH 内；用 DIRECTORY_SEPARATOR 边界，避免 /var/www/site 匹配到 /var/www/site-evil
        if ($realDir === false || ($realDir !== $realRoot && strpos($realDir, $realRoot . DIRECTORY_SEPARATOR) !== 0)) {
            return '';
        }
    }
    return $file;
}

// 密钥文件地址的 basename 必须为 {key}.txt（IndexNow 协议要求，同时防止覆盖站点内其它文件）
function indexnow_location_basename_ok($key, $key_location)
{
    if (! check_indexnow_key($key)) {
        return false;
    }
    $path = trim((string) $key_location);
    if ($path === '') {
        return false;
    }
    if (preg_match('#^https?://#i', $path)) {
        $parts = parse_url($path);
        $path = isset($parts['path']) ? (string) $parts['path'] : '';
    }
    $path = str_replace('\\', '/', $path);
    if ($path === '' || strpos($path, '..') !== false) {
        return false;
    }
    return basename($path) === $key . '.txt';
}

// 判断密钥文件目录是否覆盖全部推送地址（IndexNow：子目录密钥只能推该目录下的 URL）
function indexnow_key_location_covers_urls($key_location, $urls)
{
    $key_location = trim((string) $key_location);
    if ($key_location === '' || ! $urls) {
        return false;
    }
    $dir = preg_replace('#/[^/]*$#', '/', $key_location);
    foreach ($urls as $url) {
        if (strpos((string) $url, $dir) !== 0) {
            return false;
        }
    }
    return true;
}

// 解析推送用密钥地址：自定义地址能覆盖推送 URL 时用之，否则回退站点根目录密钥（不写文件，写文件仅在配置保存时进行）
function resolve_indexnow_key_location($key, $key_location_cfg, $urls)
{
    $key_location = get_indexnow_key_location($key, $key_location_cfg);
    if (indexnow_key_location_covers_urls($key_location, $urls)) {
        return $key_location;
    }
    return get_indexnow_key_location($key, '');
}

// 检查密钥文件地址对应的本地文件是否存在且内容等于密钥（无法映射到本地时返回 true，由引擎侧校验）
function indexnow_key_location_file_ready($key, $key_location)
{
    if (! check_indexnow_key($key)) {
        return false;
    }
    $key_location = trim((string) $key_location);
    if ($key_location === '') {
        return false;
    }
    $file = resolve_indexnow_local_key_file($key_location);
    if ($file === '') {
        // 本站绝对 URL 偶发无法映射时，根目录默认地址仍可按密钥文件名检查
        $default = get_indexnow_key_location($key, '');
        if ($key_location === $default) {
            $file = get_indexnow_key_file($key);
        } else {
            return true;
        }
    }
    return is_file($file) && trim((string) @file_get_contents($file)) === $key;
}

// 从站点内本地密钥文件读取密钥（相对路径或本站绝对 URL；远程非本站不读取；文件名须为 {内容}.txt）
function read_indexnow_key_from_local_location($key_location)
{
    $file = resolve_indexnow_local_key_file($key_location);
    if ($file === '' || ! is_file($file)) {
        return '';
    }
    $key = trim((string) @file_get_contents($file));
    if (! check_indexnow_key($key)) {
        return '';
    }
    // 文件名必须与内容一致，避免把任意文本文件内容当密钥回填
    if (! indexnow_location_basename_ok($key, $key_location)) {
        return '';
    }
    return $key;
}

// 有效推送密钥：配置优先；为空时尝试从本地密钥文件回填（仅填了密钥文件地址的场景）
function get_effective_indexnow_key($key, $key_location = '')
{
    if (check_indexnow_key($key)) {
        return (string) $key;
    }
    return read_indexnow_key_from_local_location($key_location);
}

// 写入IndexNow密钥文件，返回是否成功
function save_indexnow_key_file($key)
{
    if (! check_indexnow_key($key)) {
        return false;
    }
    $file = get_indexnow_key_file($key);
    if (file_exists($file) && trim((string) @file_get_contents($file)) === $key) {
        return true;
    }
    return @file_put_contents($file, $key) !== false;
}

// 将密钥同步写入密钥文件地址对应的本地文件（相对路径 / 本站 URL）；远程地址或文件名非 {key}.txt 返回 false
function write_indexnow_key_to_local_location($key, $key_location)
{
    if (! check_indexnow_key($key)) {
        return false;
    }
    // 强制 basename 为 {key}.txt，禁止覆盖 index.php / config 等站点文件
    if (! indexnow_location_basename_ok($key, $key_location)) {
        return false;
    }
    $file = resolve_indexnow_local_key_file($key_location);
    if ($file === '') {
        return false;
    }
    if (file_exists($file) && trim((string) @file_get_contents($file)) === $key) {
        return true;
    }
    $dir = dirname($file);
    if (! is_dir($dir)) {
        return false;
    }
    return @file_put_contents($file, $key) !== false;
}

// 拼接IndexNow推送地址；域名绑定场景下链接可能已带协议，避免重复拼接
function build_indexnow_url($domain, $path)
{
    $path = (string) $path;
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return $domain . $path;
}

// 过滤IndexNow推送地址：去重；指定 expected_host 时仅保留该域，否则以首条地址的 host 为基准（防混域整批被拒）
function filter_indexnow_urls($urls, $expected_host = null)
{
    $urls = array_values(array_unique($urls));
    if (! $urls) {
        return array();
    }

    if ($expected_host !== null && $expected_host !== '') {
        $expected_host = strtolower((string) $expected_host);
        $result = array();
        foreach ($urls as $url) {
            $host = parse_url($url, PHP_URL_HOST);
            if ($host && strtolower($host) === $expected_host) {
                $result[] = $url;
            }
        }
        return $result;
    }

    $host = parse_url($urls[0], PHP_URL_HOST);
    if (! $host) {
        return array();
    }

    $result = array();
    foreach ($urls as $url) {
        if (parse_url($url, PHP_URL_HOST) === $host) {
            $result[] = $url;
        }
    }
    return $result;
}

// 推送IndexNow，返回array('code'=>HTTP状态码,'errno'=>curl错误码,'error'=>curl错误描述)
// $expected_host 可选，传入后强制按该 host 过滤并写入请求的 host 字段
function post_indexnow($key, $key_location, $urls, $expected_host = null)
{
    $rs = array(
        'code' => 0,
        'errno' => 0,
        'error' => ''
    );

    $urls = filter_indexnow_urls($urls, $expected_host);
    if (! $urls) {
        return $rs;
    }

    $host = ($expected_host !== null && $expected_host !== '')
        ? strtolower((string) $expected_host)
        : parse_url($urls[0], PHP_URL_HOST);
    $data = array(
        'host' => $host,
        'key' => $key,
        'urlList' => $urls
    );
    if ($key_location) {
        $data['keyLocation'] = $key_location;
    }

    $ch = curl_init();
    $options = array(
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_URL => 'https://api.indexnow.org/indexnow',
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json; charset=utf-8'
        ),
        // 接口仅支持HTTPS，虚拟主机缺省证书库时须显式指定，否则校验失败无法推送
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CAINFO => CORE_PATH . '/cacert.pem'
    );
    curl_setopt_array($ch, $options);
    curl_exec($ch); // 接口成功时响应体为空，仅依据状态码判断结果
    $rs['code'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $rs['errno'] = curl_errno($ch);
    $rs['error'] = curl_error($ch);
    curl_close($ch);
    return $rs;
}

// 转换IndexNow推送结果为提示语
function get_indexnow_msg($rs, $count)
{
    if (! empty($rs['errno'])) {
        // 提示语最终输出到JS弹窗，去除引号避免脚本截断
        $error = str_replace(array(
            '"',
            "'"
        ), '', $rs['error']);
        return 'IndexNow推送请求失败：' . $error . '，请检查服务器网络及HTTPS证书配置！';
    }

    switch ($rs['code']) {
        case 200:
            return '成功推送' . $count . '条地址到IndexNow！';
        case 202:
            return '已接收' . $count . '条地址，密钥待校验；首次推送出现属正常现象，请确认密钥文件可公网访问！';
        case 400:
            return 'IndexNow推送失败：请求格式错误，请检查密钥及推送地址！';
        case 403:
            return 'IndexNow推送失败：密钥无效，或与密钥文件中的内容不一致！';
        case 422:
            return 'IndexNow推送失败：推送地址不属于本站点，或与密钥文件所在目录不匹配！';
        case 429:
            return 'IndexNow推送失败：推送过于频繁，请稍后再试！';
        case 0:
            return 'IndexNow推送失败：未获取到接口响应，请稍后再试！';
        default:
            return 'IndexNow推送失败：接口返回状态码' . $rs['code'] . '！';
    }
}

// 保存富文本后：从 content 提取 iframe 域名并追加到全局白名单（精确 host）
// $model 可选，便于测试注入；生产默认 model('admin.system.Config')
function sync_iframe_whitelist_from_content($html, $model = null)
{
    if (is_string($html) && $html !== '') {
        $html = decode_string($html);
    }
    $hosts = filter_html_extract_iframe_hosts($html);
    if (! $hosts) {
        return array(
            'ok' => true,
            'added' => false,
            'added_hosts' => array(),
            'msg' => '',
        );
    }
    if ($model === null) {
        $model = model('admin.system.Config');
    }
    return $model->appendIframeWhitelistHosts($hosts);
}

// 白名单同步失败时的提示文案；成功或无需追加时返回空串
function iframe_whitelist_sync_notice($html, $success_prefix = '保存成功！', $model = null)
{
    $sync = sync_iframe_whitelist_from_content($html, $model);
    if ($sync['ok']) {
        return '';
    }
    $hosts = ! empty($sync['added_hosts']) ? implode('、', $sync['added_hosts']) : '';
    $suffix = $hosts ? '（' . $hosts . '）' : '';
    return $success_prefix . '，但 iframe 域名未能自动加入白名单' . $suffix . '，请前往「全局配置 → 配置参数 → 安全配置 → iframe白名单」手动添加，否则前台可能无法显示 iframe。';
}

// 合并多段富文本后同步 iframe 白名单（主内容 + 扩展字段 / 多标签）
function iframe_whitelist_sync_notice_many(array $htmls, $success_prefix = '保存成功！', $model = null)
{
    $merged = '';
    foreach ($htmls as $html) {
        if (is_string($html) && $html !== '') {
            $merged .= $html . "\n";
        }
    }
    return iframe_whitelist_sync_notice($merged, $success_prefix, $model);
}

// 扩展字段是否为编辑器类型（ay_extfield.type=8）；请求内缓存 type map
function is_extfield_editor($name)
{
    static $map = null;
    if (! is_string($name) || $name === '' || strpos($name, 'ext_') !== 0) {
        return false;
    }
    if ($map === null) {
        $map = array();
        try {
            $loaded = model('admin.content.ExtField')->getTypeMap();
            if (is_array($loaded)) {
                $map = $loaded;
            }
        } catch (\Throwable $e) {
            $map = array();
        }
    }
    return isset($map[$name]) && (int) $map[$name] === 8;
}

// llms.txt：将栏目编码集合（数组或逗号串）规范为去重逗号串，过滤非法编码
function llms_normalize_scodes($input)
{
    if (is_string($input)) {
        $input = explode(',', $input);
    }
    if (! is_array($input)) {
        return '';
    }
    $codes = array();
    foreach ($input as $code) {
        if (! is_scalar($code)) {
            continue;
        }
        $code = trim((string) $code);
        // 栏目编码为 ay_content_sort.scode，限定字母数字与连字符下划线
        if ($code === '' || ! preg_match('/^[\w\-]{1,20}$/', $code)) {
            continue;
        }
        if (! in_array($code, $codes, true)) {
            $codes[] = $code;
        }
    }
    return implode(',', $codes);
}

// llms.txt：把文本压成不含换行的单行，换行会破坏 Markdown 列表结构
function llms_single_line($text)
{
    if (! is_scalar($text)) {
        return '';
    }
    // \s 在 /u 模式下不含 U+00A0 与中日韩全角空格，需显式列出
    $text = preg_replace('/[\x{00a0}\x{3000}\s]+/u', ' ', (string) $text);
    return trim((string) $text);
}

// llms.txt：将富文本或描述压成单行纯文本并按字数截断，供链接说明使用
function llms_plain_text($text, $len = 100)
{
    if (! is_scalar($text)) {
        return '';
    }
    $text = (string) $text;
    if ($text === '') {
        return '';
    }
    // 入库时经 escape_string 转义，须先还原再剥标签；顺序颠倒会把实体形态的标签解码进输出
    $text = decode_string($text);
    // 块级结束标签代表语义换行，先换成空格再剥标签，避免前后文字被粘连
    $text = preg_replace('#<br\s*/?>|</(p|div|li|tr|td|h[1-6]|blockquote)>#i', ' ', $text);
    $text = preg_replace('#<!--.*?-->#s', '', $text);
    // 解码后无法区分真标签与作者写的小于号，故只剥「<字母」开头的合法标签形态；
    // strip_tags 会从小于号一路吞到结尾，「价格 <100 元」会只剩「价格」
    $text = preg_replace('#</?[a-z][a-z0-9]*[^>]*>#i', '', $text);
    // 富文本常见的 &nbsp; 等命名实体在剥完标签后才还原，避免重新生成可被误认的标签
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = llms_single_line($text);
    if ($text === '') {
        return '';
    }

    $len = (int) $len;
    if ($len < 1) {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'utf-8') > $len) {
            $text = mb_substr($text, 0, $len, 'utf-8') . '…';
        }
    } elseif (strlen($text) > $len) {
        $text = substr($text, 0, $len) . '…';
    }
    return $text;
}

// llms.txt：按 ay_config 字段长度收敛补充说明；入参为入库转义态，不在此解码
function llms_truncate_intro($text, $len = 190)
{
    if (! is_scalar($text)) {
        return '';
    }
    $text = trim(preg_replace('/[\r\n\t]+/', ' ', (string) $text));
    if ($text === '') {
        return '';
    }

    $len = (int) $len;
    if ($len < 1) {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'utf-8') > $len) {
            $text = mb_substr($text, 0, $len, 'utf-8');
        }
    } elseif (strlen($text) > $len) {
        $text = substr($text, 0, $len);
    }
    // 截断可能切开 &amp; 之类的实体，去掉尾部残片以免入库后解码出乱码
    return preg_replace('/&[#0-9a-zA-Z]{0,9}$/', '', $text);
}

// llms.txt：转义 Markdown 链接文本中会破坏 [标题](地址) 结构的字符
function llms_escape_link_text($text)
{
    if (! is_scalar($text)) {
        return '';
    }
    $text = llms_plain_text($text, 200);
    return str_replace(array('[', ']'), array('\[', '\]'), $text);
}

// llms.txt：转义链接地址中会提前闭合 Markdown 链接的字符
function llms_escape_link_url($url)
{
    if (! is_scalar($url)) {
        return '';
    }
    $url = trim((string) $url);
    $url = str_replace(array("\r", "\n", ' '), array('', '', '%20'), $url);
    return str_replace(array('(', ')'), array('%28', '%29'), $url);
}

/**
 * llms.txt：生成一条 Markdown 链接行，说明为空时省略冒号部分
 *
 * 标题在此完成清洗，说明则须由调用方按 llms_desc_len 清洗截断后传入：
 * llms_plain_text() 含解码步骤并不幂等，重复清洗会把已还原的字面标签当真标签剥掉。
 */
function llms_link_line($title, $url, $note = '')
{
    $title = llms_escape_link_text($title);
    $url = llms_escape_link_url($url);
    if ($title === '' || $url === '') {
        return '';
    }
    $note = llms_single_line($note);
    return '- [' . $title . '](' . $url . ')' . ($note !== '' ? ': ' . $note : '');
}

