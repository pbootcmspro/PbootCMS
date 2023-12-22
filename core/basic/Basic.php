<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年11月4日
 *  系统基础类
 */
namespace core\basic;

class Basic
{

    protected static $models = array();

    // 实现类文件自动加载
    public static function autoLoad($className)
    {
        //oss类跳过
        if( strstr($className,'OSS')){
            return;
        }
        if (substr($className, 0, 4) == 'core') { // 框架类文件命名空间转换
            $class_file = CORE_PATH . '/' . str_replace('\\', '/', substr($className, 5)) . '.php';
        } elseif (substr($className, 0, 3) == 'app') { // 应用类文件命名空间转换
            $class_file = APP_PATH . '/' . str_replace('\\', '/', substr($className, 4)) . '.php';
        } elseif (strpos($className, '\\')) { // 如果带有命名空间，使用全路径载入
            $class_file = ROOT_PATH . '/' . str_replace('\\', '/', $className) . '.php';
        } else { // 默认载入内核基础目录下文件
            $class_file = CORE_PATH . '/basic/' . $className . '.php';
        }
        if (! file_exists($class_file)) {
            error('自动加载类文件时发生错误，类名【' . $className . '】，文件：【' . $class_file . '】');
        }
        require $class_file;
    }

    // 自定义错误函数
    public static function errorHandler($errno, $errstr, $errfile, $errline)
    {
        if (! (error_reporting() & $errno)) {
            // 如果这个错误类型没有包含在error_reporting里，如加了@的错误则不报告
            return;
        }
        switch ($errno) {
            case E_ERROR:
                $err_level = 'ERROR';
                break;
            case E_WARNING:
                $err_level = 'WARNING';
                break;
            case E_PARSE:
                $err_level = 'PARSE';
                break;
            case E_NOTICE:
                $err_level = 'NOTICE';
                break;
            case E_RECOVERABLE_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                $err_level = 'FATAL ERROR';
                break;
            default:
                $err_level = 'UNKNOW';
                break;
        }
        $info = "<h3>$err_level:</h3>\n";
        $info .= "<p><b>Code:</b> $errno;</p>\n";
        $info .= "<p><b>Desc:</b> $errstr;</p>\n";
        $info .= "<p><b>File:</b> $errfile;</p>\n";
        $info .= "<p><b>Line:</b> $errline;</p>\n";
        
        if ($err_level == 'WARNING' || $err_level == 'NOTICE') {
            echo $info;
        } else {
            error($info);
        }
    }

    // 异常捕获
    public static function exceptionHandler($exception)
    {
        error("程序运行异常: " . $exception->getMessage() . "，位置：" . $exception->getFile() . '，第' . $exception->getLine() . '行。');
    }

    // 致命错误捕获
    public static function shutdownFunction()
    {
        $error = error_get_last();
        define('E_FATAL', E_ERROR | E_RECOVERABLE_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR);
        if ($error && ($error["type"] === ($error["type"] & E_FATAL))) {
            $errno = $error["type"];
            $errstr = $error["message"];
            $errfile = $error["file"];
            $errline = $error["line"];
            self::errorHandler($errno, $errstr, $errfile, $errline);
        }
    }

    // 会话处理程序设置
    public static function setSessionHandler()
    {
        if (ini_get('session.auto_start')) {
            return;
        }
        
        // 配置会话安全参数
        session_name('PbootSystem');
        ini_set("session.use_trans_sid", 0);
        ini_set("session.use_cookies", 1);
        ini_set("session.use_only_cookies", 1);
        session_set_cookie_params(0, SITE_DIR . '/', null, null, false);
        
        switch (Config::get('session.handler')) {
            case 'memcache':
                if (! extension_loaded('memcache'))
                    error('PHP运行环境未安装memcache.dll扩展！');
                ini_set("session.save_handler", "memcache");
                ini_set("session.save_path", Config::get('seesion.path'));
                break;
            default:
                if (Config::get('session_in_sitepath')) {
                    $save_path = RUN_PATH . '/session/';
                    if (! check_dir($save_path, true))
                        error('设置的会话目录创建失败！' . $save_path);
                    ini_set("session.save_handler", "files");
                    $depth = 1;
                    ini_set("session.save_path", $depth . ';' . $save_path);
                    if (! is_dir($save_path . '/0/0') || ! is_dir($save_path . '/v/v')) {
                        create_session_dir($save_path, $depth);
                    }
                }
                break;
        }
    }

    // 实例化模型
    public static function createModel($name = null, $new = false)
    {
        // 自动同名模型控制器
        if (! $name)
            $name = C;
        
        // 获取类名
        if (strpos($name, '.') !== false) {
            $path = explode('.', $name);
            $class_name = '\\app\\' . $path[0] . '\\model';
            $len = count($path);
            for ($i = 1; $i < $len - 1; $i ++) {
                $class_name .= '\\' . $path[$i];
            }
            $class_name .= '\\' . ucfirst($path[$i]) . 'Model';
        } else {
            $class_name = '\\app\\' . M . '\\model\\' . ucfirst($name) . 'Model';
        }
        
        // 根据需要实例化
        $key = md5($class_name);
        if (! isset(self::$models[$key]) || $new) {
            self::$models[$key] = new $class_name();
        }
        
        return self::$models[$key];
    }

    // 创建数据接口
    public static function createApi($args = null)
    {
        // 直接调用方式
        if (! is_array($args)) {
            $args = func_get_args();
        }
        
        // 分离参数
        $name = $args[0];
        unset($args[0]);
        $param = $args;
        
        // 如果只是传递了方法，则自动完善模块及模型控制器
        if (strpos($name, '.') === false) {
            $name = M . '.' . C . '.' . $name;
        }
        
        $path = explode('.', $name); // 第一个为模块 $path[0]，倒数第二个为模型$path[$i]，倒数第一个为方法$path[$i+1]
        $class_name = '\\app\\' . $path[0] . '\\model';
        $len = count($path);
        for ($i = 1; $i < $len - 2; $i ++) {
            $class_name .= '\\' . $path[$i];
        }
        $class_name .= '\\' . ucfirst($path[$i]) . 'Model';
        $key = md5($class_name);
        
        if (isset(self::$models[$key])) {
            $model = self::$models[$key];
        } else {
            $model = new $class_name();
            self::$models[$key] = $model;
        }
        
        // 调取接口方法
        if (is_array($param)) {
            $rs = call_user_func_array(array(
                $model,
                $path[$i + 1]
            ), $param);
        }
        
        // 返回结果,如果不是json数据，则转换
        if (! ! $return = json_decode($rs)) {
            return $rs;
        } else {
            return json_encode($rs);
        }
    }
}


