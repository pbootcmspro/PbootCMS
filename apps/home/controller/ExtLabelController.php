<?php
/**
 * @copyright (C)2020-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2020年3月8日
 *  个人扩展标签可编写到本类中，升级不会覆盖
 */
namespace app\home\controller;

use core\basic\Controller;

class ExtLabelController
{

    protected $content;

    /* 必备启动函数 */
    public function run($content)
    {
        // 接收数据
        $this->content = $content;
        
        // 执行个人自定义标签函数
        $this->test();
        
        // 返回数据
        return $this->content;
    }

    // 测试扩展单个标签
    private function test()
    {
        $this->content = str_replace('{pboot:userip}', get_user_ip(), $this->content);
    }
}