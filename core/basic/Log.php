<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年10月24日 
 *  日志统一调用类
 */
namespace core\basic;

use core\log\LogText;
use core\log\LogDb;

class Log
{

    // 获取缓存实例
    protected static function getLogInstance()
    {
        switch (Config::get('log_record_type')) {
            case 'text':
                $instance = LogText::getInstance();
                break;
            case 'db':
                $instance = LogDb::getInstance();
                break;
            default:
                $instance = LogText::getInstance();
        }
        return $instance;
    }

    /**
     * 日志写入
     *
     * @param string $content
     *            日志内容
     * @param string $level
     *            内容级别
     */
    public static function write($content, $level = "info", $username = null)
    {
        $log = self::getLogInstance();
        $log->write($content, $level, $username);
    }

    /**
     * 错误日志快速写入，error级别
     *
     * @param string $content
     *            日志内容
     */
    public static function error($content)
    {
        $log = self::getLogInstance();
        $log->error($content);
    }

    /**
     * 基础日志快速写入， info级别
     *
     * @param string $content
     *            日志内容
     */
    public static function info($content)
    {
        $log = self::getLogInstance();
        $log->info($content);
    }
}