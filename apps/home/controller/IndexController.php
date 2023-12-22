<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年2月14日
 *  首页控制器
 */

namespace app\home\controller;

use app\home\model\ParserModel;
use core\basic\Config;
use core\basic\Controller;
use core\basic\Url;

class IndexController extends Controller
{

    protected $parser;

    protected $model;

    protected $htmldir;

    public function __construct()
    {
        $this->parser = new ParserController();
        $this->model = new ParserModel();
        $this->htmldir = $this->config('tpl_html_dir') ? $this->config('tpl_html_dir') . '/' : '';
    }

    // 空拦截器, 实现文章路由转发
    public function _empty()
    {
        // 地址类型
        $url_rule_type = $this->config('url_rule_type') ?: 3;

        if (P) { // 采用pathinfo模式及p参数伪静态模式
            if ($url_rule_type == 2) { // 禁止伪静态时带index.php 和动态地址访问
                if (stripos(URL, 'index.php') !== false) {
                    _404('您访问的内容不存在，请核对后重试！');
                }
                if (stripos(URL, '?') !== false && stripos(URL, '/?tag=') == false && stripos(URL, '/?page=') == false && stripos(URL, '/?ext_') == false) {
                    _404('您访问的内容不存在，请核对后重试！');
                }
            }
            $path = P;
        } elseif ($url_rule_type == 3 && isset($_SERVER["QUERY_STRING"]) && $qs = $_SERVER["QUERY_STRING"]) { // 采用简短传参模式
            parse_str($qs, $output);
            unset($output['page']); // 去除分页
            if ($output && !current($output)) { // 第一个路径参数不能有值，否则非标准路径参数
                $path = key($output); // 第一个参数为路径信息，注意PHP数组会自动将key点符号转换下划线
            } elseif (get('tag')) { // 对于兼容模式tag需要自动跳转tag独立页面
                $tag = new TagController();
                $tag->index();
            } elseif (get('keyword')) { // 兼容模式搜索处理
                $search = new SearchController();
                $search->index();
            }
        }

        // 判断是否存在后缀
        $url_rule_suffix = substr($this->config('url_rule_suffix'), 1);
        $suffix = false;
        $slash = false;
        if (preg_match('/(.*)(_|\.)' . $url_rule_suffix . '$/', $path, $matchs)) {
            $path = $matchs[1];
            $suffix = true;
        } elseif (preg_match('/^[\w\-\/]+\/$/', $path)) {
            $slash = true;
            $path = trim($path, '/');
        }
        $path = escape_string($path);
        $path_arr = $path ? explode('/', $path) : array();

        // 开始路由
        if (isset($path_arr) && count($path_arr) > 0 && preg_match('/^[\w\-\/]+$/', $path)) {
            switch (strtolower($path_arr[0])) {
                case 'search':
                case 'keyword':
                    $search = new SearchController();
                    $search->index();
                    break;
                case 'message':
                    $msg = new MessageController();
                    $msg->index();
                    break;
                case 'form':
                    $_GET['fcode'] = $path_arr[1];
                    $form = new FormController();
                    $form->index();
                    break;
                case 'sitemap':
                case 'sitemap_xml':
                    $sitemap = new SitemapController();
                    $sitemap->index();
                    break;
                case 'sitemap_txt':
                    $sitemap = new SitemapController();
                    $sitemap->linkTxt();
                    break;
                case 'tag':
                    $tag = new TagController();
                    $tag->index();
                    break;
                case 'member':
                    $member = new MemberController();
                    $member->{$path_arr[1]}();
                    break;
                case 'comment':
                    $comment = new CommentController();
                    $comment->{$path_arr[1]}();
                    break;
                case 'spider':
                    $spider = new SpiderController();
                    $spider->index();
                    break;
                default:

                    $url_break_char = $this->config('url_break_char') ?: '_';
                    $url_rule_content_path = $this->config('url_rule_content_path') ? true : false;
                    $err = '';
                    $iscontent = false;

                    // 开始进行地址匹配
                    if (!$suffix && !!$sort = $this->model->getSort($path)) {
                        // 栏目名称，即栏目全路径匹配
                    } elseif (preg_match('/^([a-zA-Z0-9\-\/]+)' . $url_break_char . '([0-9]+)$/i', $path, $matchs) && !!$sort = $this->model->getSort($matchs[1])) {
                        // 栏目名称_分页，栏目分页的情况
                        define('CMS_PAGE_CUSTOM', true); // 设置走自定义CMS分页
                        $_GET['page'] = $matchs[2]; // 设置分页参数
                    } else {

                        if ($url_rule_content_path && !!$data = $this->model->getContent($path)) {
                            $iscontent = true; // 短路径情况
                        } elseif (!$url_rule_content_path) {
                            // 详情页至少是2级，对地址进行栏目和内容路径拆分，访问详情页
                            $part1 = dirname($path);
                            $part2 = basename($path);
                            while ($part1 != '.') {
                                if ((!!$sort = $this->model->getSort($part1)) && !!$data = $this->model->getContent($part2)) {
                                    // 栏目名称/内容名称或ID
                                    $iscontent = true;
                                    $scode = $sort->scode;
                                    break;
                                } elseif (preg_match('/^([a-zA-Z0-9\-\/]+)' . $url_break_char . '([0-9]+)$/i', $part1, $matchs) && !!$model = $this->model->checkModelUrlname($matchs[1])) {
                                    // 模型名称_栏目ID/内容名称或ID
                                    $data = $this->model->getContent($part2);
                                    $iscontent = true;
                                    $scode = $matchs[2];
                                    // 限制串模型多路径
                                    if (!!$data->urlname && $matchs[1] != $data->urlname) {
                                        $err = true;
                                    }
                                    break;
                                } else {
                                    $part2 = basename($part1) . '/' . $part2;
                                    $part1 = dirname($part1);
                                }
                            }

                            // 限制串栏目多路径
                            if ($scode != $data->scode) {
                                $err = true;
                            }

                            // 限制串内容ID及名称多路径
                            if (!!$data->filename && $part2 != $data->filename) {
                                $err = true;
                            }
                        }

                        // 执行未配置栏目名称但是配置了模型路径的情况路径匹配
                        if (!$iscontent) {
                            preg_match('/^([a-zA-Z0-9\-\/]+)(' . $url_break_char . '([0-9]+))?' . $url_break_char . '([0-9]+)$/i', $path, $matchs);
                            if ($matchs[2] && $model = $this->model->checkModelUrlname($matchs[1])) {
                                // 模型名称_栏目ID_分页
                                define('CMS_PAGE_CUSTOM', false);
                                $sort = $this->model->getSort($matchs[3]);
                                $_GET['page'] = $matchs[4]; // 分页
                            } elseif (!!$model = $this->model->checkModelUrlname($matchs[1])) {
                                // 模型名称_栏目ID
                                $sort = $this->model->getSort($matchs[4]);
                            }

                            // 限制串模型和栏目名称多路径，当栏目名称不为空时不允许使用模型路径
                            if ($sort->filename != '') {
                                $err = true;
                            }

                            // 限制串模型多路径
                            if (!!$sort->urlname && $matchs[1] != $sort->urlname) {
                                $err = true;
                            }
                        }
                    }

                    if ($iscontent) {
                        define('CMS_PAGE', false); // 使用普通分页处理模型
                        if (!!$data && $suffix && !$err) {
                            $this->getContentPage($data);
                        } else {
                            _404('您访问的内容不存在，请核对后重试！');
                        }
                    } else {
                        define('CMS_PAGE', true); // 使用cms分页处理模型
                        if (!!$sort && !$suffix && !$err) {
                            if ($sort->type == 1) {
                                $this->getAboutPage($sort);
                            } else {

                                $this->getListPage($sort);
                            }
                        } else {
                            _404('您访问的页面不存在，请核对后重试！');
                        }
                    }
            }
        } else {
            if (SITE_DIR == '') {
                //一级目录
                $this->urlJump($url_rule_type, false);
            } else {
                //二级目录
                $this->urlJump($url_rule_type, true);
            }
        }
    }

    // 首页
    private function getIndexPage()
    {
        $content = parent::parser($this->htmldir . 'index.html'); // 框架标签解析
        $content = $this->parser->parserBefore($content); // CMS公共标签前置解析
        $content = str_replace('{pboot:pagetitle}', $this->config('index_title') ?: '{pboot:sitetitle}-{pboot:sitesubtitle}', $content);
        $content = $this->parser->parserPositionLabel($content, -1, '首页', SITE_INDEX_DIR . '/'); // CMS当前位置标签解析
        $content = $this->parser->parserSpecialPageSortLabel($content, 0, '', SITE_INDEX_DIR . '/'); // 解析分类标签
        $content = $this->parser->parserAfter($content); // CMS公共标签后置解析
        $this->cache($content, true);
    }

    // 列表
    private function getListPage($sort)
    {

        // 调用栏目语言与当前语言不一致时，自动切换语言
        if ($sort->acode != get_lg() && Config::get('lgautosw') !== '0') {
            cookie('lg', $sort->acode);
        }
        if (!$sort->listtpl) {
            error('请到后台设置分类栏目列表页模板！');
        }

        $this->checkPageLevel($sort->gcode, $sort->gtype, $sort->gnote);
        $content = parent::parser($this->htmldir . $sort->listtpl); // 框架标签解析
        $content = $this->parser->parserBefore($content); // CMS公共标签前置解析
        $pagetitle = $sort->title ? "{sort:title}" : "{sort:name}"; // 页面标题
        $content = str_replace('{pboot:pagetitle}', $this->config('list_title') ?: ($pagetitle . '-{pboot:sitetitle}-{pboot:sitesubtitle}'), $content);
        $content = str_replace('{pboot:pagekeywords}', '{sort:keywords}', $content);
        $content = str_replace('{pboot:pagedescription}', '{sort:description}', $content);
        $content = $this->parser->parserPositionLabel($content, $sort->scode); // CMS当前位置标签解析
        $content = $this->parser->parserSortLabel($content, $sort); // CMS分类信息标签解析
        $content = $this->parser->parserListLabel($content, $sort->scode); // CMS分类列表标签解析
        $content = $this->parser->parserAfter($content); // CMS公共标签后置解析

        $this->cache($content, true);
    }

    // 详情页
    private function getContentPage($data)
    {

        // 调用内容语言与当前语言不一致时，自动切换语言
        if ($data->acode != get_lg() && Config::get('lgautosw') !== '0') {
            cookie('lg', $data->acode);
        }

        // 读取模板
        if (!$sort = $this->model->getSort($data->scode)) {
            _404('您访问内容的分类已经不存在，请核对后再试！');
        }

        if (!$sort->contenttpl) {
            error('请到后台设置分类栏目内容页模板！');
        }

        $this->checkPageLevel($sort->gcode, $sort->gtype, $sort->gnote); // 检查栏目权限
        $this->checkPageLevel($data->gcode, $data->gtype, $data->gnote); // 检查内容权限
        $content = parent::parser($this->htmldir . $sort->contenttpl); // 框架标签解析
        $content = $this->parser->parserBefore($content); // CMS公共标签前置解析
        $content = str_replace('{pboot:pagetitle}', $this->config('content_title') ?: '{content:title}-{sort:name}-{pboot:sitetitle}-{pboot:sitesubtitle}', $content);
        $content = str_replace('{pboot:pagekeywords}', '{content:keywords}', $content);
        $content = str_replace('{pboot:pagedescription}', '{content:description}', $content);
        $content = $this->parser->parserPositionLabel($content, $sort->scode); // CMS当前位置标签解析
        $content = $this->parser->parserSortLabel($content, $sort); // CMS分类信息标签解析
        $content = $this->parser->parserCurrentContentLabel($content, $sort, $data); // CMS内容标签解析
        $content = $this->parser->parserCommentLabel($content); // 文章评论
        $content = $this->parser->parserAfter($content); // CMS公共标签后置解析


        $this->cache($content, true);
    }

    // 单页
    private function getAboutPage($sort)
    {
        // 调用栏目语言与当前语言不一致时，自动切换语言
        if ($sort->acode != get_lg() && Config::get('lgautosw') !== '0') {
            cookie('lg', $sort->acode);
        }

        // 读取数据
        if (!$data = $this->model->getAbout($sort->scode)) {
            _404('您访问的内容不存在，请核对后重试！');
        }

        if (!$sort->contenttpl) {
            error('请到后台设置分类栏目内容页模板！');
        }

        $this->checkPageLevel($sort->gcode, $sort->gtype, $sort->gnote);
        $content = parent::parser($this->htmldir . $sort->contenttpl); // 框架标签解析
        $content = $this->parser->parserBefore($content); // CMS公共标签前置解析
        $pagetitle = $sort->title ? "{sort:title}" : "{content:title}"; // 页面标题
        $content = str_replace('{pboot:pagetitle}', $this->config('about_title') ?: ($pagetitle . '-{pboot:sitetitle}-{pboot:sitesubtitle}'), $content);
        $content = str_replace('{pboot:pagekeywords}', '{content:keywords}', $content);
        $content = str_replace('{pboot:pagedescription}', '{content:description}', $content);
        $content = $this->parser->parserPositionLabel($content, $sort->scode); // CMS当前位置标签解析
        $content = $this->parser->parserSortLabel($content, $sort); // CMS分类信息标签解析
        $content = $this->parser->parserCurrentContentLabel($content, $sort, $data); // CMS内容标签解析
        $content = $this->parser->parserCommentLabel($content); // 文章评论
        $content = $this->parser->parserAfter($content); // CMS公共标签后置解析

        $this->cache($content, true);
    }

    // 检查页面权限
    private function checkPageLevel($gcode, $gtype, $gnote)
    {
        if ($gcode) {
            $deny = false;
            $gtype = $gtype ?: 4;
            switch ($gtype) {
                case 1:
                    if ($gcode <= session('pboot_gcode')) {
                        $deny = true;
                    }
                    break;
                case 2:
                    if ($gcode < session('pboot_gcode')) {
                        $deny = true;
                    }
                    break;
                case 3:
                    if ($gcode != session('pboot_gcode')) {
                        $deny = true;
                    }
                    break;
                case 4:
                    if ($gcode > session('pboot_gcode')) {
                        $deny = true;
                    }
                    break;
                case 5:
                    if ($gcode >= session('pboot_gcode')) {
                        $deny = true;
                    }
                    break;
            }
            if ($deny) {
                $gnote = $gnote ?: '您的权限不足，无法浏览本页面！';
                if (session('pboot_uid')) { // 已经登录
                    error($gnote);
                } else {
                    if ($this->config('login_no_wait')) {
                        location(Url::home('member/login', null, "backurl=" . urlencode(get_current_url())));
                    } else {
                        error($gnote, Url::home('member/login', null, "backurl=" . urlencode(get_current_url())));
                    }
                }
            }
        }
    }

    //首页跳转并过滤注入字符
    /*
     * @param $type url模式
     * @param $isSecSiteDir 是否为二级目录 boolean
     * */
    private function urlJump($type, $isSecSiteDir)
    {
        //首页开启了分页直接跳转
        if (strpos($_SERVER['REQUEST_URI'], '/?page=') === 0) {
            $this->getIndexPage();
        }
        $http = is_https() ? 'https://' : 'http://';
        $matches1 = '';
        switch ($type) {
            //普通模式
            case 1:
                $preg1 = '';
                if ($isSecSiteDir === true) {
                    if ($_SERVER['REQUEST_URI'] == SITE_DIR . '/index.php') {
                        $preg1 = '/^\/.*?\/index.php/';
                    } elseif ($_SERVER['REQUEST_URI'] == '/index.php') {
                        $preg1 = '/^\/index.php/';
                    }
                } else {
                    $preg1 = '/^\/index.php/';
                }
                preg_match($preg1, $_SERVER['REQUEST_URI'], $matches1);
                break;
            //伪静态
            case 2:
                $preg2 = '';
                if ($isSecSiteDir === true) {
                    if ($_SERVER['REQUEST_URI'] == SITE_DIR . '/') {
                        $preg2 = '/^\/.*/';
                    } elseif ($_SERVER['REQUEST_URI'] == '/') {
                        $preg2 = '/^\/$/';
                    }
                } else {
                    $preg2 = '/^\//';
                }
                preg_match($preg2, $_SERVER['REQUEST_URI'], $matches1);
                break;
            //兼容模式
            case 3:
                $preg3 = '';
                if ($isSecSiteDir === true) {
                    if (strpos($_SERVER['REQUEST_URI'], SITE_DIR) === 0) {
                        $preg3 = '/(^\/.*?\/index.php)|(^\/.*)/';
                    } elseif (strpos($_SERVER['REQUEST_URI'], '/') === 0) {
                        $preg3 = '/(^\/index.php)|(^\/)/';
                    }
                } else {
                    $preg3 = '/(^\/index.php)|(^\/)/';
                }
                preg_match($preg3, $_SERVER['REQUEST_URI'], $matches1);
                break;
        }
//        if(strpos($matches1[0],'/?page=') !== 0 || $matches1[0]){
//            $this->getIndexPage();
//        }
//        if(strpos($_SERVER['REQUEST_URI'],'?page') !== 0){
//            $this->getIndexPage();
//        }
        if ($matches1[0]) {
            if ($_SERVER['REQUEST_URI'] == $matches1[0]) {
                $this->getIndexPage();
            } elseif (strpos($matches1[0], '/?page=') !== false) {
                $this->getIndexPage();
            } else {
                //读取后台首页404访问配置
                if ($this->config('url_index_404') == 1) {
                    _404('您访问的页面不存在，请核对后重试！');
                }
                header("Location: " . $http . $_SERVER['HTTP_HOST'] . $matches1[0], true, 301);
            }
        } else {
            _404('您访问的页面不存在，请核对后重试！');
        }
    }
}