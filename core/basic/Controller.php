<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2016年11月6日
 *  应用控制基类  
 */
namespace core\basic;

use core\view\View;
use core\view\Paging;

class Controller
{

    // 显示模板
    final protected function display($file)
    {
        $view = View::getInstance();
        $content = $view->parser($file);
        $content = $this->runtime($content);
        echo $this->gzip($content);
        exit();
    }

    // 解析模板
    final protected function parser($file)
    {
        $view = View::getInstance();
        return $view->parser($file);
    }

    // 缓存页面内容,默认直接显示内容，可传递第二参数false返回内容
    final protected function cache($content, $display = true)
    {
        $view = View::getInstance();
        if (Config::get('tpl_html_cache')) {
            $content = str_replace('{pboot:runtime}', 'Cached at ' . date('Y-m-d H:i:s'), $content);
        } else {
            $content = $this->runtime($content);
        }
        $view->cache($content); // 压缩前缓存
        $content = $this->gzip($content);
        if ($display) {
            echo $content;
            exit();
        } else {
            return $content;
        }
    }

    // 设置视图主题
    final protected function setTheme($themeName)
    {
        $view = View::getInstance();
        $view->assign('theme', $themeName);
    }

    // 变量注入接口
    final protected function assign($var, $value)
    {
        $view = View::getInstance();
        $view->assign($var, $value);
    }

    // 变量获取接口
    final protected function getVar($var)
    {
        $view = View::getInstance();
        return $view->getVar($var);
    }

    // 手动生成分页信息,返回限制语句
    final protected function page($tatal, $morePageStr = false)
    {
        $page = Paging::getInstance();
        return $page->limit($tatal, $morePageStr);
    }

    // 获取配置信息
    final protected function config($item = null, $array = false)
    {
        return Config::get($item, $array);
    }

    // 缓存配置信息
    final protected function setConfig($itemName, array $data)
    {
        return Config::set($itemName, $data);
    }

    // 写入日志信息
    final protected function log($content, $level = "info")
    {
        Log::write($content, $level);
    }

    // 解析运行时间标签
    private function runtime($content)
    {
        return str_replace('{pboot:runtime}', 'Processed in ' . round(microtime(true) - START_TIME, 6) . ' second(s).', $content);
    }

    // 压缩内容
    private function gzip($content)
    {
        if (Config::get('gzip') && ! headers_sent() && extension_loaded("zlib") && strstr($_SERVER["HTTP_ACCEPT_ENCODING"], "gzip")) {
            $content = gzencode($content, 6);
            header("Content-Encoding: gzip");
            header("Vary: Accept-Encoding");
            header("Content-Length: " . strlen($content));
        }
        return $content;
    }
}

