<?php

/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年12月24日
 *  二维码生成
 */

// 绘制二维码图片
function draw_qcode($string)
{
    require dirname(__FILE__) . '/extend/qrcode/phpqrcode.php'; // 引入类文件
    QRcode::png($string, false, 'M', 6, 1); // 生成二维码图片
}

if (isset($_GET['string']) && $string = $_GET['string']) {
    draw_qcode($string);
} else {
    die('地址必须传入string参数！');
}