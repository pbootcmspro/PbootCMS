<?php
/**
 * @copyright (C)2016-2099
 * @author pbootcms
 * @email support@pbootcms.com
 * @date 2026年8月27日
 *  生成llms.txt内容清单，规范见 https://llmstxt.org/
 */
namespace app\home\controller;

use core\basic\Controller;
use core\basic\Url;
use app\home\model\SitemapModel;
use app\home\model\ParserModel;

class LlmsController extends Controller
{

    protected $model;

    protected $siteModel;

    protected $parser;

    public function __construct()
    {
        $this->model = new SitemapModel();
        $this->siteModel = new ParserModel();
    }

    public function index()
    {
        // 未开启时不输出空文件，直接按不存在处理；必须在发送内容类型前判断
        if (! $this->config('llms_open')) {
            _404('您访问的页面不存在，请核对后重试！');
        }

        header('Content-type: text/plain; charset=utf-8');
        // 正文取自站点内容，禁止浏览器与中间代理按内容嗅探改判类型
        header('X-Content-Type-Options: nosniff');
        // 该纯文本页仅供模型抓取，自身无需被搜索引擎收录
        header('X-Robots-Tag: noindex, nofollow');
        echo $this->render();
    }

    // 组装完整llms.txt文本
    private function render()
    {
        $site = $this->siteModel->getSite();

        $title = llms_plain_text($site ? $site->title : '', 200);
        $blocks = array();
        $blocks[] = '# ' . ($title !== '' ? $title : get_http_host());

        $description = llms_plain_text($site ? $site->description : '', 500);
        if ($description !== '') {
            $blocks[] = '> ' . $description;
        }

        $intro = llms_plain_text($this->config('llms_intro'), 200);
        if ($intro !== '') {
            $blocks[] = $intro;
        }

        $sorts = $this->model->getLlmsSorts();
        $tree = $this->makeTree($sorts);
        $optional_scodes = $this->getOptionalScodes($tree['map']);

        foreach ($tree['roots'] as $scode) {
            if (in_array($scode, $optional_scodes, true)) {
                continue;
            }
            $lines = $this->makeSectionLines($scode, $tree, $optional_scodes);
            if (! $lines) {
                continue;
            }
            $blocks[] = '## ' . llms_plain_text($tree['map'][$scode]->name, 200) . "\n" . implode("\n", $lines);
        }

        if (! ! $optional = $this->makeOptionalLines($optional_scodes, $tree)) {
            $blocks[] = "## Optional\n" . implode("\n", $optional);
        }

        return implode("\n\n", $blocks) . "\n";
    }

    // 将栏目平铺结果整理为编码索引与父子关系
    private function makeTree($sorts)
    {
        $map = array();
        $children = array();
        $roots = array();

        foreach ($sorts as $sort) {
            $map[$sort->scode] = $sort;
        }
        foreach ($sorts as $sort) {
            if ($sort->pcode && isset($map[$sort->pcode])) {
                if (! isset($children[$sort->pcode])) {
                    $children[$sort->pcode] = array();
                }
                $children[$sort->pcode][] = $sort->scode;
            } else {
                $roots[] = $sort->scode;
            }
        }

        return array(
            'map' => $map,
            'children' => $children,
            'roots' => $roots
        );
    }

    // 读取后台选定的Optional段栏目，仅保留当前语言下真实存在的编码
    private function getOptionalScodes(array $map)
    {
        $scodes = array();
        foreach (explode(',', llms_normalize_scodes($this->config('llms_optional_scodes'))) as $scode) {
            if ($scode !== '' && isset($map[$scode])) {
                $scodes[] = $scode;
            }
        }
        return $scodes;
    }

    // 生成一个顶级栏目分段内的全部链接行（含子栏目与栏目下内容）
    private function makeSectionLines($scode, array $tree, array $optional_scodes)
    {
        $sort = $tree['map'][$scode];
        $lines = array();

        // 外链栏目实际跳转站外，不作为本站内容清单输出
        if (! $sort->outlink) {
            if ($sort->type == 1) {
                if ($this->config('llms_inc_about') !== '0') {
                    $lines[] = llms_link_line($sort->name, $this->sortUrl($sort), $this->makeNote($sort->description));
                }
            } else {
                if ($this->config('llms_inc_list') !== '0') {
                    $lines[] = llms_link_line($sort->name, $this->sortUrl($sort), $this->makeNote($sort->description));
                }
                if ($this->config('llms_inc_content') !== '0') {
                    $lines = array_merge($lines, $this->makeContentLines($sort->scode));
                }
            }
        }

        if (isset($tree['children'][$scode])) {
            foreach ($tree['children'][$scode] as $child) {
                if (in_array($child, $optional_scodes, true)) {
                    continue;
                }
                $lines = array_merge($lines, $this->makeSectionLines($child, $tree, $optional_scodes));
            }
        }

        return array_filter($lines);
    }

    // 生成指定列表栏目下的内容链接行
    private function makeContentLines($scode)
    {
        $lines = array();
        $contents = $this->model->getLlmsSortContent($scode, $this->getSortNum());
        foreach ($contents as $content) {
            if ($content->outlink) { // 外链内容跳转站外，不输出
                continue;
            }
            $link = $this->getParser()->parserLink(2, $content->urlname, 'content', $content->scode, $content->sortfilename, $content->id, $content->filename);
            $lines[] = llms_link_line($content->title, get_http_url() . $link, $this->makeNote($content->description));
        }
        return array_filter($lines);
    }

    // 生成Optional段链接行，该段为上下文不足时可优先跳过的次要链接
    private function makeOptionalLines(array $optional_scodes, array $tree)
    {
        $lines = array();
        foreach ($optional_scodes as $scode) {
            // 父子两级都被选中时只从最上层展开一次，避免子树重复输出
            if ($this->hasOptionalAncestor($scode, $tree, $optional_scodes)) {
                continue;
            }
            // 整棵子树随之移入本段，否则被选栏目的子栏目及其内容会两边都不输出
            $lines = array_merge($lines, $this->makeSectionLines($scode, $tree, array()));
        }
        if ($this->config('llms_with_sitemap')) {
            $lines[] = llms_link_line('Sitemap', get_http_url() . Url::home('sitemap.xml', false), '站点全量URL清单');
        }
        return array_filter($lines);
    }

    // 判断栏目的某级父栏目是否也被选入Optional段
    private function hasOptionalAncestor($scode, array $tree, array $optional_scodes)
    {
        $seen = array();
        $pcode = $tree['map'][$scode]->pcode;
        // 数据异常形成环时靠 seen 兜底，此处从任意节点向上回溯，不像分段渲染那样天然无环
        while ($pcode && isset($tree['map'][$pcode]) && ! isset($seen[$pcode])) {
            if (in_array($pcode, $optional_scodes, true)) {
                return true;
            }
            $seen[$pcode] = true;
            $pcode = $tree['map'][$pcode]->pcode;
        }
        return false;
    }

    // 栏目绝对地址，动态、伪静态、静态三种模式统一由parserLink处理
    private function sortUrl($sort)
    {
        $pagetype = $sort->type == 1 ? 'about' : 'list';
        return get_http_url() . $this->getParser()->parserLink($sort->type, $sort->urlname, $pagetype, $sort->scode, $sort->filename);
    }

    // 标签解析器按需实例化，其构造会读取内容字段白名单，无链接可生成时不必付出该开销
    private function getParser()
    {
        if ($this->parser === null) {
            $this->parser = new ParserController();
        }
        return $this->parser;
    }

    // 按后台配置生成链接说明文字
    private function makeNote($description)
    {
        if ($this->config('llms_desc_source') === 'none') {
            return '';
        }
        return llms_plain_text($description, $this->getDescLen());
    }

    // 每栏目内容条数上限，防止文件膨胀
    private function getSortNum()
    {
        $num = (int) $this->config('llms_sort_num');
        if ($num < 1) {
            $num = 20;
        }
        return $num > 500 ? 500 : $num;
    }

    // 说明文字截断长度
    private function getDescLen()
    {
        $len = (int) $this->config('llms_desc_len');
        if ($len < 1) {
            $len = 100;
        }
        return $len > 500 ? 500 : $len;
    }
}
