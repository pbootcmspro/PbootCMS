<?php

/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年11月5日
 *
 */
use core\basic\Config;

// 获取用户浏览器类型
function get_user_bs($bs = null)
{
    if (isset($_SERVER["HTTP_USER_AGENT"])) {
        $user_agent = strtolower($_SERVER["HTTP_USER_AGENT"]);
    } else {
        return null;
    }
    
    // 直接检测传递的值
    if ($bs) {
        if (strpos($user_agent, strtolower($bs))) {
            return true;
        } else {
            return false;
        }
    }
    
    // 固定检测
    if (strpos($user_agent, 'micromessenger')) {
        $user_bs = 'Weixin';
    } elseif (strpos($user_agent, 'qq')) {
        $user_bs = 'QQ';
    } elseif (strpos($user_agent, 'weibo')) {
        $user_bs = 'Weibo';
    } elseif (strpos($user_agent, 'alipayclient')) {
        $user_bs = 'Alipay';
    } elseif (strpos($user_agent, 'trident/7.0')) {
        $user_bs = 'IE11'; // 新版本IE优先，避免360等浏览器的兼容模式检测错误
    } elseif (strpos($user_agent, 'trident/6.0')) {
        $user_bs = 'IE10';
    } elseif (strpos($user_agent, 'trident/5.0')) {
        $user_bs = 'IE9';
    } elseif (strpos($user_agent, 'trident/4.0')) {
        $user_bs = 'IE8';
    } elseif (strpos($user_agent, 'msie 7.0')) {
        $user_bs = 'IE7';
    } elseif (strpos($user_agent, 'msie 6.0')) {
        $user_bs = 'IE6';
    } elseif (strpos($user_agent, 'edge')) {
        $user_bs = 'Edge';
    } elseif (strpos($user_agent, 'firefox')) {
        $user_bs = 'Firefox';
    } elseif (strpos($user_agent, 'chrome') || strpos($user_agent, 'android')) {
        $user_bs = 'Chrome';
    } elseif (strpos($user_agent, 'safari')) {
        $user_bs = 'Safari';
    } elseif (strpos($user_agent, 'mj12bot')) {
        $user_bs = 'MJ12bot';
    } else {
        $user_bs = 'Other';
    }
    return $user_bs;
}

// 获取用户操作系统类型
function get_user_os($osstr = null)
{
    if (isset($_SERVER["HTTP_USER_AGENT"])) {
        $user_agent = strtolower($_SERVER["HTTP_USER_AGENT"]);
    } else {
        return null;
    }
    
    // 直接检测传递的值
    if ($osstr) {
        if (strpos($user_agent, strtolower($osstr))) {
            return true;
        } else {
            return false;
        }
    }
    
    // 必须先于windows判断：二合一UA形如 (PC; OpenHarmony 6.1; Windows NT 10.0)，带windows nt兼容填充
    // 仅手机与平板按移动设备处理，PC/2in1/未知形态一律归为非移动，避免新形态出现时被误投喂手机版
    if (strpos($user_agent, 'openharmony') !== false) {
        if (strpos($user_agent, 'phone;') !== false) {
            $user_os = 'HarmonyOS';
        } elseif (strpos($user_agent, 'tablet;') !== false) {
            $user_os = 'HarmonyOS Pad';
        } else {
            $user_os = 'HarmonyOS PC';
        }
    } elseif (strpos($user_agent, 'windows nt 5.0')) {
        $user_os = 'Windows 2000';
    } elseif (strpos($user_agent, 'windows nt 9')) {
        $user_os = 'Windows 9X';
    } elseif (strpos($user_agent, 'windows nt 5.1')) {
        $user_os = 'Windows XP';
    } elseif (strpos($user_agent, 'windows nt 5.2')) {
        $user_os = 'Windows 2003';
    } elseif (strpos($user_agent, 'windows nt 6.0')) {
        $user_os = 'Windows Vista';
    } elseif (strpos($user_agent, 'windows nt 6.1')) {
        $user_os = 'Windows 7';
    } elseif (strpos($user_agent, 'windows nt 6.2')) {
        $user_os = 'Windows 8';
    } elseif (strpos($user_agent, 'windows nt 6.3')) {
        $user_os = 'Windows 8.1';
    } elseif (strpos($user_agent, 'windows nt 10')) {
        $user_os = 'Windows 10';
    } elseif (strpos($user_agent, 'windows phone')) {
        $user_os = 'Windows Phone';
    } elseif (strpos($user_agent, 'android')) {
        $user_os = 'Android';
    } elseif (strpos($user_agent, 'iphone')) {
        $user_os = 'iPhone';
    } elseif (strpos($user_agent, 'ipad')) {
        $user_os = 'iPad';
    } elseif (strpos($user_agent, 'mac')) {
        $user_os = 'Mac';
    } elseif (strpos($user_agent, 'sunos')) {
        $user_os = 'Sun OS';
    } elseif (strpos($user_agent, 'bsd')) {
        $user_os = 'BSD';
    } elseif (strpos($user_agent, 'ubuntu')) {
        $user_os = 'Ubuntu';
    } elseif (strpos($user_agent, 'linux')) {
        $user_os = 'Linux';
    } elseif (strpos($user_agent, 'unix')) {
        $user_os = 'Unix';
    } else {
        $user_os = 'Other';
    }
    return $user_os;
}

/**
 * 从 $_SERVER 读取直连对端 IPv4；无法识别时返回 0.0.0.0（不回退去信转发头）。
 */
function get_user_ip_peer(): string
{
    if (! isset($_SERVER['REMOTE_ADDR'])) {
        return '0.0.0.0';
    }
    $peer = trim((string) $_SERVER['REMOTE_ADDR']);
    if ($peer === '::1') {
        return '127.0.0.1';
    }
    if (filter_var($peer, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return $peer;
    }
    return '0.0.0.0';
}

/**
 * 转发头候选：公网 IPv4；拒绝私有/保留/回环/链路本地/0.0.0.0。
 */
function is_forwarded_public_ipv4($ip): bool
{
    if (! is_string($ip) || $ip === '') {
        return false;
    }
    if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return false;
    }
    $long = ip2long($ip);
    if ($long === false) {
        return false;
    }
    $long = sprintf('%u', $long);
    if ($long <= 0) {
        return false;
    }
    // PHP 7.0 NO_RES_RANGE 不含 127.0.0.0/8
    if (($long & 0xff000000) === 0x7f000000) {
        return false;
    }
    return true;
}

/** @return string[] */
function get_trusted_proxies(): array
{
    $proxies = array();
    if (! class_exists('core\\basic\\Config', false) || ! defined('CORE_PATH')) {
        return $proxies;
    }
    $raw = \core\basic\Config::get('trusted_proxies', true);
    if (! is_array($raw)) {
        return $proxies;
    }
    foreach ($raw as $entry) {
        $entry = trim((string) $entry);
        if ($entry !== '') {
            $proxies[] = $entry;
        }
    }
    return $proxies;
}

function is_trusted_proxy_ip(string $ip): bool
{
    foreach (get_trusted_proxies() as $network) {
        if (network_match($ip, $network)) {
            return true;
        }
    }
    return false;
}

/**
 * 从 X-Forwarded-For 自右向左剥可信跳；
 * 第一个不可信 hop：公网 IPv4 则采纳，否则拒绝整条链（不可越过私有/保留/畸形 hop）。
 */
function resolve_forwarded_client_ipv4(string $forwarded, array $trusted): string
{
    $parts = array_map('trim', explode(',', $forwarded));
    $parts = array_values(array_filter($parts, static function ($v) {
        return $v !== '';
    }));
    if (! $parts) {
        return '';
    }
    for ($i = count($parts) - 1; $i >= 0; $i--) {
        $candidate = $parts[$i];
        // 畸形 / 非 IPv4：拒绝整条链，不可向左继续扫描
        if (! filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return '';
        }
        $trustedHop = false;
        foreach ($trusted as $network) {
            if (network_match($candidate, $network)) {
                $trustedHop = true;
                break;
            }
        }
        if ($trustedHop) {
            continue;
        }
        // 第一个不可信 hop：公网则采纳，否则 fail-closed
        if (is_forwarded_public_ipv4($candidate)) {
            return $candidate;
        }
        return '';
    }
    return '';
}

// 获取用户IP
function get_user_ip(): string
{
    $peer = get_user_ip_peer();
    $trusted = get_trusted_proxies();

    if ($trusted && is_trusted_proxy_ip($peer)) {
        $client = '';
        if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $client = resolve_forwarded_client_ipv4((string) $_SERVER['HTTP_X_FORWARDED_FOR'], $trusted);
        } elseif (! empty($_SERVER['HTTP_X_REAL_IP'])) {
            $real = trim((string) $_SERVER['HTTP_X_REAL_IP']);
            if (is_forwarded_public_ipv4($real)) {
                $client = $real;
            }
        }
        if ($client !== '') {
            return htmlspecialchars($client, ENT_QUOTES, 'UTF-8');
        }
    }

    return htmlspecialchars($peer, ENT_QUOTES, 'UTF-8');
}

/**
 * IP存储值转显示字符串，兼容历史脏数据。
 * 数值按long2ip还原；空值归零；已是点分或其它文本的原样返回。
 * PHP8下long2ip()收到非数值字符串会抛TypeError，导致整个列表页报错，故读取端统一走此函数。
 */
function long2ip_safe($value): string
{
    if (is_numeric($value)) {
        return long2ip((int) $value);
    }
    return $value ? (string) $value : '0.0.0.0';
}

/**
 * 安全关闭 cURL 句柄：PHP 7 为 resource 需 curl_close；PHP 8+ 为 CurlHandle 对象且 PHP 8.5 起 curl_close 废弃。
 */
function curl_safe_close($ch)
{
    if (is_resource($ch)) {
        curl_close($ch);
    }
}

// 执行URL请求，并返回数据
function get_url($url, $fields = array(), $UserAgent = null, $vfSSL = false)
{
    $SSL = substr($url, 0, 8) == "https://" ? true : false;
    
    $ch = curl_init();
    if ($UserAgent) { // 在HTTP请求中包含一个"User-Agent: "头的字符串。
        curl_setopt($ch, CURLOPT_USERAGENT, $UserAgent);
    } else {
        // PHP 8：未定义下标会告警并污染 AJAX JSON；无浏览器 UA 时用固定串
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'PbootCMS';
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);
    }
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); // 在发起连接前等待的时间，如果设置为0，则无限等待
    curl_setopt($ch, CURLOPT_TIMEOUT, 90); // 设置cURL允许执行的最长秒数
    curl_setopt($ch, CURLOPT_URL, $url); // 设置请求地址
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // 设置cURL 参数，要求结果保存到字符串中还是输出到屏幕上。
                                                 
    // SSL验证
    if ($SSL) {
        if ($vfSSL) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_CAINFO, CORE_PATH . '/cacert.pem');
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 信任任何证书
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // 不检查证书中是否设置域名
        }
    }
    
    // 数据字段
    if ($fields) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    }
    
    $output = curl_exec($ch);
    if ($output === false || curl_errno($ch)) {
        $err = curl_error($ch);
        curl_safe_close($ch);
        // 去掉 URL 中可能夹带的用户名密码，避免泄露凭据
        $err = preg_replace('#://[^:@/\s]+:[^@/\s]+@#', '://***:***@', (string) $err);
        error('请求远程地址错误：' . ($err !== '' ? $err : '未知网络错误'));
    }
    curl_safe_close($ch);
    if (! is_string($output)) {
        error('请求远程地址错误：响应无效');
    }
    return $output;
}

/**
 * 进程实例标识：优先 getmypid，主机 disable_functions 禁用时安全降级
 * PHP 8 会将被禁用函数从函数表移除，直接调用会 fatal
 */
function process_instance_id()
{
    if (function_exists('getmypid')) {
        $pid = @getmypid();
        if ($pid !== false && $pid !== null && (int) $pid > 0) {
            return (int) $pid;
        }
    }
    return mt_rand(1000, 2147483647);
}

// 返回时间戳格式化日期时间，默认当前
function get_datetime($timestamp = null)
{
    if (! $timestamp)
        $timestamp = time();
    return date('Y-m-d H:i:s', $timestamp);
}

// 返回时间戳格式化日期，默认当前
function get_date($timestamp = null)
{
    if (! $timestamp)
        $timestamp = time();
    return date('Y-m-d', $timestamp);
}

// 返回时间戳差值部分，年、月、日
function get_date_diff($startstamp, $endstamp, $return = 'm')
{
    $y = date('Y', $endstamp) - date('Y', $startstamp);
    $m = date('m', $endstamp) - date('m', $startstamp);
    
    switch ($return) {
        case 'y':
            if ($y <= 1) {
                $y = $m / 12;
            }
            $string = $y;
            break;
        case 'm':
            $string = $y * 12 + $m;
            break;
        case 'd':
            $string = ($endstamp - $startstamp) / 86400;
            break;
    }
    return $string;
}

// 生成无限极树,$data为二维数组数据
function get_tree($data, $tid, $idField, $pidField, $sonName = 'son')
{
    $tree = array();
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            if ($value[$pidField] == "$tid") { // 父亲找到儿子
                $value[$sonName] = get_tree($data, $value[$idField], $idField, $pidField, $sonName);
                $tree[] = $value;
            }
        } else {
            if ($value->$pidField == "$tid") { // 父亲找到儿子
                $temp = clone $value;
                $temp->$sonName = get_tree($data, $value->$idField, $idField, $pidField, $sonName);
                $tree[] = $temp;
            }
        }
    }
    return $tree;
}

// 获取数据数组的映射数组
function get_mapping($array, $vValue, $vKey = null)
{
    if (! $array)
        return array();
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            if ($vKey) {
                $result[$value[$vKey]] = $value[$vValue];
            } else {
                $result[] = $value[$vValue];
            }
        } elseif (is_object($value)) {
            if ($vKey) {
                $result[$value->$vKey] = $value->$vValue;
            } else {
                $result[] = $value->$vValue;
            }
        } else {
            return $array;
        }
    }
    return $result;
}

// 页码赋值，异常返回1
function get_page()
{
    if (isset($_GET['page'])) {
        $value = trim($_GET['page']);
        if (preg_match('/^[0-9]+$/', $value)) {
            return $value;
        }
    }
    return 1;
}

// 返回请求类型
function get_request_method()
{
    return $_SERVER['REQUEST_METHOD'];
}

// 获取当前完整URL地址
function get_current_url()
{
    $http_type = is_https() ? 'https://' : 'http://';
    return $http_type . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

// 获取字符串第N次出现位置
function get_strpos($string, $find, $n)
{
    $pos = strpos($string, $find);
    for ($i = 2; $i <= $n; $i ++) {
        $pos = strpos($string, $find, $pos + 1);
    }
    return $pos;
}

// array_column向下兼容低版本PHP
if (! function_exists('array_column')) {

    function array_column($input, $columnKey, $indexKey = null)
    {
        $columnKeyIsNumber = (is_numeric($columnKey)) ? true : false;
        $indexKeyIsNull = (is_null($indexKey)) ? true : false;
        $indexKeyIsNumber = (is_numeric($indexKey)) ? true : false;
        $result = array();
        foreach ((array) $input as $key => $row) {
            if ($columnKeyIsNumber) {
                $tmp = array_slice($row, $columnKey, 1);
                $tmp = (is_array($tmp) && ! empty($tmp)) ? current($tmp) : null;
            } else {
                $tmp = isset($row[$columnKey]) ? $row[$columnKey] : null;
            }
            if (! $indexKeyIsNull) {
                if ($indexKeyIsNumber) {
                    $key = array_slice($row, $indexKey, 1);
                    $key = (is_array($key) && ! empty($key)) ? current($key) : null;
                    $key = is_null($key) ? 0 : $key;
                } else {
                    $key = isset($row[$indexKey]) ? $row[$indexKey] : 0;
                }
            }
            $result[$key] = $tmp;
        }
        return $result;
    }
}

/**
 * 系统信息弹出解析函数
 *
 * @param string $info_tpl模板
 * @param string $string内容
 * @param string $jump_url跳转地址
 * @param number $time时间
 */
function parse_info_tpl($info_tpl, $string, $jump_url = null, $time = 0)
{
    if (file_exists($info_tpl)) {
        $tpl_content = file_get_contents($info_tpl);
        if ($jump_url) {
            $timeout_js = "<script>var timeout = {time};var showbox = document.getElementById('time');show();function show(){showbox.innerHTML = timeout+ ' 秒后自动跳转';timeout--;if (timeout == 0) {window.location.href = '{url}';}else {setTimeout(function(){show();}, 1000);}}</script>";
        } else {
            $timeout_js = '';
        }
        $tpl_content = str_replace('{js}', $timeout_js, $tpl_content);
        $tpl_content = str_replace('{info}', $string, $tpl_content);
        $tpl_content = str_replace('{url}', $jump_url ?? '', $tpl_content);
        $tpl_content = str_replace('{time}', $time, $tpl_content);
        $tpl_content = str_replace('{sitedir}', SITE_DIR, $tpl_content);
        $tpl_content = str_replace('{coredir}', CORE_DIR, $tpl_content);
        $tpl_content = str_replace('{appversion}', APP_VERSION . '-' . RELEASE_TIME, $tpl_content);
        $tpl_content = str_replace('{serveros}', PHP_OS, $tpl_content);
        $tpl_content = str_replace('{phpversion}', PHP_VERSION, $tpl_content);
        $tpl_content = str_replace('{serversoft}', $_SERVER['SERVER_SOFTWARE'] ?? '', $tpl_content);
        return $tpl_content;
    } else {
        exit('<div style="font-size:50px;">:(</div>提示信息的模板文件不存在！');
    }
}

/**
 * 区域编码校验：仅允许字母、数字、横线、点（不含下划线）
 * 合法返回原字符串，非法返回 false
 *
 * @param mixed $acode
 * @return string|false
 */
function filter_area_acode($acode)
{
    $acode = trim((string) $acode);
    if ($acode === '' || ! preg_match('/^[a-zA-Z0-9\-\.]+$/', $acode)) {
        return false;
    }
    return $acode;
}

/**
 * 区域绑定域名校验：允许可选 http(s):// 与尾部斜杠，返回规范化主机名；
 * 空输入返回 ''；非法返回 false
 *
 * @param mixed $domain
 * @return string|false
 */
function filter_area_domain($domain)
{
    $domain = trim((string) $domain);
    if ($domain === '') {
        return '';
    }
    $parsed = sanitize_redirect_parse_domain_config($domain);
    return $parsed === null ? false : $parsed['host'];
}

/**
 * 规范化主机名（域名或 IPv4/IPv6 字面量）
 *
 * @param string $host
 * @return string
 */
function sanitize_redirect_normalize_host($host)
{
    if ($host !== '' && $host[0] === '[' && substr($host, -1) === ']') {
        $host = substr($host, 1, -1);
    }
    $host = rtrim(strtolower($host), '.');
    if ($host === '') {
        return '';
    }
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
        return $host;
    }
    if (preg_match('/^[a-z0-9]([a-z0-9\-\.]*[a-z0-9])?$/', $host)) {
        return $host;
    }
    return '';
}

/**
 * 解析 HTTP_HOST（支持 bracketed IPv6 与 hostname:port）
 *
 * @param mixed $http_host
 * @return array{host:string,port:int|null}|null
 */
function parse_http_host($http_host)
{
    $http_host = trim((string) $http_host);
    if ($http_host === '') {
        return null;
    }

    $port = null;
    $hostPart = '';

    if ($http_host[0] === '[') {
        if (! preg_match('/^\[([^\]]+)\](?::(\d+))?$/', $http_host, $m)) {
            return null;
        }
        $hostPart = $m[1];
        if (isset($m[2])) {
            $port = (int) $m[2];
        }
    } else {
        $parsed = parse_url('http://' . $http_host);
        if (empty($parsed['host'])) {
            return null;
        }
        $hostPart = $parsed['host'];
        if (isset($parsed['port'])) {
            $port = (int) $parsed['port'];
        }
    }

    $host = sanitize_redirect_normalize_host($hostPart);
    if ($host === '') {
        return null;
    }

    return array('host' => $host, 'port' => $port);
}

/**
 * 跳转参数 -1 哨兵（返回上一页 / Referer）
 *
 * @param mixed $jump_url
 * @return bool
 */
function redirect_is_back_sentinel($jump_url)
{
    return trim((string) $jump_url) === '-1';
}

/**
 * 从域名配置项解析允许的 origin 列表
 *
 * @param mixed $domain
 * @return array{host:string,origins:array<int,array{scheme:string,port:int}>}|null
 */
function sanitize_redirect_parse_domain_config($domain)
{
    $raw = trim((string) $domain);
    if ($raw === '') {
        return null;
    }

    $scheme = null;
    if (preg_match('{^(https?)://}i', $raw, $m)) {
        $scheme = strtolower($m[1]);
        $raw = substr($raw, strlen($m[0]));
    }

    if (strpos($raw, '/') !== false) {
        if (! preg_match('{^[^/?#]+/+$}', $raw)) {
            return null;
        }
        $raw = rtrim($raw, '/');
    }
    if ($raw === '' || strpos($raw, '?') !== false || strpos($raw, '#') !== false) {
        return null;
    }

    $port = null;
    $hostPart = $raw;
    if ($raw[0] === '[') {
        if (! preg_match('/^\[([^\]]+)\](?::(\d+))?$/', $raw, $m)) {
            return null;
        }
        $hostPart = $m[1];
        if (isset($m[2])) {
            $port = (int) $m[2];
        }
    } else {
        $parsed = parse_url('http://' . $raw);
        if (empty($parsed['host'])) {
            return null;
        }
        $hostPart = $parsed['host'];
        if (isset($parsed['port'])) {
            $port = (int) $parsed['port'];
        }
    }

    $host = sanitize_redirect_normalize_host($hostPart);
    if ($host === '') {
        return null;
    }

    if ($scheme !== null) {
        if ($port === null) {
            $port = ($scheme === 'https') ? 443 : 80;
        }
        return array(
            'host' => $host,
            'origins' => array(array('scheme' => $scheme, 'port' => $port)),
        );
    }

    if ($port !== null) {
        return array(
            'host' => $host,
            'origins' => array(array('scheme' => 'http', 'port' => $port)),
        );
    }

    return array(
        'host' => $host,
        'origins' => array(
            array('scheme' => 'http', 'port' => 80),
            array('scheme' => 'https', 'port' => 443),
        ),
    );
}

/**
 * 收集允许作为跳转目标的 origin（当前请求、手机域名、主域名、多语言绑定域名）
 *
 * @return array<string, array<int, array{scheme:string,port:int}>>
 */
function sanitize_redirect_allowed_origins()
{
    $origins = array();

    $merge = function ($host, $items) use (&$origins) {
        if ($host === '') {
            return;
        }
        if (! isset($origins[$host])) {
            $origins[$host] = array();
        }
        foreach ($items as $item) {
            $exists = false;
            foreach ($origins[$host] as $existing) {
                if ($existing['scheme'] === $item['scheme'] && (int) $existing['port'] === (int) $item['port']) {
                    $exists = true;
                    break;
                }
            }
            if (! $exists) {
                $origins[$host][] = $item;
            }
        }
    };

    $self = filter_iframe_request_origin();
    if ($self !== null && $self['host'] !== '') {
        $merge($self['host'], array(array('scheme' => $self['scheme'], 'port' => $self['port'])));
    }

    $addDomain = function ($config) use ($merge) {
        $parsed = sanitize_redirect_parse_domain_config($config);
        if ($parsed !== null) {
            $merge($parsed['host'], $parsed['origins']);
        }
    };

    $wap = Config::get('wap_domain');
    if ($wap) {
        $addDomain($wap);
    }

    $main = Config::get('main_domain');
    if ($main) {
        $addDomain($main);
    }

    $lgs = Config::get('lgs');
    if (is_array($lgs)) {
        foreach ($lgs as $lg) {
            if (empty($lg['domain'])) {
                continue;
            }
            $addDomain($lg['domain']);
        }
    }

    return $origins;
}

/**
 * 绝对 URL 的 origin 是否允许跳转（同 host 与白名单备域均需 scheme/port 一致）
 *
 * @param array{scheme:string,host:string,port:int} $target
 * @return bool
 */
function sanitize_redirect_origin_allowed(array $target)
{
    $allowed = sanitize_redirect_allowed_origins();
    if ($target['host'] === '' || ! isset($allowed[$target['host']])) {
        return false;
    }

    if ($target['scheme'] !== 'http' && $target['scheme'] !== 'https') {
        return false;
    }

    foreach ($allowed[$target['host']] as $origin) {
        if ($target['scheme'] === $origin['scheme'] && (int) $target['port'] === (int) $origin['port']) {
            return true;
        }
    }

    return false;
}

/**
 * 校验并规范化相对跳转地址
 *
 * @param string $url
 * @param string $fallback
 * @return string
 */
function sanitize_redirect_relative($url, $fallback)
{
    if (strpos($url, '//') === 0) {
        return $fallback;
    }

    if (preg_match('~^[^/?#]*:~', $url)) {
        return $fallback;
    }

    $path = $url;
    if (preg_match('/^([^?#]*)/', $url, $m)) {
        $path = $m[1];
    }
    if (strpos($path, '@') !== false) {
        return $fallback;
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
        return $fallback;
    }

    return $url;
}

/**
 * 校验绝对跳转 URL（http/https，拒绝 userinfo 与危险 scheme）
 *
 * @param string $url
 * @param string $fallback
 * @return string
 */
function sanitize_redirect_absolute($url, $fallback)
{
    $self = filter_iframe_request_origin();
    if ($self === null) {
        return $fallback;
    }

    if (strpos($url, '//') === 0) {
        $url = $self['scheme'] . ':' . $url;
    }

    if (preg_match('~^https?://[^/?#]*@~i', $url)) {
        return $fallback;
    }

    $target = filter_iframe_src_origin($url, $self['scheme']);
    if ($target === null || ! sanitize_redirect_origin_allowed($target)) {
        return $fallback;
    }

    return $url;
}

/**
 * 循环解码 HTML 实体直至稳定（防 &amp;#58;、&amp;amp;#58; 等多重绕过）
 *
 * @param string $value
 * @return string
 */
function decode_html_entities_stable($value)
{
    $prev = '';
    while ($prev !== $value) {
        $prev = $value;
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return $value;
}

/**
 * 集中式安全跳转 URL 规范化：允许站内相对路径或经 parse_url 校验的同站/白名单 http(s) 地址
 *
 * @param mixed  $url
 * @param string|null $fallback 非法时回退，默认 '/'
 * @return string
 */
function sanitize_redirect_url($url, $fallback = null)
{
    $fallback = ($fallback !== null && $fallback !== '') ? (string) $fallback : '/';

    if ($url === null || $url === '' || redirect_is_back_sentinel($url)) {
        return $fallback;
    }

    $url = trim((string) $url);
    if ($url === '') {
        return $fallback;
    }

    // HTML 实体解码（防 javascript&#58;、&#47;&#47; 等绕过）
    $url = decode_html_entities_stable($url);

    // C0/DEL 必须在 trim 之前拒绝，防止 &#09;//evil 等被 trim 洗白
    if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
        return $fallback;
    }

    $url = trim($url);
    if ($url === '') {
        return $fallback;
    }

    if (strpos($url, '\\') !== false) {
        return $fallback;
    }

    if (preg_match('/["\'<>]/', $url)) {
        return $fallback;
    }

    if (preg_match('/^\s*(?:javascript|data|vbscript|file)\s*:/i', $url)) {
        return $fallback;
    }

    if (strpos($url, '//') === 0) {
        return sanitize_redirect_absolute($url, $fallback);
    }

    if (preg_match('#^\s*https?://#i', $url)) {
        return sanitize_redirect_absolute($url, $fallback);
    }

    return sanitize_redirect_relative($url, $fallback);
}

/**
 * 解析 error/success/location/alert_location 的跳转参数（含 Referer -1 回退）
 *
 * @param mixed $jump_url
 * @return string|null null 表示不跳转
 */
function redirect_resolve_jump_url($jump_url)
{
    if ($jump_url === null || $jump_url === '') {
        return null;
    }

    if (redirect_is_back_sentinel($jump_url)) {
        if (! isset($_SERVER['HTTP_REFERER']) || $_SERVER['HTTP_REFERER'] === '') {
            return null;
        }
        return sanitize_redirect_url($_SERVER['HTTP_REFERER']);
    }

    return sanitize_redirect_url($jump_url);
}

// 获取转义数据，支持字符串、数组、对象
function escape_string($string)
{
    if (! $string)
        return $string;
    if (is_array($string)) { // 数组处理
        foreach ($string as $key => $value) {
            $string[$key] = escape_string($value);
        }
    } elseif (is_object($string)) { // 对象处理
        foreach ($string as $key => $value) {
            $string->$key = escape_string($value);
        }
    } else { // 字符串处理
        ## 防止跨站脚本攻击 (XSS)
        $string = htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
        ## 防止SQL注入攻击
        $string = addslashes($string);
    }
    return $string;
}

// 解析 fuzzy 参数（0/false/off/no 为精确匹配，其余为模糊匹配；未传时返回 $default）
function parse_fuzzy_param($value, $default = null)
{
    if ($value === null || $value === '') {
        return $default;
    }
    return ! in_array(strtolower((string) $value), array('0', 'false', 'off', 'no'), true);
}

// 构建单标签 SQL 条件（逗号分隔标签集合语义）
function build_tags_where($value, $fuzzy = false)
{
    return build_csv_field_where('a.tags', $value, $fuzzy);
}

// 构建扩展字段筛选 SQL（多选存为逗号分隔，精确模式按集合边界匹配，避免「红色」匹配不到「红色,橙色」）
function build_extfield_where($field, $value, $fuzzy = false)
{
    if (! is_string($field) || ! preg_match('/^ext_[\w\-]+$/i', $field)) {
        return '';
    }
    return build_csv_field_where($field, $value, $fuzzy);
}

// ay_content 表允许用于前台搜索、筛选的字段白名单（单一来源，前台与接口共用）
function content_query_fields()
{
    return array(
        'id', 'acode', 'scode', 'subscode', 'title', 'titlecolor', 'subtitle',
        'filename', 'author', 'source', 'outlink', 'date', 'ico', 'pics',
        'picstitle', 'content', 'tags', 'enclosure', 'keywords', 'description',
        'sorting', 'status', 'istop', 'isrecommend', 'isheadline', 'visits',
        'likes', 'oppose', 'create_user', 'update_user', 'create_time',
        'update_time', 'gtype', 'gid', 'gnote'
    );
}

// 在字段白名单中按大小写不敏感方式解析标识符，返回白名单保留的规范字段名
function canonical_allowlist_field($field, $allowedFields)
{
    if (! is_string($field) || ! is_array($allowedFields)) {
        return '';
    }
    $field = trim($field);
    if ($field === '' || ! preg_match('/^[a-zA-Z_][\w\-]*$/', $field)) {
        return '';
    }
    $lookup = strtolower($field);
    foreach ($allowedFields as $allowedField) {
        if (! is_string($allowedField) || ! preg_match('/^[a-zA-Z_][\w\-]*$/', $allowedField)) {
            continue;
        }
        if (strtolower($allowedField) === $lookup) {
            return $allowedField;
        }
    }
    return '';
}

// 解析搜索字段为安全的列名，非白名单字段返回空字符串
// 扩展字段必须命中真实字段白名单且保持不限定；普通字段补 a. 限定，避免多表关联下的列名歧义
function resolve_search_field($field, $allowedFields = null, $extFields = null)
{
    if (! is_string($field)) {
        return '';
    }
    $field = trim($field);
    if ($field === '') {
        return '';
    }
    if (preg_match('/^ext_[\w\-]+$/i', $field)) {
        return canonical_allowlist_field($field, $extFields);
    }
    if ($allowedFields === null) {
        $allowedFields = content_query_fields();
    }
    if ($field = canonical_allowlist_field($field, $allowedFields)) {
        return 'a.' . $field;
    }
    return '';
}

// 判断 order 参数是否包含需要加载扩展字段白名单的 ext_* 令牌（允许可选 ASC/DESC）
function order_requests_ext_field($rorder)
{
    return (bool) preg_match(
        '/(?:^|,)\s*ext_[\w\-]+(?:\s+(?:ASC|DESC))?\s*(?:,|$)/i',
        (string) $rorder
    );
}

// 解析 order 令牌为安全的限定列名，非白名单字段返回空字符串
// 普通字段补 a. 限定，真实扩展字段补 e. 限定，均保留白名单/表结构的规范拼写
function resolve_order_field($field, $allowedFields = null, $extFields = null)
{
    if (! is_string($field)) {
        return '';
    }
    $field = trim($field);
    if ($field === '') {
        return '';
    }
    $dir = '';
    if (preg_match('/\s+(ASC|DESC)$/i', $field, $m)) {
        $dir = ' ' . strtoupper($m[1]);
        $field = trim(substr($field, 0, -strlen($m[0])));
    }
    if ($field === '') {
        return '';
    }
    if (strpos($field, '.') !== false) {
        return '';
    }
    if (preg_match('/^ext_[\w\-]+$/i', $field)) {
        if (! is_array($extFields)) {
            $extFields = array();
        }
        if ($field = canonical_allowlist_field($field, $extFields)) {
            return 'e.' . $field . $dir;
        }
        return '';
    }
    if ($allowedFields === null) {
        $allowedFields = content_query_fields();
    }
    if ($field = canonical_allowlist_field($field, $allowedFields)) {
        return 'a.' . $field . $dir;
    }
    return '';
}

// 解析自定义 order 字符串（逗号分隔多令牌）；任一令牌非法则返回空字符串
function resolve_content_order_custom($rorder, $allowedFields = null, $extFields = null)
{
    if (! is_string($rorder) || trim($rorder) === '') {
        return '';
    }
    $orders = array();
    foreach (explode(',', $rorder) as $v) {
        if (! $column = resolve_order_field($v, $allowedFields, $extFields)) {
            return '';
        }
        $orders[] = $column;
    }
    return implode(',', $orders);
}

// 转义 LIKE 元字符（配合 ESCAPE '!'，避免 %/_ 放大匹配；! 作转义符可跨 MySQL/SQLite）
function escape_like_metachar($value)
{
    return str_replace(array('!', '%', '_'), array('!!', '!%', '!_'), (string)$value);
}

// 构建逗号分隔字段的 SQL 条件（精确=集合包含；模糊=子串包含）
function build_csv_field_where($column, $value, $fuzzy = false)
{
    if (! is_string($column) || ! preg_match('/^[a-zA-Z_][\w\-]*(?:\.[a-zA-Z_][\w\-]*)?$/', $column)) {
        return '';
    }
    $value = escape_string(trim((string) $value));
    // 字面量 "0" 是合法筛选值，不能按 falsy 丢弃
    if ($value === '') {
        return '';
    }
    $like = escape_like_metachar($value);
    if ($fuzzy) {
        return $column . " like '%" . $like . "%' ESCAPE '!'";
    }
    // 精确：= 比较用原值；边界 LIKE 臂转义 %/_，避免 ?ext_x=% 命中全部含逗号记录
    return "(" . $column . "='" . $value . "' OR " . $column . " like '" . $like . ",%' ESCAPE '!' OR " . $column . " like '%," . $like . "' ESCAPE '!' OR " . $column . " like '%," . $like . ",%' ESCAPE '!')";
}

// 字符反转义html实体及斜杠，支持字符串、数组、对象
function decode_string($string)
{
    if (! $string)
        return $string;
    if (is_array($string)) { // 数组处理
        foreach ($string as $key => $value) {
            $string[$key] = decode_string($value);
        }
    } elseif (is_object($string)) { // 对象处理
        foreach ($string as $key => $value) {
            $string->$key = decode_string($value);
        }
    } else { // 字符串处理
        $string = stripcslashes($string);
        $string = htmlspecialchars_decode($string, ENT_QUOTES);
        $string = preg_replace_r('/pboot:if/i', 'pboot@if', $string); // 避免解码绕过问题
    }
    return $string;
}

// 清洗 CSS 文本中的危险声明（用于 <style> 块与 style 属性）
function sanitize_css($css)
{
    if (! $css || ! is_string($css))
        return $css;

    // @import 外链样式
    $css = preg_replace('/@import\s+[^;}\n]+;?/i', '', $css);

    // IE expression()，支持一层嵌套括号；畸形括号时避免死循环
    $prev = null;
    while ($prev !== $css && preg_match('/expression\s*\(/i', $css)) {
        $prev = $css;
        $css = preg_replace('/expression\s*\((?:[^()]|\([^()]*\))*\)/i', '', $css);
        if ($prev === $css) {
            $css = preg_replace('/expression\s*\([^;}\n]*/i', '', $css);
            break;
        }
    }
    // expression 清除后的空属性/残留括号
    $css = preg_replace('/[a-z_-][\w-]*\s*:\s*(?=[;}])/i', '', $css);
    $css = preg_replace('/\{\s*\}/', '', $css);

    // IE behavior: url()
    $css = preg_replace('/behavior\s*:\s*url\s*\([^)]*\)/i', '', $css);

    // url(javascript:) / url(vbscript:)
    $css = preg_replace('/url\s*\(\s*["\']?\s*javascript\s*:[^)]*\)/i', '', $css);
    $css = preg_replace('/url\s*\(\s*["\']?\s*vbscript\s*:[^)]*\)/i', '', $css);

    // 旧版 Firefox -moz-binding
    $css = preg_replace('/-moz-binding\s*:[^;}]*/i', '', $css);

    return $css;
}

// 移除富文本中针对整站布局的全局劫持规则（保留文章局部 class 样式）
function filter_css_global_hijack_rules($css)
{
    if (! $css || ! is_string($css))
        return $css;

    // 先剔除 display:none 劫持（[^{}]+ 避免把规则体内的 } 误当作选择器边界）
    $css = preg_replace_callback('/([^{}]+)\{([^}]*)\}/is', function ($m) {
        $selector = trim($m[1]);
        $declarations = $m[2];
        if (preg_match('/display\s*:\s*none/i', $declarations) &&
            preg_match('/\b(?:body|html|header|nav|footer|#header)\b/i', $selector)) {
            return '';
        }
        // 裸 a / a:pseudo / a, ... 会影响整页导航与所有外链（富文本 <style> 为全局生效）
        if (preg_match('/^\s*a(?:\s*:[\w-]+)?\s*(?:,|$)/i', $selector) &&
            ! preg_match('/^\s*a[.#\[]/i', $selector)) {
            return '';
        }
        // 通配符劫持
        if (preg_match('/^\s*\*[\s,:#.[]/i', $selector) || preg_match('/^\s*\*\s*$/', $selector)) {
            return '';
        }
        return $m[0];
    }, $css);

    // body/html 伪元素全屏遮罩
    $css = preg_replace('/\b(?:body|html)\s*::\s*(?:before|after)\s*\{[^{}]*\}/is', '', $css);

    // 兜底：剔除残留 body/html display:none（防止规则被破坏后漏网）
    $css = preg_replace('/\b(?:body|html)\s*\{[^}]*display\s*:\s*none[^}]*\}/is', '', $css);

    // 清理空声明与空规则块
    $css = preg_replace('/[a-z_-][\w-]*\s*:\s*(?=[;}])/i', '', $css);
    $css = preg_replace('/[^{};,@\s][^{}]*\{\s*\}/', '', $css);

    return $css;
}

// 过滤 style 属性值，危险内容剔除后尽量保留合法声明
function filter_inline_style_attr($css)
{
    $css = filter_css_global_hijack_rules(sanitize_css($css));
    // 折叠多余分号与空白
    $css = preg_replace('/;\s*;/', ';', $css);
    return trim($css, " \t\n\r\0\x0B;");
}

// 后台 UEditor <script type="text/plain"> 容器输出：解码 + 防止 </script> 破出页面
// 不在此处过滤 iframe：编辑器内容会在保存时原样回写数据库，任何清洗都等于永久删除
// 为 iframe 无引号 src 补双引号，避免 UEditor htmlparser 在 / 处截断（如仅剩 https:）
function normalize_iframe_unquoted_src($html)
{
    if (! is_string($html) || $html === '' || stripos($html, '<iframe') === false) {
        return $html;
    }
    return preg_replace_callback('/<iframe\b([^>]*)>/i', function ($m) {
        $attrs = $m[1];
        if (! preg_match('/\bsrc\s*=\s*(?![\'"])[^\s>]/i', $attrs)) {
            return $m[0];
        }
        $newAttrs = preg_replace_callback(
            '/\bsrc\s*=\s*(?![\'"])([^\s>]+)/i',
            function ($srcMatch) {
                return 'src="' . htmlspecialchars($srcMatch[1], ENT_QUOTES, 'UTF-8') . '"';
            },
            $attrs
        );
        return '<iframe' . $newAttrs . '>';
    }, $html);
}

// 后台富文本入库前规范化：解码 → iframe 无引号 src 补引号 → 再转义
function normalize_richtext_for_storage($content)
{
    if (! is_string($content) || $content === '') {
        return $content;
    }
    return escape_string(normalize_iframe_unquoted_src(decode_string($content)));
}

function ueditor_holder_html($html)
{
    if (! $html || ! is_string($html)) {
        return $html;
    }
    $html = decode_string($html);
    $html = normalize_iframe_unquoted_src($html);
    // HTML 解析器会无视 type=text/plain，字面量 </script> 会提前闭合容器
    $html = preg_replace('/<\/script/i', '<\\/script', $html);
    return $html;
}

// 处理富文本中的 iframe：放行同源（本站）与 content_iframe_whitelist 域名，重建为仅含安全属性
// 的标签并强制 sandbox / referrerpolicy；同源 PDF 免 sandbox；其余整段移除
function filter_html_iframes($html)
{
    if (stripos($html, '<iframe') === false && stripos($html, '</iframe') === false) {
        return $html;
    }

    // 规范化白名单：仅保留主机名（去协议、路径、端口、末尾点），统一小写
    $raw = Config::get('content_iframe_whitelist', true);
    $whitelist = array();
    if (is_array($raw)) {
        foreach ($raw as $item) {
            $host = filter_iframe_normalize_host($item);
            if ($host !== '') {
                $whitelist[$host] = true;
            }
        }
    }

    // 白名单为空也不能删掉全部：同源 iframe 仍需放行，统一交由 filter_iframe_rebuild 判定

    // 命中白名单或同源的 iframe 先重建为安全标签并存入占位符，避免后续清理误伤重建结果
    $placeholders = array();
    $html = preg_replace_callback('/<iframe\b([^>]*)>(.*?)<\/iframe>/is', function ($m) use ($whitelist, &$placeholders) {
        $safe = filter_iframe_rebuild($m[1], $whitelist);
        if ($safe === '') {
            return '';
        }
        $token = "\x01IFRAME_" . count($placeholders) . "\x01";
        $placeholders[$token] = $safe;
        return $token;
    }, $html);

    // 处理未配对的残留开标签（无闭合的畸形 iframe）
    $html = preg_replace_callback('/<iframe\b([^>]*)>/i', function ($m) use ($whitelist, &$placeholders) {
        $safe = filter_iframe_rebuild($m[1], $whitelist);
        if ($safe === '') {
            return '';
        }
        $token = "\x01IFRAME_" . count($placeholders) . "\x01";
        $placeholders[$token] = $safe;
        return $token;
    }, $html);

    // 清理所有残留闭标签，再还原占位符
    $html = preg_replace('/<\/iframe\s*>/i', '', $html);
    if ($placeholders) {
        $html = strtr($html, $placeholders);
    }

    return $html;
}

// 从任意用户输入中提取字面量主机名：支持完整 URL、协议相对 URL、纯域名、bracketed IPv6
function filter_iframe_normalize_host_literal($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if ($value[0] === '[') {
        if (preg_match('/^\[([^\]]+)\]/', $value, $m)) {
            return sanitize_redirect_normalize_host($m[1]);
        }
        return '';
    }

    // 纯域名（无协议、无斜杠）时补一个协议以便 parse_url 解析
    if (strpos($value, '//') === false && strpos($value, '/') === false) {
        $value = 'http://' . $value;
    } elseif (strpos($value, '//') === 0) {
        $value = 'http:' . $value;
    }
    $host = parse_url($value, PHP_URL_HOST);
    if (! $host) {
        return '';
    }
    return sanitize_redirect_normalize_host($host);
}

// 从白名单配置项中提取主机名或通配 pattern（支持 *.example.com、.example.com）
function filter_iframe_normalize_host($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    // Cookie 域写法 .example.com → *.example.com
    if ($value[0] === '.' && strpos($value, '*') === false) {
        $value = '*' . $value;
    }

    // 通配子域 *.example.com（base 须含至少一个点，拒绝 *.com 等单段域误配）
    if (strpos($value, '*.') === 0) {
        $base = filter_iframe_normalize_host_literal(substr($value, 2));
        if ($base === '' || strpos($base, '.') === false) {
            return '';
        }
        return '*.' . $base;
    }

    // 含 * 但非 *. 前缀：非法
    if (strpos($value, '*') !== false) {
        return '';
    }

    return filter_iframe_normalize_host_literal($value);
}

// 将白名单配置串解析为有序 host 列表与命中 map
function filter_iframe_whitelist_parse_hosts($value)
{
    $hosts = array();
    $map = array();
    if (is_string($value) && $value !== '') {
        $value = str_replace("\r\n", ',', $value);
        $value = str_replace('，', ',', $value);
        foreach (explode(',', $value) as $item) {
            $host = filter_iframe_normalize_host($item);
            if ($host !== '' && ! in_array($host, $hosts, true)) {
                $hosts[] = $host;
                $map[$host] = true;
            }
        }
    }
    return array('hosts' => $hosts, 'map' => $map);
}

// 从 iframe 属性串提取最后一个 src（双引号 / 单引号 / 无引号统一规则）
// 自动加白与前台重建共用，避免解析规则漂移；重复 src 时取最后一个
function filter_iframe_extract_src($attr_str)
{
    if (! is_string($attr_str) || $attr_str === '') {
        return '';
    }

    // 无引号用 [^\s>]+：兼容含 ?a=1 的 URL（HTML5 无引号本不含 =，但 CMS/浏览器常见）
    $pattern = '/\bsrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i';
    if (! preg_match_all($pattern, $attr_str, $matches, PREG_SET_ORDER)) {
        return '';
    }

    $match = end($matches);
    if (isset($match[1]) && $match[1] !== '') {
        return trim($match[1]);
    }
    if (isset($match[2]) && $match[2] !== '') {
        return trim($match[2]);
    }
    if (isset($match[3])) {
        return trim($match[3]);
    }

    return '';
}

// 从富文本 HTML 中提取所有 iframe src 的精确 host（去重、小写、不含端口）
function filter_html_extract_iframe_hosts($html)
{
    if (! is_string($html) || $html === '' || stripos($html, '<iframe') === false) {
        return array();
    }
    if (! preg_match_all('/<iframe\b[^>]*>/i', $html, $tags)) {
        return array();
    }
    $found = array();
    foreach ($tags[0] as $tag) {
        $src = filter_iframe_extract_src($tag);
        if ($src === '') {
            continue;
        }
        $src = filter_iframe_sanitize_src($src);
        if ($src === '') {
            continue;
        }
        $parse_src = (strpos($src, '//') === 0) ? 'http:' . $src : $src;
        $host = filter_iframe_normalize_host_literal(parse_url($parse_src, PHP_URL_HOST));
        if ($host !== '' && ! in_array($host, $found, true)) {
            $found[] = $host;
        }
    }
    return $found;
}

// 返回当前白名单（精确或手工通配）尚未覆盖、需要追加的 host 列表
function filter_iframe_whitelist_filter_new_hosts(array $hosts, $current_csv)
{
    $map = filter_iframe_whitelist_parse_hosts($current_csv)['map'];
    $new = array();
    foreach ($hosts as $host) {
        $host = filter_iframe_normalize_host_literal($host);
        if ($host === '' || filter_iframe_host_in_whitelist($host, $map)) {
            continue;
        }
        if (! in_array($host, $new, true)) {
            $new[] = $host;
        }
    }
    return $new;
}

// 将精确 host 幂等合并进白名单逗号串（不推导 *.example.com）
function filter_iframe_whitelist_merge_exact_hosts($current_csv, array $hosts)
{
    $parsed = filter_iframe_whitelist_parse_hosts($current_csv);
    $existing = $parsed['hosts'];
    $map = $parsed['map'];
    $added_hosts = array();

    foreach ($hosts as $host) {
        $host = filter_iframe_normalize_host_literal($host);
        if ($host === '' || filter_iframe_host_in_whitelist($host, $map)) {
            continue;
        }
        $existing[] = $host;
        $map[$host] = true;
        $added_hosts[] = $host;
    }

    return array(
        'ok' => true,
        'added' => count($added_hosts) > 0,
        'added_hosts' => $added_hosts,
        'value' => implode(',', $existing),
    );
}

// 判断 host 是否命中 iframe 白名单（精确 + *.example.com 子域通配，不匹配裸域）
function filter_iframe_host_in_whitelist($host, $whitelist)
{
    if (! is_string($host) || $host === '' || ! is_array($whitelist)) {
        return false;
    }
    if (isset($whitelist[$host])) {
        return true;
    }
    foreach ($whitelist as $pattern => $_) {
        if (strpos($pattern, '*.') !== 0) {
            continue;
        }
        $suffix = substr($pattern, 1);
        $len = strlen($suffix);
        if ($len < 2 || $suffix[0] !== '.') {
            continue;
        }
        if (strlen($host) > $len && substr($host, - $len) === $suffix) {
            return true;
        }
    }
    return false;
}

// 规范化并校验 iframe src，非法则返回空串
function filter_iframe_sanitize_src($src)
{
    if (! is_string($src) || $src === '') {
        return '';
    }

    // HTML 实体解码（防 &#106;avascript: 等绕过）
    $src = decode_html_entities_stable($src);

    $src = trim($src);
    if ($src === '') {
        return '';
    }

    // 拒绝控制字符、空字节、换行
    if (preg_match('/[\x00-\x1F\x7F]/', $src)) {
        return '';
    }

    // 拒绝反斜杠（部分环境对 https:\/\/ 解析不一致）
    if (strpos($src, '\\') !== false) {
        return '';
    }

    // 拒绝已知危险 scheme（解码后再判一次）
    if (preg_match('/^\s*(javascript|vbscript|data)\s*:/i', $src)) {
        return '';
    }

    // 协议相对 URL：补 http: 便于 parse_url
    $parse_src = (strpos($src, '//') === 0) ? 'http:' . $src : $src;

    $scheme = parse_url($parse_src, PHP_URL_SCHEME);
    if ($scheme !== null && ! in_array(strtolower($scheme), array('http', 'https'), true)) {
        return '';
    }

    $host = parse_url($parse_src, PHP_URL_HOST);
    if (! $host) {
        // 根相对路径（/path，无协议无主机）属本站同源资源，放行；危险 scheme 已在上方拦截
        if ($scheme === null && isset($src[0]) && $src[0] === '/' && strpos($src, '//') !== 0) {
            return $src;
        }
        return '';
    }
    if (strpos($host, '@') !== false) {
        return '';
    }

    // host 仅允许合法字符（字母数字.-，支持 punycode xn--）
    if (! preg_match('/^[a-z0-9]([a-z0-9\-\.]*[a-z0-9])?$/i', $host)) {
        return '';
    }

    return $src;
}

// 判断 iframe src 是否与当前站点同源（完整 origin：scheme + host + 端口，三者一致才同源）
// 根相对路径（/path，非 //）恒视为同源；协议相对 URL 按当前请求 scheme 归一
function filter_iframe_is_same_origin($src)
{
    if (! is_string($src) || $src === '') {
        return false;
    }
    if ($src[0] === '/' && strpos($src, '//') !== 0) {
        return true;
    }
    $self = filter_iframe_request_origin();
    if ($self === null) {
        return false;
    }
    $target = filter_iframe_src_origin($src, $self['scheme']);
    if ($target === null) {
        return false;
    }
    return $target['scheme'] === $self['scheme'] && $target['host'] === $self['host'] && $target['port'] === $self['port'];
}

// 当前请求自身 origin：scheme 依 is_https()，host / 端口取自 HTTP_HOST（支持 bracketed IPv6）
function filter_iframe_request_origin()
{
    if (empty($_SERVER['HTTP_HOST'])) {
        return null;
    }
    $parsed = parse_http_host($_SERVER['HTTP_HOST']);
    if ($parsed === null) {
        return null;
    }
    $scheme = is_https() ? 'https' : 'http';
    $port = ($parsed['port'] !== null) ? $parsed['port'] : ($scheme === 'https' ? 443 : 80);
    return array('scheme' => $scheme, 'host' => $parsed['host'], 'port' => $port);
}

// 解析 iframe src 的 origin；协议相对 URL 用请求 scheme 补全；解析不出 host 返回 null
function filter_iframe_src_origin($src, $req_scheme)
{
    $normalized = (strpos($src, '//') === 0) ? $req_scheme . ':' . $src : $src;
    $host = parse_url($normalized, PHP_URL_HOST);
    if (! $host) {
        return null;
    }
    $host = sanitize_redirect_normalize_host($host);
    if ($host === '') {
        return null;
    }
    $scheme = parse_url($normalized, PHP_URL_SCHEME);
    $scheme = ($scheme !== null) ? strtolower($scheme) : $req_scheme;
    $port = parse_url($normalized, PHP_URL_PORT);
    $port = ($port !== null) ? (int) $port : ($scheme === 'https' ? 443 : 80);
    return array('scheme' => $scheme, 'host' => $host, 'port' => $port);
}

// 从属性串提取 allow，仅保留 Permissions Policy 白名单 token（不透传任意值）
function filter_iframe_rebuild_allow($attr_str)
{
    static $allowed = array(
        'accelerometer' => true,
        'autoplay' => true,
        'clipboard-write' => true,
        'encrypted-media' => true,
        'gyroscope' => true,
        'picture-in-picture' => true,
        'web-share' => true,
        'fullscreen' => true
    );

    if (preg_match('/\ballow\s*=\s*(["\'])(.*?)\1/i', $attr_str, $m)) {
        $raw = $m[2];
    } elseif (preg_match('/\ballow\s*=\s*([^\s>]+)/i', $attr_str, $m)) {
        $raw = $m[1];
    } else {
        return '';
    }

    $kept = array();
    $parts = preg_split('/\s*;\s*/', $raw);
    if (! is_array($parts)) {
        return '';
    }
    foreach ($parts as $part) {
        $token = strtolower(trim($part));
        // 仅接受裸 feature 名，拒绝带 allowlist origin 的扩展写法
        if ($token !== '' && isset($allowed[$token])) {
            $kept[$token] = true;
        }
    }
    if (! $kept) {
        return '';
    }

    return 'allow="' . implode('; ', array_keys($kept)) . '"';
}

// 校验单个 iframe 的属性串，命中白名单则返回重建后的安全标签，否则返回空串
function filter_iframe_rebuild($attr_str, $whitelist)
{
    // 提取 src：优先最后一个（HTML 重复属性时多数浏览器取最后一个）
    $src = filter_iframe_extract_src($attr_str);
    if ($src === '') {
        return '';
    }

    $src = filter_iframe_sanitize_src($src);
    if ($src === '') {
        return '';
    }

    $parse_src = (strpos($src, '//') === 0) ? 'http:' . $src : $src;
    $host = rtrim(strtolower((string) parse_url($parse_src, PHP_URL_HOST)), '.');
    $same_origin = filter_iframe_is_same_origin($src);
    if (! $same_origin && ! filter_iframe_host_in_whitelist($host, $whitelist)) {
        return '';
    }

    // 重建：仅保留必要安全属性，其余属性（含 on* 事件）一律丢弃
    $attrs = 'src="' . htmlspecialchars($src, ENT_QUOTES) . '"';

    if (preg_match('/\bwidth\s*=\s*(["\']?)([\d.]+%?)\1/i', $attr_str, $w)) {
        $attrs .= ' width="' . htmlspecialchars($w[2], ENT_QUOTES) . '"';
    }
    if (preg_match('/\bheight\s*=\s*(["\']?)([\d.]+%?)\1/i', $attr_str, $h)) {
        $attrs .= ' height="' . htmlspecialchars($h[2], ENT_QUOTES) . '"';
    }
    if (preg_match('/\btitle\s*=\s*(["\'])(.*?)\1/i', $attr_str, $t)) {
        $attrs .= ' title="' . htmlspecialchars($t[2], ENT_QUOTES) . '"';
    }

    $allow = filter_iframe_rebuild_allow($attr_str);
    if ($allow !== '') {
        $attrs .= ' ' . $allow;
    }

    // 强制安全属性；YouTube 等嵌入需 Referer，不得使用 no-referrer
    // sandbox 下需显式声明 allow-fullscreen 才能全屏
    $attrs .= ' frameborder="0" loading="lazy" referrerpolicy="strict-origin-when-cross-origin"';
    // 同源 PDF 免 sandbox：Chrome 禁止在 sandbox iframe 内渲染 PDF（否则前台「已被 Chrome 屏蔽」）
    $src_path = (string) parse_url($parse_src, PHP_URL_PATH);
    $is_same_origin_pdf = $same_origin && $src_path !== '' && preg_match('/\.pdf$/i', $src_path);
    if (! $is_same_origin_pdf) {
        $attrs .= ' sandbox="allow-scripts allow-same-origin allow-popups allow-presentation allow-forms allow-fullscreen"';
    }
    $attrs .= ' allowfullscreen';

    return '<iframe ' . $attrs . '></iframe>';
}

// 过滤HTML内容中的危险标签和属性，保留安全的HTML标签
// 用于富文本内容字段（如文章content），允许显示格式化内容但阻止XSS攻击
function filter_html($html)
{
    if (! $html || ! is_string($html))
        return $html;

    // 0. 优先处理 iframe：命中白名单域名的重建为安全属性版本，其余整段移除
    $html = filter_html_iframes($html);

    // 1. 移除所有危险标签（script, object, embed, applet, form, base, meta, link, svg等）
    // 注意：iframe 已在上一步单独处理，不再纳入黑名单，否则会误删白名单 iframe 的闭标签
    $dangerous_tags = array(
        'script', 'object', 'embed', 'applet', 'form', 'input',
        'button', 'select', 'textarea', 'base', 'meta', 'link', 'svg',
        'math', 'noscript', 'template', 'frame', 'frameset', 'body', 'head'
    );
    foreach ($dangerous_tags as $tag) {
        // 移除开标签、闭标签和自闭合标签
        $html = preg_replace('/<' . $tag . '[\s>\/][^>]*>/i', '', $html);
        $html = preg_replace('/<\/' . $tag . '[^>]*>/i', '', $html);
        $html = preg_replace('/<' . $tag . '\s*\/?>/i', '', $html);
    }

    // 2. 移除所有 on 开头的事件属性（onclick, onerror, onload, onmouseover等）
    $html = preg_replace('/\s+on\w+\s*=\s*(["\']?)[^>"\']*\1/i', '', $html);
    // 处理无引号的事件属性
    $html = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $html);

    // 3. 移除 javascript: 和 vbscript: 协议
    $html = preg_replace('/href\s*=\s*(["\']?)\s*javascript\s*:[^>"\']*\1/i', 'href="#"', $html);
    $html = preg_replace('/href\s*=\s*(["\']?)\s*vbscript\s*:[^>"\']*\1/i', 'href="#"', $html);
    $html = preg_replace('/src\s*=\s*(["\']?)\s*javascript\s*:[^>"\']*\1/i', '', $html);
    $html = preg_replace('/src\s*=\s*(["\']?)\s*vbscript\s*:[^>"\']*\1/i', '', $html);
    // 处理无引号的协议
    $html = preg_replace('/href\s*=\s*javascript\s*:[^\s>]+/i', 'href="#"', $html);
    $html = preg_replace('/src\s*=\s*javascript\s*:[^\s>]+/i', '', $html);

    // 4. 移除 data: 协议中的危险内容（仅允许图片data URI）
    $html = preg_replace('/src\s*=\s*(["\']?)\s*data\s*:(?!image\/(png|jpeg|jpg|gif|webp|bmp))[^>"\']*\1/i', '', $html);

    // 5. 清洗 <style> 块：保留合法排版，剔除恶意 CSS
    $html = preg_replace_callback('/<style\b([^>]*)>(.*?)<\/style>/is', function ($matches) {
        $attrs = preg_replace('/\s+on\w+\s*=\s*[^\s>]*/i', '', $matches[1]);
        $css = filter_css_global_hijack_rules(sanitize_css($matches[2]));
        $css = trim($css);
        if ($css === '') {
            return '';
        }
        return '<style' . $attrs . '>' . $css . '</style>';
    }, $html);
    // 移除空 <style/> 自闭合标签
    $html = preg_replace('/<style\b[^>]*\/>/i', '', $html);

    // 6. 清洗 style 属性（双引号 / 单引号）
    $html = preg_replace_callback('/\s+style\s*=\s*(")([^"]*)"/is', function ($matches) {
        $css = filter_inline_style_attr($matches[2]);
        return $css === '' ? '' : ' style="' . $css . '"';
    }, $html);
    $html = preg_replace_callback("/\s+style\s*=\s*(')([^']*)'/is", function ($matches) {
        $css = filter_inline_style_attr($matches[2]);
        return $css === '' ? '' : " style='" . $css . "'";
    }, $html);
    // 无引号 style 属性：含危险模式则整段移除
    $html = preg_replace('/\s+style\s*=\s*[^"\'>\s][^>]*(?:expression|@import|behavior\s*:\s*url|javascript\s*:)/i', '', $html);

    // 7. 移除 XML相关危险内容
    $html = preg_replace('/<\?xml[^>]*\?>/i', '', $html);
    $html = preg_replace('/<!\[CDATA\[/i', '', $html);
    $html = preg_replace('/\]\]>/i', '', $html);

    // 8. 移除HTML注释中的条件注释（IE条件注释可执行代码）
    $html = preg_replace('/<!--\[if\s/i', '&lt;!--[if ', $html);
    $html = preg_replace('/<!\[endif\]-->/i', '<![endif]--&gt;', $html);

    return $html;
}

// 字符反转义斜杠，支持字符串、数组、对象
function decode_slashes($string)
{
    if (! $string)
        return $string;
    if (is_array($string)) { // 数组处理
        foreach ($string as $key => $value) {
            $string[$key] = decode_slashes($value);
        }
    } elseif (is_object($string)) { // 对象处理
        foreach ($string as $key => $value) {
            $string->$key = decode_slashes($value);
        }
    } else { // 字符串处理
        $string = stripcslashes($string);
    }
    return $string;
}

// 字符串双层MD5加密
function encrypt_string($string)
{
    return md5(md5($string));
}

// 生成唯一标识符
function get_uniqid()
{
    return encrypt_string(uniqid(mt_rand(), true));
}

// 清洗html代码的空白符号
function clear_html_blank($string)
{
    $string = str_replace("\r\n", '', $string); // 清除换行符
    $string = str_replace("\n", '', $string); // 清除换行符
    $string = str_replace("\t", '', $string); // 清除制表符
    $string = str_replace('　', '', $string); // 清除大空格
    $string = str_replace('&nbsp;', '', $string); // 清除 &nbsp;
    $string = preg_replace('/\s+/', ' ', $string); // 清除空格
    return $string;
}

// 去除字符串两端斜线
function trim_slash($string)
{
    return trim($string, '/');
}

// 驼峰转换下划线加小写字母
function hump_to_underline($string)
{
    return strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $string));
}

// 转换对象为数组
function object_to_array($object)
{
    if($object === null){
        return [];
    }else{
        return json_decode(json_encode($object),true);
    }
}

// 转换数组为对象
function array_to_object($array)
{
    return json_decode(json_encode($array));
}

// 值是否在对象中
function in_object($needle, $object)
{
    foreach ($object as $value) {
        if ($needle == $value)
            return true;
    }
}

// 结果集中查找指定字段父节点是否存在
function result_value_search($needle, $result, $skey)
{
    foreach ($result as $key => $value) {
        if ($value->$skey == $needle) {
            return $key;
        }
    }
    return false;
}

// 多维数组合并
function mult_array_merge($array1, $array2)
{
    if (is_array($array2)) {
        foreach ($array2 as $key => $value) {
            if (is_array($value)) {
                if (array_key_exists($key, $array1)) {
                    $array1[$key] = mult_array_merge($array1[$key], $value);
                } else {
                    $array1[$key] = $value;
                }
            } else {
                $array1[$key] = $value;
            }
        }
    }
    return $array1;
}

// 数组转换为带引号字符串
function implode_quot($glue, array $pieces, $diffnum = false)
{
    if (! $pieces)
        return "''";
    foreach ($pieces as $key => $value) {
        if ($diffnum && ! is_numeric($value)) {
            $value = "'$value'";
        } elseif (! $diffnum) {
            $value = "'$value'";
        }
        if (isset($string)) {
            $string .= $glue . $value;
        } else {
            $string = $value;
        }
    }
    return $string;
}

// 是否为多维数组,是返回true
function is_multi_array($array)
{
    if (is_array($array)) {
        return (count($array) != count($array, 1));
    } else {
        return false;
    }
}

// 是否为移动设备
function is_mobile()
{
    $os = get_user_os();
    if ($os == 'Android' || $os == 'iPhone' || $os == 'Windows Phone' || $os == 'iPad' || $os == 'HarmonyOS' || $os == 'HarmonyOS Pad') {
        return true;
    }
    return false;
}

// 是否为POST请求
function is_post()
{
    if ($_POST) {
        return true;
    } else {
        return false;
    }
}

// 是否为GET请求
function is_get()
{
    if ($_GET) {
        return true;
    } else {
        return false;
    }
}

// 是否为PUT请求
function is_put()
{
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        return true;
    } else {
        return false;
    }
}

// 是否为PATCH请求
function is_patch()
{
    if ($_SERVER['REQUEST_METHOD'] == 'PATCH') {
        return true;
    } else {
        return false;
    }
}

// 是否为DELETE请求
function is_delete()
{
    if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
        return true;
    } else {
        return false;
    }
}

// 是否为AJAX请求
function is_ajax()
{
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        return true;
    } else {
        return false;
    }
}

// 判断当前是否为https
function is_https()
{
    if ((isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on')) {
        return true;
    } elseif (isset($_SERVER['REQUEST_SCHEME']) && strtolower($_SERVER['REQUEST_SCHEME']) == 'https') {
        return true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https') {
        return true;
    } elseif (isset($_SERVER['HTTP_X_CLIENT_SCHEME']) && strtolower($_SERVER['HTTP_X_CLIENT_SCHEME']) == 'https') {
        return true;
    } else {
        return false;
    }
}

// 获取当前访问地址
function get_http_url($noport = false)
{
    if (is_https()) {
        $url = 'https://' . $_SERVER['HTTP_HOST'];
    } else {
        $url = 'http://' . $_SERVER['HTTP_HOST'];
    }
    if ($noport) {
        $url = str_replace(':' . $_SERVER['SERVER_PORT'], '', $url);
    }
    return $url;
}

// 获取当前访问域名
function get_http_host($noport = true)
{
    if ($noport) {
        return str_replace(':' . $_SERVER['SERVER_PORT'], '', $_SERVER['HTTP_HOST']);
    } else {
        return $_SERVER['HTTP_HOST'];
    }
}

// 服务器信息
function get_server_info()
{
    // 定义输出常量
    define('YES', 'Yes');
    define('NO', '<span style="color:red">No</span>');
    
    // 服务器系统
    $data['php_os'] = PHP_OS;
    // 服务器访问地址
    $data['http_host'] = $_SERVER['HTTP_HOST'] ?? '';
    // 服务器名称
    $data['server_name'] = $_SERVER['SERVER_NAME'] ?? '';
    // 服务器端口
    $data['server_port'] = $_SERVER['SERVER_PORT'] ?? '';
    // 服务器地址
    $data['server_addr'] = $_SERVER['LOCAL_ADDR'] ?? $_SERVER['SERVER_ADDR'] ?? '';
    // 服务器软件
    $data['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? '';
    // 站点目录
    $data['document_root'] = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : DOC_PATH;
    // PHP版本
    $data['php_version'] = PHP_VERSION;
    // 建议的最低PHP版本，仅用于后台提示文案
    $data['php_min_version'] = '7.4';
    // PHP版本是否低于建议的最低版本，仅用于后台展示提醒，不做任何拦截
    // 用PHP_VERSION_ID整数比较，避免version_compare把7.4.0-dev、7.4.0RC1等预发布版误判为过低
    $data['php_version_low'] = PHP_VERSION_ID < 70400;
    // 数据库驱动
    $data['db_driver'] = Config::get('database.type');
    // php配置文件
    $data['php_ini'] = @php_ini_loaded_file();
    // 最大上传
    $data['upload_max_filesize'] = ini_get('upload_max_filesize');
    // 最大提交
    $data['post_max_size'] = ini_get('post_max_size');
    // 最大提交文件数
    $data['max_file_uploads'] = ini_get('max_file_uploads');
    // 内存限制
    $data['memory_limit'] = ini_get('memory_limit');
    // 检测gd扩展
    $data['gd'] = extension_loaded('gd') ? YES : NO;
    // 图像后端：配置值、主路径 GD；AVIF 按能力择优（见 image_cap_avif）
    if (function_exists('image_backend_config')) {
        $matrix = image_capability_matrix();
        $im = isset($matrix['imagick']) && is_array($matrix['imagick']) ? $matrix['imagick'] : array();
        $data['image_backend_config'] = image_backend_config();
        $data['image_backend_effective'] = image_backend_effective();
        $data['imagick'] = ! empty($im['loaded']) ? YES : NO;
        $data['imagick_usable'] = ! empty($im['usable']) ? YES : NO;
        $data['imagick_version'] = ! empty($im['version']) ? $im['version'] : '-';
        $data['imagick_unusable_reason'] = (! empty($im['usable']) || empty($im['unusable_reason']))
            ? '-'
            : (string) $im['unusable_reason'];
        $data['imagick_policy_status'] = function_exists('imagick_policy_registry_status')
            ? imagick_policy_registry_status($im)
            : '-';
        $deepAt = ! empty($im['deep_probe_at']) ? (int) $im['deep_probe_at'] : 0;
        if ($deepAt > 0) {
            $runtime = ! empty($im['deep_probe_runtime_coders']) && is_array($im['deep_probe_runtime_coders'])
                ? implode(',', $im['deep_probe_runtime_coders'])
                : '无';
            $data['imagick_deep_probe'] = date('Y-m-d H:i:s', $deepAt) . '；runtime 可读：' . $runtime;
        } else {
            $data['imagick_deep_probe'] = '未检测（可在下方按钮触发）';
        }
        $data['image_cap_avif'] = 'GD：' . (! empty($matrix['avif']['gd']) ? YES : NO)
            . ' / Imagick：' . (! empty($matrix['avif']['imagick']) ? YES : NO);
        $data['image_cap_heic'] = 'GD：' . (! empty($matrix['heic']['gd']) ? YES : NO)
            . ' / Imagick：' . (! empty($matrix['heic']['imagick']) ? YES : NO);
        $data['image_cap_animated_gif'] = 'GD：' . (! empty($matrix['animated_gif']['gd']) ? YES : NO)
            . ' / Imagick：' . (! empty($matrix['animated_gif']['imagick']) ? YES : NO);
        $data['image_cap_webp'] = 'GD：' . (! empty($matrix['webp']['gd']) ? YES : NO)
            . ' / Imagick：' . (! empty($matrix['webp']['imagick']) ? YES : NO);
    } else {
        $data['image_backend_config'] = 'auto';
        $data['image_backend_effective'] = 'gd';
        $data['imagick'] = NO;
        $data['imagick_usable'] = NO;
        $data['imagick_version'] = '-';
        $data['imagick_unusable_reason'] = '-';
        $data['imagick_policy_status'] = '-';
        $data['imagick_deep_probe'] = '-';
        $data['image_cap_avif'] = 'GD：' . NO . ' / Imagick：' . NO;
        $data['image_cap_heic'] = 'GD：' . NO . ' / Imagick：' . NO;
        $data['image_cap_animated_gif'] = 'GD：' . NO . ' / Imagick：' . NO;
        $data['image_cap_webp'] = 'GD：' . NO . ' / Imagick：' . NO;
    }
    // 检测imap扩展
    $data['imap'] = extension_loaded('imap') ? YES : NO;
    // 检测socket扩展
    $data['sockets'] = extension_loaded('sockets') ? YES : NO;
    // 检测curl扩展
    $data['curl'] = extension_loaded('curl') ? YES : NO;
    // 会话保存路径
    $data['session_save_path'] = session_save_path() ?: ($_SERVER['TMP'] ?? '');
    // 检测standard库是否存在
    $data['standard'] = extension_loaded('standard') ? YES : NO;
    // 检测多线程支持
    $data['pthreads'] = extension_loaded('pthreads') ? YES : NO;
    // 检测XCache支持
    $data['xcache'] = extension_loaded('XCache') ? YES : NO;
    // 检测APC支持
    $data['apc'] = extension_loaded('APC') ? YES : NO;
    // 检测eAccelerator支持
    $data['eaccelerator'] = extension_loaded('eAccelerator') ? YES : NO;
    // 检测wincache支持
    $data['wincache'] = extension_loaded('wincache') ? YES : NO;
    // 检测ZendOPcache支持
    $data['zendopcache'] = extension_loaded('Zend OPcache') ? YES : NO;
    // 检测memcache支持
    $data['memcache'] = extension_loaded('memcache') ? YES : NO;
    // 检测memcached支持
    $data['memcached'] = extension_loaded('memcached') ? YES : NO;
    // 已经安装模块
    $loaded_extensions = get_loaded_extensions();
    $extensions = '';
    foreach ($loaded_extensions as $key => $value) {
        $extensions .= $value . ', ';
    }
    $data['extensions'] = $extensions;
    return json_decode(json_encode($data));
}

// 获取数据库类型
function get_db_type()
{
    switch (Config::get('database.type')) {
        case 'mysqli':
        case 'pdo_mysql':
            $db = 'mysql';
            break;
        case 'sqlite':
        case 'pdo_sqlite':
            $db = 'sqlite';
            break;
        case 'pdo_pgsql':
            $db = 'pgsql';
            break;
        default:
            $db = null;
    }
    return $db;
}

// 获取间隔的月份的起始及结束日期
function get_month_days($date, $start = 0, $interval = 1, $retamp = false)
{
    $timestamp = strtotime($date) ?: $date;
    $first_day = strtotime(date('Y', $timestamp) . '-' . date('m', $timestamp) . '-01 +' . $start . ' month');
    $last_day = strtotime(date('Y-m-d', $first_day) . ' +' . $interval . ' month -1 day');
    if ($retamp) {
        $return = array(
            'first' => $first_day,
            'last' => $last_day
        );
    } else {
        $return = array(
            'first' => date('Y-m-d', $first_day),
            'last' => date('Y-m-d', $last_day)
        );
    }
    return $return;
}

// 框架地址地址前缀
function url_index_path($indexfile = null)
{
    $indexfile = $indexfile ?: $_SERVER["SCRIPT_NAME"];
    if (Config::get('app_url_type') == 2 && strripos($indexfile, 'index.php') !== false) {
        return SITE_DIR;
    } elseif (Config::get('app_url_type') == 3 && strripos($indexfile, 'index.php') !== false) {
        return SITE_DIR . '/?p=';
    } elseif (Config::get('app_url_type') == 3 && strripos($indexfile, 'index.php') === false) {
        return $indexfile . '?p=';
    } else {
        return $indexfile;
    }
}

// 获取服务端web软件
function get_server_soft()
{
    $soft = strtolower($_SERVER["SERVER_SOFTWARE"] ?? '');
    if (strpos($soft, 'iis')) {
        return 'iis';
    } elseif (strpos($soft, 'apache')) {
        return 'apache';
    } elseif (strpos($soft, 'nginx')) {
        return 'nginx';
    } else {
        return 'other';
    }
}

// 创建会话层级目录
function create_session_dir($path, $depth)
{
    if ($depth < 1) {
        return;
    } else {
        $depth --;
    }
    $char = array(
        0,
        1,
        2,
        3,
        4,
        5,
        6,
        7,
        8,
        9,
        'a',
        'b',
        'c',
        'd',
        'e',
        'f',
        'g',
        'h',
        'i',
        'j',
        'k',
        'l',
        'm',
        'n',
        'o',
        'p',
        'q',
        'r',
        's',
        't',
        'u',
        'v'
    );
    
    foreach ($char as $value) {
        if (! check_dir($path . '/' . $value, true)) {
            error('会话目录写入权限不足！');
        }
        create_session_dir($path . '/' . $value, $depth);
    }
}

// 中英混合的字符串截取,以一个汉字为一个单位长度，英文为半个
function substr_both($string, $strat, $length)
{
    $s = 0; // 起始位置
    $i = 0; // 实际Byte计数
    $n = 0; // 字符串长度计数
    $str_length = strlen($string); // 字符串的字节长度
    while (($n < $length) and ($i < $str_length)) {
        $ascnum = Ord(substr($string, $i, 1)); // 得到字符串中第$i位字符的ascii码
        if ($ascnum >= 224) { // 根据UTF-8编码规范，将3个连续的字符计为单个字符
            $i += 3;
            $n ++;
        } elseif ($ascnum >= 192) { // 根据UTF-8编码规范，将2个连续的字符计为单个字符
            $i += 2;
            $n ++;
        } else {
            $i += 1;
            $n += 0.5;
        }
        if ($s == 0 && $strat > 0 && $n >= $strat) {
            $s = $i; // 记录起始位置
        }
    }
    if ($n < $strat) { // 起始位置大于字符串长度
        return;
    }
    return substr($string, $s, $i);
}

// 中英混合的字符串长度,以一个汉字为一个单位长度，英文为半个
function strlen_both($string)
{
    $i = 0; // 实际Byte计数
    $n = 0; // 字符串长度计数
    $str_length = strlen($string); // 字符串的字节长度
    while ($i < $str_length) {
        $ascnum = Ord(substr($string, $i, 1)); // 得到字符串中第$i位字符的ascii码
        if ($ascnum >= 224) { // 根据UTF-8编码规范，将3个连续的字符计为单个字符
            $i += 3;
            $n ++;
        } elseif ($ascnum >= 192) { // 根据UTF-8编码规范，将2个连续的字符计为单个字符
            $i += 2;
            $n ++;
        } else {
            $i += 1;
            $n += 0.5;
        }
    }
    return $n;
}

// 获取地址参数
function query_string($unset = null)
{
    if (isset($_SERVER["QUERY_STRING"]) && ! ! $qs = $_SERVER["QUERY_STRING"]) {
        parse_str($qs, $output);
        unset($output['page']);
        $unset = strpos($unset, ',') ? explode(',', $unset) : $unset;
        
        if (is_array($unset)) {
            foreach ($unset as $value) {
                if (isset($output[$value])) {
                    unset($output[$value]);
                }
            }
        } else {
            if (isset($output[$unset])) {
                unset($output[$unset]);
            }
        }
        // 避免路径参数编码
        if (isset($output['p'])) {
            $p = 'p=' . $output['p'];
            unset($output['p']);
            $qs = $output ? $p . '&' . http_build_query($output) : $p;
        } else {
            $qs = http_build_query($output);
        }
    }
    return $qs ? '?' . $qs : '';
}

// 判断是否在子网
function network_match($ip, $network)
{
    if (strpos($network, '/') > 0) {
        $network = explode('/', $network);
        $move = 32 - $network[1];
        if ($network[1] == 0) {
            return true;
        }
        return ((ip2long($ip) >> $move) === (ip2long($network[0]) >> $move)) ? true : false;
    } elseif ($network == $ip) {
        return true;
    } else {
        return false;
    }
}

// 递归替换
function preg_replace_r($search, $replace, $subject)
{
    while (preg_match($search, $subject)) {
        $subject = preg_replace($search, $replace, $subject);
    }
    return $subject;
}

// 找回密码验证码：绝对有效期（秒）与连续失败上限
define('RETRIEVE_CODE_TTL', 900);
define('RETRIEVE_CODE_MAX_FAIL', 5);

// 验证码字符集（排除易混淆字符）
function retrieve_code_charset()
{
    return 'ABCDEFGHKMNPRTUVWXY23456789';
}

// 生成随机验证码（CSPRNG，默认 6 位）
function create_code($len = 6)
{
    $charset = retrieve_code_charset();
    $max = strlen($charset) - 1;
    $code = '';
    for ($i = 0; $i < $len; $i ++) {
        $code .= $charset[random_int(0, $max)];
    }
    return $code;
}

// 找回密码验证码是否已过期
function retrieve_code_expired($issuedAt, $now = null)
{
    if (! $issuedAt) {
        return true;
    }
    if ($now === null) {
        $now = time();
    }
    return ((int) $now - (int) $issuedAt) >= RETRIEVE_CODE_TTL;
}

// 校验找回密码验证码，返回 status 与 failures
function verify_retrieve_code($inputCode, $inputEmail, array $session, $now = null)
{
    $code = isset($session['retrieve_checkcode']) ? (string) $session['retrieve_checkcode'] : '';
    $email = isset($session['retrieve_email']) ? (string) $session['retrieve_email'] : '';
    $issuedAt = isset($session['retrieve_checkcode_time']) ? $session['retrieve_checkcode_time'] : null;
    $failures = isset($session['retrieve_checkcode_failures']) ? (int) $session['retrieve_checkcode_failures'] : 0;

    if (! $code || ! $email) {
        return array(
            'status' => 'missing',
            'failures' => $failures
        );
    }

    if (retrieve_code_expired($issuedAt, $now)) {
        return array(
            'status' => 'expired',
            'failures' => $failures
        );
    }

    if ($failures >= RETRIEVE_CODE_MAX_FAIL) {
        return array(
            'status' => 'locked',
            'failures' => $failures
        );
    }

    $inputCode = strtolower(trim((string) $inputCode));
    $inputEmail = strtolower(trim((string) $inputEmail));

    if (! hash_equals($code, $inputCode) || ! hash_equals($email, $inputEmail)) {
        $failures ++;
        if ($failures >= RETRIEVE_CODE_MAX_FAIL) {
            return array(
                'status' => 'locked',
                'failures' => $failures
            );
        }
        return array(
            'status' => 'mismatch',
            'failures' => $failures
        );
    }

    return array(
        'status' => 'ok',
        'failures' => $failures
    );
}

// 清理找回密码验证码相关 session
function clear_retrieve_code_session()
{
    unset($_SESSION['retrieve_checkcode']);
    unset($_SESSION['retrieve_email']);
    unset($_SESSION['retrieve_checkcode_time']);
    unset($_SESSION['retrieve_checkcode_failures']);
}
