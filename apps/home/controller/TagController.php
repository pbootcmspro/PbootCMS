<?php
/**
 * @copyright (C)2020-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2020年3月8日
 *  标签控制器     
 */
namespace app\home\controller;

use core\basic\Controller;
use core\basic\Url;

class TagController extends Controller
{

    protected $parser;

    protected $htmldir;

    public function __construct()
    {
        $this->parser = new ParserController();
        $this->htmldir = $this->config('tpl_html_dir') ? $this->config('tpl_html_dir') . '/' : '';
    }

    // 内容搜索
    public function index()
    {
        // 在非兼容模式接受地址第二参数值
        if (defined('RVAR')) {
            $_GET['tag'] = RVAR;
        }
        
        if (! get('tag')) {
            _404('您访问的页面不存在，请核对后重试！');
        }
        
        $tagstpl = request('tagstpl');
        if (! preg_match('/^[\w]+\.html$/', $tagstpl)) {
            $tagstpl = 'tags.html';
        }
        $content = parent::parser($this->htmldir . $tagstpl); // 框架标签解析
        $content = $this->parser->parserBefore($content); // CMS公共标签前置解析
        $content = $this->parser->parserPositionLabel($content, 0, '相关内容', Url::home('tag/' . get('tag'))); // CMS当前位置标签解析
        $content = $this->parser->parserSpecialPageSortLabel($content, - 2, '相关内容', Url::home('tag/' . get('tag'))); // 解析分类标签
        $content = $this->parser->parserAfter($content); // CMS公共标签后置解析
        $this->cache($content, true);
    }
}