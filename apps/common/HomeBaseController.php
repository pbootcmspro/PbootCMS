<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2026年8月14日
 *  前台渲染控制基类
 */
namespace app\common;

use core\basic\Controller;

class HomeBaseController extends Controller
{

    /**
     * 渲染前绑定当前语言主题
     *
     * 语言可能在路由取到栏目/内容后才确定（见 IndexController::switchLg），
     * 因此主题必须延后到首次渲染前按最终语言绑定，且整个请求只绑定一次。
     */
    protected function beforeParser($file)
    {
        if ($this->getVar('theme') !== null) {
            return;
        }
        $this->setTheme(get_theme() . HomeController::getThemeSuffix());
    }
}
