<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年11月5日
 *  助手函数
 */
use core\basic\Config;
use core\basic\Json;
use core\view\View;
use core\view\Paging;
use core\basic\Response;
use core\basic\Url;
use core\basic\Basic;
use core\basic\Smtp;

/**
 * 生成实际跳转路径
 *
 * @param string $url
 *            接收控制器方法访问完整路径，如：/home/index/index
 * @return mixed
 */
function url($url, $suffix = false)
{
    return Url::get($url, $suffix);
}

/**
 * 生成前端路径
 *
 * @param string $url
 *            前端地址参数
 * @return mixed
 */
function homeurl($url, $suffix = null, $qs = null)
{
    return Url::home($url, $suffix, $qs);
}

/**
 * 自定义错误页面
 *
 * @param string $_string内容
 * @param string $_url跳转地址
 * @param number $_time时间
 */
function error($string, $jump_url = null, $time = 2)
{
    @ob_clean();
    http_response_code(404);
    if (! $string)
        $string = '未知错误！';
    
    if ($jump_url == '-1' && isset($_SERVER['HTTP_REFERER'])) {
        $jump_url = $_SERVER['HTTP_REFERER'];
        if (strpos($jump_url, get_http_url()) !== 0) {
            $jump_url = '/';
        }
    } elseif ($jump_url == '-1') {
        $jump_url = null;
    }
    if (Config::get('return_data_type') == 'json' || is_ajax()) { // 接口模型返回格式数据
        Response::json(0, strip_tags($string), $jump_url);
    } else {
        $err_tpl = CORE_PATH . '/template/error.html';
        echo parse_info_tpl($err_tpl, $string, $jump_url, $time);
    }
    exit();
}

/**
 * 自定义错误页面
 *
 * @param string $_string内容
 * @param string $_url跳转地址
 * @param number $_time时间
 */
function success($string, $jump_url = null, $time = 2)
{
    if ($jump_url == '-1' && isset($_SERVER['HTTP_REFERER'])) {
        $jump_url = $_SERVER['HTTP_REFERER'];
        if (strpos($jump_url, get_http_url()) !== 0) {
            $jump_url = '/';
        }
    } elseif ($jump_url == '-1') {
        $jump_url = null;
    }
    if (Config::get('return_data_type') == 'json' || is_ajax()) { // 接口模型返回格式数据
        Response::json(1, strip_tags($string), $jump_url);
    } else {
        $err_tpl = CORE_PATH . '/template/success.html';
        echo parse_info_tpl($err_tpl, $string, $jump_url, $time);
    }
    exit();
}

/**
 * 弹窗
 *
 * @param string $info信息
 */
function alert($info, $status = 0)
{
    if (Config::get('return_data_type') == 'json' || is_ajax()) { // 接口模型返回格式数据
        Response::json($status, strip_tags($info));
    } else {
        echo '<script type="text/javascript">alert("' . clear_html_blank($info) . '");</script>';
    }
}

/**
 * 弹窗并返回前页
 *
 * @param string $info信息
 */
function alert_back($info, $status = 0)
{
    if (Config::get('return_data_type') == 'json' || is_ajax()) { // 接口模型返回格式数据
        Response::json($status, strip_tags($info));
    } else {
        echo '<script type="text/javascript">alert("' . clear_html_blank($info) . '");window.history.go(-1);</script>';
        exit();
    }
}

/**
 * 跳转
 *
 * @param string $url跳转地址
 */
function location($url)
{
    if ($url == '-1' && isset($_SERVER['HTTP_REFERER'])) {
        $url = $_SERVER['HTTP_REFERER'];
        if (strpos($url, get_http_url()) !== 0) {
            $url = '/';
        }
    }
    header('Location:' . $url);
    exit();
}

/**
 * 弹窗并跳转
 *
 * @param string $info信息
 * @param string $url跳转地址
 */
function alert_location($info, $url, $status = 0)
{
    if ($url == '-1' && isset($_SERVER['HTTP_REFERER'])) {
        $url = $_SERVER['HTTP_REFERER'];
        if (strpos($url, get_http_url()) !== 0) {
            $url = '/';
        }
    }
    if (Config::get('return_data_type') == 'json' || is_ajax()) { // 接口模型返回格式数据
        Response::json($status, strip_tags($info), $url);
    } else {
        echo '<script type="text/javascript">alert("' . clear_html_blank($info) . '");location.href="' . $url . '";</script>';
        exit();
    }
}

/**
 * 弹窗并关闭
 *
 * @param string $info信息
 */
function alert_close($info, $status = 0)
{
    if (Config::get('return_data_type') == 'json' || is_ajax()) { // 接口模型返回格式数据
        Response::json($status, strip_tags($info));
    } else {
        echo '<script type="text/javascript">alert("' . clear_html_blank($info) . '");window.close();</script>';
        exit();
    }
}

/**
 * 实例化模型对象助手
 *
 * @param string $name
 *            需要实力化的模型名称
 * @param string $new
 *            是否强制新建对象
 * @return mixed
 */
function model($name = null, $new = false)
{
    return Basic::createModel($name, $new);
}

/**
 * api读取数据
 *
 * @param string $name
 *            接口名称，如：add或 admin.user.addd
 * @param array $param
 *            参数
 * @param string $rsOriginal
 *            结果不处理直接返回
 * @param string $jsonRsArray
 *            返回Json数组方式
 * @return mixed
 */
function api($args = null)
{
    return Basic::createApi(func_get_args());
}

// 输出模板内容
function display($tpl)
{
    $view = View::getInstance();
    echo $view->parser($tpl);
}

// 解析模板内容
function parser($tpl)
{
    $view = View::getInstance();
    return $view->parser($tpl);
}

// 设置模板
function set_theme($theme_name)
{
    $view = View::getInstance();
    $view->assign('theme', $theme_name);
}

// 注入模板变量
function assign($var, $value)
{
    $view = View::getInstance();
    $view->assign($var, $value);
}

// 变量获取接口
function get_var($var)
{
    $view = View::getInstance();
    return $view->getVar($var);
}

// 手动生成分页信息,返回限制语句
function page($tatal, $morePageStr = false)
{
    $page = Paging::getInstance();
    return $page->limit($tatal, $morePageStr);
}

// 内容输出助手函数
function response($data)
{
    return core\basic\Response::handle($data);
}

// Json内容输出助手函数
function json($code, $data, $tourl = null)
{
    return core\basic\Response::json($code, $data, $tourl);
}

/**
 * 数据过滤
 *
 * @param mixed $varname
 *            字符串或参数名称
 * @param array $condition
 *            array('d_source'=>'post','d_none'=>true,'d_require'=>true,'d_type'=>'int','d_max'=>5,'d_min'=2,'d_default'=>'')
 *            字段名称：$varname,用字段名称做key传递文本
 *            数据源:d_source，[post、get、cookie、session、both、string]
 *            是否允许空：d_none，[true、false],如果为false接受的收据为空，则直接报错
 *            是否必须：d_require，[true、false],如果为true意味着如果数据不满足要求直接报错，否则返回null
 *            数据类型：d_type，[int、float、num、letter、var、bool、date、array、object]
 *            正则表达：d_regular，接受正则表达式
 *            最大值|最大长度：d_max
 *            最小值|最小长度：d_min
 *            默认值：d_default
 * @return mixed
 */
function filter($varname, $condition)
{
    // 变量名称文本
    if (array_key_exists($varname, $condition) && $condition[$varname]) {
        $vartext = $condition[$varname];
    } else {
        $vartext = $varname;
    }
    
    // 数据源
    if (array_key_exists('d_source', $condition)) {
        switch ($condition['d_source']) {
            case 'post':
                $data = @$_POST[$varname];
                break;
            case 'get':
                $data = @$_GET[$varname];
                break;
            case 'cookie':
                $data = @$_COOKIE[$varname];
                break;
            case 'session':
                $data = session($varname);
                break;
            case 'both':
                $data = @$_POST[$varname] ?: @$_GET[$varname];
                break;
            case 'string':
                $data = $varname;
            default:
                error($vartext . '数据获取方式设置错误！');
        }
        // 去空格
        if (is_string($data))
            $data = trim($data);
    } else {
        $data = $varname; // 没有数据源指定时直接按照字符串过滤处理
    }
    
    // 数据为空时,进行是否允许空检测
    if (! $data && array_key_exists('d_none', $condition) && $condition['d_none'] === false) {
        error($vartext . '不能为空！');
    }
    
    // 判断是否强制检测，为true时，意味着如果数据不满足要求直接报错，否则返回null
    if (array_key_exists('d_require', $condition) && $condition['d_require'] == true) {
        $require = true;
    } else {
        $require = false;
    }
    
    // 数据类型检测
    if (array_key_exists('d_type', $condition)) {
        switch ($condition['d_type']) {
            case 'int':
                if (! preg_match('/^[0-9]+$/', $data)) {
                    $err = '必须为整数！';
                }
                break;
            case 'float':
                if (! is_float($data)) {
                    $err = '必须为浮点数！';
                }
                break;
            case 'num':
                if (! is_numeric($data)) {
                    $err = '必须为数字！';
                }
                break;
            case 'letter':
                if (! preg_match('/^[a-zA-Z]+$/', $data)) {
                    $err = '只能包含字母！';
                }
                break;
            case 'var':
                if (! preg_match('/^[\w\-\.]+$/', $data)) {
                    $err = '只能包含字母、数字、划线、点！';
                }
                break;
            case 'bool':
                if (! is_bool($data)) {
                    $err = '必须为布尔类型！';
                }
                break;
            case 'date':
                if (! strtotime($data)) {
                    $err = '必须为日期类型！';
                }
                break;
            case 'array':
                if (! is_array($data)) {
                    $err = '必须为数组类型！';
                }
                break;
            case 'object':
                if (! is_object($data)) {
                    $err = '必须为对象类型！';
                }
                break;
            case 'vars':
                if (! preg_match('/^[\x{4e00}-\x{9fa5}\w\-\.,\s]+$/u', $data)) {
                    $err = '只能包含中文、字母、数字、横线、点、逗号、空格！';
                }
                break;
            default:
                if ($condition['d_type'])
                    error($vartext . '数据类型设置错误！');
        }
    }
    
    // 非必须或必须但无错误时执行
    if ((! $require || ($require && ! isset($err)))) {
        
        // 正则匹配
        if (array_key_exists('d_regular', $condition)) {
            if (! preg_match($condition['d_regular'], $data)) {
                $err = '不符合正则表达式规则！';
            }
        }
        // 最大值匹配
        if (array_key_exists('d_max', $condition)) {
            if (is_numeric($data)) {
                if ($data > $condition['d_max']) {
                    $err = '不能大于' . $condition['d_max'];
                }
            } else {
                if (mb_strlen($data) > $condition['d_max']) {
                    $err = '长度不能大于' . $condition['d_max'];
                }
            }
        }
        // 最小值匹配
        if (array_key_exists('d_min', $condition)) {
            if (is_numeric($data)) {
                if ($data < $condition['d_min']) {
                    $err = '不能小于' . $condition['d_min'];
                }
            } else {
                if (mb_strlen($data) < $condition['d_min']) {
                    $err = '长度不能小于' . $condition['d_min'];
                }
            }
        }
    }
    
    // 如果为必须且有错误，则显示错误,如果非必须，但有错误，则设置数据为null
    if ($require && isset($err)) {
        error($vartext . $err);
    } elseif (isset($err)) {
        $data = null;
    }
    
    // 如果设置有默认值，默认值
    if (array_key_exists('d_default', $condition)) {
        $data = (! is_null($data)) ? $data : $condition['d_default'];
    }
    
    if (is_string($data)) {
        $data = trim($data); // 去空格
        $data = preg_replace_r('/(x3c)|(x3e)/', '', $data); // 去十六进制括号
        $data = preg_replace_r('/pboot:if/i', 'pboot@if', $data); // 过滤插入cms条件语句
        $data = preg_replace_r('/pboot:sql/i', 'pboot@sql', $data); // 过滤插入cms条件语句
        $data = preg_replace_r('/GET\[/i', 'GET@[', $data);
        $data = preg_replace_r('/POST\[/i', 'POST@[', $data);
    }
    
    // 销毁错误
    unset($err);
    
    // 返回收据
    return escape_string($data);
}

/**
 * 获取GET参数
 *
 * @param string $name
 *            参数名称
 * @param mixed $type
 *            数据类型
 * @param string $require
 *            是否为必须，为true是，如果不满足条件直接错误
 * @param string $vartext
 *            变量描述文本
 * @param string $default
 *            在非必需情况下默认值
 * @return mixed
 */
function get($name, $type = null, $require = false, $vartext = null, $default = null)
{
    $condition = array(
        'd_source' => 'get',
        'd_type' => $type,
        'd_require' => $require,
        $name => $vartext,
        'd_default' => $default
    
    );
    return filter($name, $condition);
}

/**
 * * 获取POST参数
 *
 * @param string $name
 *            参数名称
 * @param mixed $type
 *            数据类型
 * @param string $require
 *            是否为必须，为true是，如果不满足条件直接错误
 * @param string $vartext
 *            变量描述文本
 * @param string $default
 *            在非必需情况下默认值
 * @return mixed
 */
function post($name, $type = null, $require = false, $vartext = null, $default = null)
{
    $condition = array(
        'd_source' => 'post',
        'd_type' => $type,
        'd_require' => $require,
        $name => $vartext,
        'd_default' => $default
    
    );
    return filter($name, $condition);
}

/**
 * * 获取参数，post或get
 *
 * @param string $name
 *            参数名称
 * @param mixed $type
 *            数据类型
 * @param string $require
 *            是否为必须，为true是，如果不满足条件直接错误
 * @param string $vartext
 *            变量描述文本
 * @param string $default
 *            在非必需情况下默认值
 * @return mixed
 */
function request($name, $type = null, $require = false, $vartext = null, $default = null)
{
    if (isset($_POST[$name])) {
        $d_source = 'post';
    } else {
        $d_source = 'get';
    }
    $condition = array(
        'd_source' => $d_source,
        'd_type' => $type,
        'd_require' => $require,
        $name => $vartext,
        'd_default' => $default
    
    );
    return filter($name, $condition);
}

/**
 * 读取或写入Cookie信息
 *
 * @param string $name
 *            名称
 * @param string $value
 *            值
 * @param int $expire
 *            秒数
 * @param string $path
 *            路径，默认站点目录
 */
function cookie($name, $value = null, $expire = null, $path = null, $domain = null, $secure = null, $httponly = false)
{
    if (! is_null($value)) {
        $path = SITE_DIR . '/';
        if (is_string($value))
            $value = trim($value);
        
        $_COOKIE[$name] = $value; // 让cookie立即生效
        if (! is_null($expire)) {
            return setcookie($name, $value, time() + $expire, $path, $domain, $secure, $httponly);
        } else {
            return setcookie($name, $value, 0, $path, $domain, $secure, $httponly);
        }
    } else {
        if (isset($_COOKIE[$name])) {
            return escape_string($_COOKIE[$name]);
        } else {
            return null;
        }
    }
}

/**
 * 读取或写入session信息
 *
 * @param string $name
 *            支持点分多级获取
 * @param mixed $value
 *            设置值
 * @return string|NULL|unknown
 */
function session($name, $value = null)
{
    if (! isset($_SESSION)) {
        session_start(); // 自动启动会话
    }
    
    if (! is_null($value)) {
        if (isset($_SESSION[$name])) {
            if ($_SESSION[$name] != $value) {
                $_SESSION[$name] = $value;
            }
        } else {
            $_SESSION[$name] = $value;
        }
        return $value;
    } else {
        if (strpos($name, '.')) {
            if (isset($_SESSION[$name])) {
                return $_SESSION[$name];
            }
            $names = explode('.', $name);
            if (! isset($_SESSION[$names[0]])) {
                return null;
            }
            $var = $_SESSION[$names[0]];
            $len = count($names);
            for ($i = 1; $i < $len; $i ++) {
                if (is_array($var)) {
                    if (isset($var[$names[$i]])) {
                        $var = $var[$names[$i]];
                    } else {
                        return null;
                    }
                } elseif (is_object($var)) {
                    if (isset($var->{$names[$i]})) {
                        $var = $var->{$names[$i]};
                    } else {
                        return null;
                    }
                } else {
                    break;
                }
            }
            return $var;
        } else {
            if (isset($_SESSION[$name])) {
                return $_SESSION[$name];
            } else {
                return null;
            }
        }
    }
}

// 检查会话参数是否存在
function issetSession($name)
{
    if (! isset($_SESSION)) {
        session_start(); // 自动启动会话
    }
    return isset($_SESSION[$name]);
}

/**
 * 快速发送邮件函数
 *
 * @param array $config
 *            邮件服务器连接数组，需包含 smtp_server、smtp_username、smtp_password、smtp_port、smtp_port
 * @param string $to
 *            邮件接收人
 * @param string $subject
 *            邮件主题
 * @param string $body
 *            邮件正文
 */
function sendmail(array $config, $to, $subject, $body)
{
    $smtp = new Smtp($config['smtp_server'], $config['smtp_username'], $config['smtp_password'], $config['smtp_port'], $config['smtp_ssl']);
    if ($smtp->sendMail($to, $subject, $body)) {
        return true;
    } else {
        return $smtp->error();
    }
}

// 发送短信
function sendsms(array $config, $to, $content)
{
    if (! $to || ! $content) {
        return false;
    }
    
    if (! isset($config['sms_account']) || ! isset($config['sms_pwd']) || ! isset($config['sms_signid'])) {
        alert_back('短信发送参数配置有误');
    }
    
    $data['Account'] = $config['sms_account'];
    $data['Pwd'] = $config['sms_pwd'];
    $data['SignId'] = $config['sms_signid'];
    $data['Content'] = $content;
    $to = str_replace("\r\n", ",", $to); // 替换回车
    $to = str_replace("，", ",", $to); // 替换中文逗号分割符
    $data['Mobile'] = str_replace(" ", "", $to); // 替换空格
    
    $url = "http://api.feige.ee/SmsService/Send";
    if (! ! $res = get_url($url, $data)) {
        $result = json_decode($res);
        if (! ($result->Code === 0)) {
            error('短信发送失败，' . $result->Message);
        } else {
            return true;
        }
    }
}

// 查询短信余额
function get_sms_balance(array $config)
{
    $data['Account'] = $config['sms_account'];
    $data['Pwd'] = $config['sms_pwd'];
    $url = "http://api.feige.ee/Account/Balance";
    if (! ! $res = get_url($url, $data)) {
        $result = json_decode($res);
        if (! ($result->Code === 0)) {
            error('查询失败，' . $result->Message);
        } else {
            return $result->Balance;
        }
    }
}

// 返回404页面,文件中可使用{info}替换提示信息
function _404($string, $jump_url = null, $time = 2)
{
    http_response_code(404);
    $file_404 = ROOT_PATH . '/404.html';
    if (file_exists($file_404)) {
        echo parse_info_tpl($file_404, $string, $jump_url, $time);
        exit();
    } else {
        error($string, $jump_url, $time);
    }
}

// php对象转为数组
function toArray($obj){
    if($obj === null){
        return [];
    }else{
        return json_decode(json_encode($obj),true);
    }
}

//if标签比较符处理
function symbol($matches): string
{
    $flag = '';
    $symbol1 = ['&&','||'];
    foreach ($symbol1 as $items) {
        if (strpos($matches, $items) !== false) {
            $arr = explode($items, $matches);
            switch ($items) {
                case '&&':
                    $bool1 = compareSymbol1($arr[0]);
                    $bool2 = compareSymbol1($arr[1]);
                    $flag = $bool1 && $bool2 ? 'if' : 'else';
                    break;
                case '||':
                    $bool1 = compareSymbol1($arr[0]);
                    $bool2 = compareSymbol1($arr[1]);

                    $flag = $bool1 || $bool2 ? 'if' : 'else';
                    break;
            }
            break;
        }
    }
    if(!$flag){
        $compare = compareSymbol1($matches);
        if($compare === true){
            $flag = 'if';
        }else if($compare === false){
            $flag = 'else';
        }
    }
    return $flag;
}

function compareSymbol1($str){
    $bool = null;
    $symbol = ['>=','<=','!=','==','>','<'];
    foreach ($symbol as $items) {
        if (strpos($str, $items) !== false) {
            $arr = explode($items, $str);
            switch ($items) {
                case '>':
                    $res1 = compareSymbol2($arr[0]);
                    $res2 = compareSymbol2($arr[1]);
                    $bool = $res1 > $res2;
                    break;
                case '>=':
                    $res1 = compareSymbol2($arr[0]);
                    $res2 = compareSymbol2($arr[1]);
                    $bool = $res1 >= $res2;
                    break;
                case '!=':
                    $res1 = compareSymbol2($arr[0]);
                    $res2 = compareSymbol2($arr[1]);
                    $bool = $res1 != $res2;
                    break;
                case '==':
                    $res1 = compareSymbol2($arr[0]);
                    $res2 = compareSymbol2($arr[1]);
                    $bool = $res1 == $res2;
                    break;
                case '<=':
                    $res1 = compareSymbol2($arr[0]);
                    $res2 = compareSymbol2($arr[1]);
                    $bool = $res1 <= $res2;
                    break;
                case '<':
                    $res1 = compareSymbol2($arr[0]);
                    $res2 = compareSymbol2($arr[1]);
                    $bool = $res1 < $res2;
                    break;
            }
            break;
        }
    }
    if($bool === null){
        $res = compareSymbol2($str);
        if (trim($res)) {
            return true;
        } else {
            return false;
        }
    }
    return $bool;
}

function compareSymbol2($str){
    $res = null;
    $symbol2 = ['%'];
    foreach ($symbol2 as $items) {
        if (strpos($str, $items) !== false) {
            $arr = explode($items, $str);
            if ($items == '%') {
                $res = $arr[0] % $arr[1];
            }
            break;
        }
    }
    if($res === null) {
        $str = trim($str);
        $str = trim($str,"'");
        $res = (string)$str;
    }
    return $res;
}
