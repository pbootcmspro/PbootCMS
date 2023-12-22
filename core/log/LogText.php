<?php

/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年10月24日 
 *  日志记录文本驱动
 */
namespace core\log;

class LogText implements Builder
{

    protected static $logText;

    private function __construct()
    {}

    // 用于获取单一实例
    public static function getInstance()
    {
        if (! self::$logText) {
            self::$logText = new self();
        }
        return self::$logText;
    }

    // 写入文本日志
    public function write($content, $level = "info")
    {
        $logfile = ROOT_PATH . '/log/' . date('Ymd') . '.log';
        check_file($logfile, true);
        $username = session('username') ?: 'system';
        $string = $level . ' ' . $content . ' ' . get_user_ip() . ' ' . get_user_os() . ' ' . get_user_bs() . ' ' . $username . ' ' . get_datetime() . PHP_EOL;
        return file_put_contents($logfile, $string, FILE_APPEND);
    }

    // 写入文本错误日志
    public function error($content)
    {
        return $this->write($content, 'error');
    }

    // 写入文本信息日志
    public function info($content)
    {
        return $this->write($content, 'info');
    }
}