<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年10月24日 
 *  Memcache缓存类
 */
namespace core\cache;

use core\basic\Config;

class Memcache implements Builder
{

    protected static $memcache;

    protected $conn;

    // 禁止直接实例化
    private function __construct()
    {}

    private function __clone()
    {
        error('禁止克隆实例！');
    }

    // 单一实例获取
    public static function getInstance()
    {
        if (! self::$memcache) {
            self::$memcache = new self();
        }
        return self::$memcache;
    }

    // 初始化连接
    protected function conn()
    {
        if (! $this->conn) {
            $this->conn = new Memcache();
            $server = Config::get('cache.server');
            if (is_multi_array($server)) {
                foreach ($server as $value) {
                    $this->conn->addserver($value['host'], $value['port']);
                }
            } else {
                $this->conn->addserver($server['host'], $server['port']);
            }
        }
        return $this->conn;
    }

    // 设置值
    public function set($key, $value)
    {
        $memcache = $this->conn();
        return $memcache->set($key, $value);
    }

    // 读取值
    public function get($key)
    {
        $memcache = $this->conn();
        return $memcache->get($key);
    }

    // 删除
    public function delete($key)
    {
        $memcache = $this->conn();
        return $memcache->delete($key);
    }

    // 清理所有
    public function flush()
    {
        $memcache = $this->conn();
        return $memcache->flush();
    }

    // 版本信息
    public function status()
    {
        $memcache = $this->conn();
        return $memcache->getExtendedStats();
    }

    // 关闭连接
    public function __destruct()
    {
        $memcache = $this->conn();
        $memcache->close();
    }
}