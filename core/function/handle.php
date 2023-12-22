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
    
    if (strpos($user_agent, 'windows nt 5.0')) {
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

// 获取用户IP
function get_user_ip(): string
{
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $cip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $cip = $_SERVER['HTTP_CLIENT_IP'];
    } else {
        $cip = $_SERVER['REMOTE_ADDR'];
    }
    if ($cip == '::1') { // 使用localhost时
        $cip = '127.0.0.1';
    }
    if (! preg_match('/^[0-9\.]+$/', $cip)) { // 非标准的IP
        $cip = '0.0.0.0';
    }
    return htmlspecialchars($cip);
}

// 执行URL请求，并返回数据
function get_url($url, $fields = array(), $UserAgent = null, $vfSSL = false)
{
    $SSL = substr($url, 0, 8) == "https://" ? true : false;
    
    $ch = curl_init();
    if ($UserAgent) { // 在HTTP请求中包含一个"User-Agent: "头的字符串。
        curl_setopt($ch, CURLOPT_USERAGENT, $UserAgent);
    } else {
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER["HTTP_USER_AGENT"]);
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
    if (curl_errno($ch)) {
        error('请求远程地址错误：' . curl_error($ch));
    }
    curl_close($ch);
    return $output;
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
        $tpl_content = str_replace('{url}', $jump_url, $tpl_content);
        $tpl_content = str_replace('{time}', $time, $tpl_content);
        $tpl_content = str_replace('{sitedir}', SITE_DIR, $tpl_content);
        $tpl_content = str_replace('{coredir}', CORE_DIR, $tpl_content);
        $tpl_content = str_replace('{appversion}', APP_VERSION . '-' . RELEASE_TIME, $tpl_content);
        $tpl_content = str_replace('{serveros}', PHP_OS, $tpl_content);
        $tpl_content = str_replace('{serversoft}', $_SERVER['SERVER_SOFTWARE'], $tpl_content);
        return $tpl_content;
    } else {
        exit('<div style="font-size:50px;">:(</div>提示信息的模板文件不存在！');
    }
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
    if ($os == 'Android' || $os == 'iPhone' || $os == 'Windows Phone' || $os == 'iPad') {
        return true;
    }
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
    $data['http_host'] = $_SERVER['HTTP_HOST'];
    // 服务器名称
    $data['server_name'] = $_SERVER['SERVER_NAME'];
    // 服务器端口
    $data['server_port'] = $_SERVER['SERVER_PORT'];
    // 服务器地址
    $data['server_addr'] = isset($_SERVER['LOCAL_ADDR']) ? $_SERVER['LOCAL_ADDR'] : $_SERVER['SERVER_ADDR'];
    // 服务器软件
    $data['server_software'] = $_SERVER['SERVER_SOFTWARE'];
    // 站点目录
    $data['document_root'] = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : DOC_PATH;
    // PHP版本
    $data['php_version'] = PHP_VERSION;
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
    // 检测imap扩展
    $data['imap'] = extension_loaded('imap') ? YES : NO;
    // 检测socket扩展
    $data['sockets'] = extension_loaded('sockets') ? YES : NO;
    // 检测curl扩展
    $data['curl'] = extension_loaded('curl') ? YES : NO;
    // 会话保存路径
    $data['session_save_path'] = session_save_path() ?: $_SERVER['TMP'];
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
    $soft = strtolower($_SERVER["SERVER_SOFTWARE"]);
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

// 生成随机验证码
function create_code($len = 4)
{
    $charset = 'ABCDEFGHKMNPRTUVWXY23456789';
    $charset = str_shuffle($charset);
    $charlen = strlen($charset) - 1;
    $code = '';
    for ($i = 0; $i < $len; $i ++) {
        $code .= $charset[mt_rand(0, $charlen)];
    }
    return $code;
}



