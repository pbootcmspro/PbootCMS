<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2026年8月6日
 *  蜘蛛访问专用文本日志驱动（与系统日志数据库隔离）
 */
namespace core\log;

use core\basic\Config;

class LogSpider
{

    protected static $logSpider;

    private function __construct()
    {}

    // 用于获取单一实例
    public static function getInstance()
    {
        if (! self::$logSpider) {
            self::$logSpider = new self();
        }
        return self::$logSpider;
    }

    /**
     * 写入蜘蛛访问日志
     *
     * @param string $spider 爬虫名称
     * @param string $url 访问 URL
     * @return bool|int
     */
    public function write($spider, $url)
    {
        if (Config::get('spiderlog') === '0') {
            return false;
        }
        $dir = ROOT_PATH . '/log/spider';
        // 仅确保目录存在；文件由 fopen('ab') 原子创建，避免 check_file/create_file 的 w 截断竞态
        check_dir($dir, true);
        $logfile = $dir . '/' . date('Ymd') . '.log';
        $line = $this->formatLine($spider, $url);
        return $this->appendWithLock($logfile, $line);
    }

    // 格式化单行日志（制表符分隔，便于追踪）
    private function formatLine($spider, $url)
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        return get_datetime() . "\t" . $this->sanitizeField($spider) . "\t" . $this->sanitizeField($url) . "\t"
            . get_user_ip() . "\t" . get_user_os() . "\t" . get_user_bs() . "\t" . $this->sanitizeField($ua) . PHP_EOL;
    }

    // 去除换行/制表符，避免破坏日志格式
    private function sanitizeField($value)
    {
        return str_replace(array("\r", "\n", "\t"), ' ', trim((string) $value));
    }

    // 带文件锁追加写入；ab 模式在文件不存在时原子创建且不截断
    private function appendWithLock($path, $content)
    {
        $fp = @fopen($path, 'ab');
        if (! $fp) {
            return false;
        }
        if (! flock($fp, LOCK_EX)) {
            fclose($fp);
            return false;
        }
        $result = fwrite($fp, $content);
        flock($fp, LOCK_UN);
        fclose($fp);
        return $result !== false;
    }
}
