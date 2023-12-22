<?php
/**
 * @copyright (C)2020-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2020年3月8日
 *  搜索控制器
 */
namespace app\home\controller;

use core\basic\Controller;
use core\basic\Url;

class SearchController extends Controller
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
        $searchtpl = request('searchtpl');
        if (! preg_match('/^[\w]+\.html$/', $searchtpl)) {
            $searchtpl = 'search.html';
        }
        
        $content = parent::parser($this->htmldir . $searchtpl); // 框架标签解析
        $content = $this->parser->parserBefore($content); // CMS公共标签前置解析
        $pagetitle = get('keyword', 'var') ? get('keyword', 'var') . '-' : '';
        $content = str_replace('{pboot:pagetitle}', $this->config('search_title') ?: $pagetitle . '搜索结果-{pboot:sitetitle}-{pboot:sitesubtitle}', $content);
        $content = $this->parser->parserPositionLabel($content, 0, '搜索', Url::home('search')); // CMS当前位置标签解析
        $content = $this->parser->parserSpecialPageSortLabel($content, - 1, '搜索结果', Url::home('search')); // 解析分类标签
        $content = $this->parser->parserSearchLabel($content); // 搜索结果标签
        $content = $this->parser->parserAfter($content); // CMS公共标签后置解析
        echo $content; // 搜索页面不缓存
        exit();
    }
}