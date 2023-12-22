<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2016年11月5日
 *  管理后台入口文件
 */

// 定义为入口文件
define('IS_INDEX', true);

// 入口文件地址绑定
define('URL_BIND', 'admin');

// PHP版本检测
if (version_compare(phpversion(),'7.0.0','<')) {
    header('Content-Type:text/html; charset=utf-8');
    exit('您服务器PHP的版本太低，程序要求PHP版本不小于7.0');
}

// 引用内核启动文件
require dirname(__FILE__) . '/core/start.php';
