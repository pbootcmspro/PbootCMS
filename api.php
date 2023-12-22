<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2016年11月5日
 *  API入口文件
 */

// 定义为入口文件
define('IS_INDEX', true);

// 入口文件地址绑定
define('URL_BIND', 'api');

// PHP版本检测
if (PHP_VERSION < '5.3') {
    echo json_encode(array(
        'code' => 0,
        'data' => 'The version of your server PHP is too low, and the program requires PHP version not less than 5.3.'
    ));
    exit();
}

// 引用内核启动文件
require dirname(__FILE__) . '/core/start.php';
