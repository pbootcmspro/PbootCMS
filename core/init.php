<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年2月7日
 *  系统环境初始化
 */
use core\basic\Config;
use core\basic\Basic;
use core\basic\Check;

// 启动程序时间
define('START_TIME', microtime(true));

// 设置字符集编码、IE文档模式
header('Content-Type:text/html; charset=utf-8');
header('X-UA-Compatible:IE=edge,chrome=1');
header('X-Powered-By:PbootCMS');

// 设置中国时区
date_default_timezone_set('Asia/Shanghai');

// 定义站点虚拟目录（自适应获取多级目录），此处保证DOCUMENT_ROOT、 __DIR__路径的一致性
if (isset($_SERVER['PATH_INFO'])) {
    $_SERVER['SCRIPT_NAME'] = preg_replace('{' . $_SERVER['PATH_INFO'] . '$}', '', $_SERVER['SCRIPT_NAME']); // 替换掉PATH_INFO,避免部分服务商路径不对
}
$script_path = explode('/', $_SERVER['SCRIPT_NAME']); // 当前执行文件路径
$file_path = str_replace('\\', '/', dirname(__DIR__)); // 系统部署路径
if (count($script_path) > 2) { // 根目录下"/index.php"长度为2
    if (! ! $path_pos = strripos($file_path, '/' . $script_path[1])) {
        define('SITE_DIR', substr($file_path, $path_pos));
        $_SERVER['SCRIPT_NAME'] = preg_replace('{^' . SITE_DIR . '}i', SITE_DIR, $_SERVER['SCRIPT_NAME']); // 规避大小写URL问题
    } else {
        define('SITE_DIR', '');
    }
} else {
    define('SITE_DIR', '');
}

// 定义入口文件地址
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
if ($script_dir == '\\' || $script_dir == '/') {
    define('SITE_INDEX_DIR', '');
} else {
    define('SITE_INDEX_DIR', dirname($_SERVER['SCRIPT_NAME']));
}

// 定义网站部署根路径
define('ROOT_PATH', $file_path);

// 定义站点物理路径
define('DOC_PATH', preg_replace('{' . SITE_DIR . '$}i', '', ROOT_PATH));
$_SERVER['DOCUMENT_ROOT'] = DOC_PATH; // 统一该环境变量值
                                      
// 定义内核文件目录
define('CORE_DIR', SITE_DIR . '/' . basename(__DIR__));

// 定义内核文件物理路径
define('CORE_PATH', DOC_PATH . CORE_DIR);

// 定义应用存放物理路径
define('APP_PATH', ROOT_PATH . '/apps');

// 定义应用文件目录
define('APP_DIR', str_replace(DOC_PATH, '', APP_PATH));

// 定义应用运行文件路径
defined('RUN_PATH') ?: define('RUN_PATH', ROOT_PATH . '/runtime');

// 定义公共配置文件路径
defined('CONF_PATH') ?: define('CONF_PATH', ROOT_PATH . '/config');

// 定义静态文件目录
defined('STATIC_DIR') ?: define('STATIC_DIR', SITE_DIR . '/static');

// 载入基础函数库
require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/function/helper.php';
require CORE_PATH . '/function/file.php';

// 载入基础类文件
require CORE_PATH . '/basic/Basic.php';

// 注册自动加载函数
spl_autoload_register('core\basic\Basic::autoLoad', true, true);

// 设置错误处理函数
set_error_handler('core\basic\Basic::errorHandler');

// 设置异常捕获函数
set_exception_handler('core\basic\Basic::exceptionHandler');

// 注册异常中止函数
register_shutdown_function('core\basic\Basic::shutdownFunction');

// 调试模式设置错误报告级别并进行环境检查
if (Config::get('debug')) {
    ini_set('display_errors', 1); // 开启显示错误
    error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);
} else {
    error_reporting(E_ERROR);
}

// 定义版本常量
define('CORE_VERSION', Config::get('core_version'));
define('APP_VERSION', Config::get('app_version'));
define('RELEASE_TIME', Config::get('release_time'));

// 环境检查
Check::checkPHP();//检查php版本
Check::checkApp(); // 检查APP配置
Check::checkBasicDir(); // 检查基础目录
Check::checkSession();//检查session文件夹
Basic::setSessionHandler();// 会话处理程序选择

