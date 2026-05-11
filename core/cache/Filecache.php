<?php
/**
 * 文件缓存类
 * 
 * @author gzu-liyujiang@foxmail.com
 * @version 2026.0.0
 */
namespace core\cache;

use core\basic\Config;

class Filecache implements Builder
{
    protected static $instance;
    protected $cacheDir;

    // 禁止直接实例化
    private function __construct()
    {
        // 获取缓存目录配置，默认使用runtime/cache
        $this->cacheDir = Config::get('cache.dir') ?: RUN_PATH . '/cache';
        
        // 确保缓存目录存在
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    private function __clone()
    {
        error('禁止克隆实例！');
    }

    // 单一实例获取
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // 生成缓存文件路径
    protected function getCacheFile($key)
    {
        // 对key进行md5加密，避免特殊字符导致文件名问题
        $filename = md5($key) . '.cache';
        return $this->cacheDir . '/' . $filename;
    }

    // 设置值
    public function set($key, $value, $expire = 0)
    {
        $cacheFile = $this->getCacheFile($key);
        
        // 构建缓存数据结构
        $data = array(
            'value' => $value,
            'expire' => $expire > 0 ? time() + $expire : 0,
            'time' => time()
        );
        
        // 序列化并写入文件
        $content = serialize($data);
        
        // 使用临时文件+重命名的方式保证原子性
        $tempFile = $cacheFile . '.tmp';
        $result = file_put_contents($tempFile, $content, LOCK_EX);
        
        if ($result !== false) {
            rename($tempFile, $cacheFile);
            return true;
        }
        
        return false;
    }

    // 读取值
    public function get($key)
    {
        $cacheFile = $this->getCacheFile($key);
        
        // 文件不存在
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        // 读取并反序列化
        $content = file_get_contents($cacheFile);
        if ($content === false) {
            return false;
        }
        
        $data = @unserialize($content);
        
        // 数据格式错误
        if (!is_array($data) || !isset($data['value'])) {
            // 删除损坏的缓存文件
            @unlink($cacheFile);
            return false;
        }
        
        // 检查是否过期
        if ($data['expire'] > 0 && time() > $data['expire']) {
            // 已过期，删除缓存文件
            @unlink($cacheFile);
            return false;
        }
        
        return $data['value'];
    }

    // 删除
    public function delete($key)
    {
        $cacheFile = $this->getCacheFile($key);
        
        if (file_exists($cacheFile)) {
            return @unlink($cacheFile);
        }
        
        return true;
    }

    // 清理所有
    public function flush()
    {
        $files = glob($this->cacheDir . '/*.cache');
        
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        
        return true;
    }
}