<?php
/**
 * @author xsh
 * @email hnxsh@foxmail.com
 * @date 2021年10月31日
 *
 */
namespace app\home\controller;

use core\basic\Controller;
use app\home\model\ParserModel;
use core\basic\Config;
use core\basic\Url;

class GetPageController extends Controller
{

    protected $parser;

    protected $model;

    protected $tplhtmldir;

    public function __construct()
    {
        $this->parser = new ParserController();
        $this->model = new ParserModel();
        $this->tplhtmldir = $this->config('tpl_html_dir') ? $this->config('tpl_html_dir') . '/' : '';
    }

    // 首页
    protected function getIndexPage()
    {
    }

    // 列表
    protected function getListPage($sort)
    {
		$pagelink = $this->parser->parserLink($sort->type, $sort->urlname, 'list', $sort->scode, $sort->filename, '', '');
    }

    // 详情页
    protected function getContentPage($data)
    {
            $link = $this->parser->parserLink($data->type, $data->urlname, 'content', $data->scode, $data->sortfilename, $data->id, $data->filename);
    }

    // 单页
    protected function getAboutPage($sort)
    {
            $link = $this->parser->parserLink($sort->type, $sort->urlname, 'list', $sort->scode, $sort->filename, '', '');
    }

    // 检查页面权限
}