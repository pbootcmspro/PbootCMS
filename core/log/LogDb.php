<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年11月16日
 *  日志数据库驱动
 */
namespace core\log;

use core\basic\Model;

class LogDb implements Builder
{

    protected static $logDb;

    protected static $model;

    private function __construct()
    {}

    // 用于获取单一实例
    public static function getInstance()
    {
        if (! self::$logDb) {
            self::$logDb = new self();
            self::$model = new Model();
        }
        return self::$logDb;
    }

    // 写入日志
    public function write($content, $level = "info", $username = null)
    {
        $username = $username ?: session('username');
        $data = array(
            'level' => $level,
            'event' => escape_string($content),
            'user_ip' => ip2long(get_user_ip()),
            'user_os' => get_user_os(),
            'user_bs' => get_user_bs(),
            'create_user' => $username,
            'create_time' => get_datetime()
        );
        return self::$model->table('ay_syslog')->insert($data);
    }

    // 写入错误日志
    public function error($content)
    {
        return $this->write($content, 'error');
    }

    // 写入信息日志
    public function info($content)
    {
        return $this->write($content, 'info');
    }
}