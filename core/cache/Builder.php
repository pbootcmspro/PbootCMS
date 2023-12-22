<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年10月24日 
 *  缓存类接口
 */
namespace core\cache;

interface Builder
{

    // 用于获取单一实例
    public static function getInstance();

    // 写入缓存
    public function set($key, $value);

    // 读取缓存
    public function get($key);

    // 删除缓存
    public function delete($key);

    // 清理缓存
    public function flush();
}