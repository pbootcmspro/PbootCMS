<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年11月1日
 *  数据库快速操作类
 */
namespace core\basic;

use core\basic\Model;

class Db
{

    // 对象方式动态调用数据库操作方法
    public function __call($methed, $args)
    {
        $model = new Model();
        $result = call_user_func_array(array(
            $model,
            $methed
        ), $args);
        return $result;
    }

    // 静态方式动态调用数据库操作方法
    public static function __callstatic($methed, $args)
    {
        $model = new Model();
        $result = call_user_func_array(array(
            $model,
            $methed
        ), $args);
        return $result;
    }
}