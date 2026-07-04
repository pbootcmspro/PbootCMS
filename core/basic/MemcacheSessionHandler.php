<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * Memcache 会话处理器（兼容 PHP 8.x 内置 memcache session 写入异常）
 */
namespace core\basic;

class MemcacheSessionHandler implements \SessionHandlerInterface
{

    private $memcache;

    private $servers;

    public function __construct($path)
    {
        $this->servers = self::parseServers($path);
        if (! $this->servers) {
            error('memcache 会话 session.path 格式错误，示例：tcp://127.0.0.1:11211');
        }
        self::assertReachable($this->servers);
    }

    public function open($savePath, $sessionName)
    {
        $this->memcache = new \Memcache();
        foreach ($this->servers as $server) {
            $this->memcache->addServer($server['host'], $server['port']);
        }
        if (! self::ping($this->memcache)) {
            error('Memcached 服务连接失败，请确认服务已启动且 session.path 配置正确（当前：' . self::formatServerList($this->servers) . '）');
        }
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        $data = $this->memcache->get($id);
        return ($data === false) ? '' : $data;
    }

    public function write($id, $data)
    {
        $lifetime = (int) ini_get('session.gc_maxlifetime');
        if ($lifetime <= 0) {
            $lifetime = 1440;
        }
        return $this->memcache->set($id, $data, 0, $lifetime);
    }

    public function destroy($id)
    {
        return $this->memcache->delete($id);
    }

    public function gc($maxlifetime)
    {
        return 0;
    }

    // 启动前检测 Memcached 是否可达
    protected static function assertReachable(array $servers)
    {
        $memcache = new \Memcache();
        foreach ($servers as $server) {
            $memcache->addServer($server['host'], $server['port']);
        }
        if (! self::ping($memcache)) {
            error('Memcached 服务连接失败，请确认服务已启动且 session.path 配置正确（当前：' . self::formatServerList($servers) . '）');
        }
    }

    // 探测 Memcached 连通性
    protected static function ping(\Memcache $memcache)
    {
        $stats = $memcache->getStats();
        if (is_array($stats)) {
            foreach ($stats as $stat) {
                if (is_array($stat) && isset($stat['pid'])) {
                    return true;
                }
            }
        }
        $key = '__pboot_sess_ping_' . mt_rand();
        if ($memcache->set($key, '1', 0, 5)) {
            $memcache->delete($key);
            return true;
        }
        return false;
    }

    protected static function formatServerList(array $servers)
    {
        $addrs = array();
        foreach ($servers as $server) {
            $addrs[] = $server['host'] . ':' . $server['port'];
        }
        return implode('; ', $addrs);
    }

    // 解析 memcache 会话服务器地址，支持 tcp://host:port 与 host:port，多节点以 ; 分隔
    protected static function parseServers($path)
    {
        $servers = array();
        $path = trim($path);
        $path = rtrim($path, ';');
        foreach (explode(';', $path) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            if (preg_match('#^tcp://([^:]+):(\d+)$#i', $entry, $match)) {
                $servers[] = array(
                    'host' => $match[1],
                    'port' => (int) $match[2]
                );
            } elseif (preg_match('#^([^:]+):(\d+)$#', $entry, $match)) {
                $servers[] = array(
                    'host' => $match[1],
                    'port' => (int) $match[2]
                );
            }
        }
        return $servers;
    }
}
