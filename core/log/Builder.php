<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年10月24日
 *  日志操作接口类
 */
namespace core\log;

interface Builder
{

    // 用于获取单一实例
    public static function getInstance();

    /**
     * 日志写入
     *
     * @param string $content
     *            日志内容
     * @param string $level
     *            内容级别
     */
    public function write($content, $level = "info", $username = null);

    /**
     * 错误日志快速写入，error级别
     *
     * @param string $content
     *            日志内容
     */
    public function error($content);

    /**
     * 基础日志快速写入， info级别
     *
     * @param string $content
     *            日志内容
     */
    public function info($content);
}