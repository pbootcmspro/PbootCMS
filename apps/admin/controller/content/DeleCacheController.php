<?php
/**
 * @author xsh
 * @email hnxsh@foxmail.com
 * @date 2021年10月31日
 *
 */
namespace app\admin\controller\content;

use core\basic\Controller;
use app\home\controller\GetPageController;
use core\basic\Config;

class DeleCacheController extends GetPageController
{

    public function index($type = null, $data = null)
    {
        if ($type || ! ! $type = get('type', 'int')) {
                        
            // 设置基础信息
            $this->setinfo();
            
            // 执行更新过程
            switch ($type) {
                case '1':
                    $this->deleIndex();
					$this->deleSort("0");
                    json(1, '更新更新首页和栏目成功');
                    break;
                case '2':
                    $this->deleSortAll("0");
                    json(1, '更新全部栏目分页成功');
                    break;
				case '3':
                    $this->deleContont($data);
                    json(1, '更新内容成功');
                    break;
                default:
                    json(0, '传递参数错误');
            }
        } else {
            $sort_model = model('admin.content.ContentSort');
            $sort_tree = $sort_model->getSelect();
            $this->assign('sort_select', $this->makeSortSelect($sort_tree));
            $this->assign('sort_select2', $this->makeSortSelect($sort_tree, true));
            $this->display('content/delecache.html');
        }
    }

    // 删除首页缓存
    private function deleIndex()
    {
        $path = '/';
		$lg = cookie('lg');
		$wap = '';
		$cacheFile = RUN_PATH . '/cache/' . md5(get_http_url() . $path . $lg . $wap) . '.html';
		unlink($cacheFile);
		if (Config::get('open_wap')){
		$wap = 'wap';
		$cacheFilewap = RUN_PATH . '/cache/' . md5(get_http_url() . $path . $lg . $wap) . '.html';
		unlink($cacheFilewap);
		}
    }

    // 删除栏目首页缓存
    private function deleSort($scode = null)
    {
		$lg = cookie('lg');
		$wap = '';
        $scode = $scode ?: get('scode', 'var');
        if (! $scode) {
            $scodes = $this->model->getScodes('1,2');
        } else {
            $scodes = $this->model->getSubScodes($scode);
        }
        
        $err = '';
        foreach ($scodes as $value) {
            $sort = $this->model->getSort($value);
            if ($sort) {
                if ($sort->type == 1) {
					$link = $this->parser->parserLink($sort->type, $sort->urlname, 'list', $sort->scode, $sort->filename, '', '');
					$cacheFile = RUN_PATH . '/cache/' . md5(get_http_url() . $link . $lg . $wap) . '.html';
					unlink($cacheFile);
					if (Config::get('open_wap')){
							$wap = 'wap';
							$cacheFilewap = RUN_PATH . '/cache/' . md5(get_http_url() . $link . $lg . $wap) . '.html';
							unlink($cacheFilewap);
						}
                } else {
                    $pagelink = $this->parser->parserLink($sort->type, $sort->urlname, 'list', $sort->scode, $sort->filename, '', '');
                    $page = 10;
                    for ($i = 1; $i <= $page; $i ++) {
                        if ($i > 1) {
                            $path = rtrim($pagelink, '/') . '_' . $i . '/';
                        } else {
                            $path = $pagelink;
                        }						
						$cacheFile = RUN_PATH . '/cache/' . md5(get_http_url() . $path . $lg . $wap) . '.html';
						if (Config::get('open_wap')){
							$wap = 'wap';
							$cacheFilewap = RUN_PATH . '/cache/' . md5(get_http_url() . $path . $lg . $wap) . '.html';
							unlink($cacheFilewap);
						}
						unlink($cacheFile);
                    }
                }
            }
        }
    }
	// 删除全部栏目缓存
	private function deleSortAll($scode = null)
    {
		$lg = cookie('lg');
		$wap = '';
        $scode = $scode ?: get('scode', 'var');
        if (! $scode) {
            $scodes = $this->model->getScodes('1,2');
        } else {
            $scodes = $this->model->getSubScodes($scode);
        }
        
        $err = '';
        foreach ($scodes as $value) {
            $sort = $this->model->getSort($value);
            if ($sort) {
                if ($sort->type == 1) {
					$link = $this->parser->parserLink($sort->type, $sort->urlname, 'list', $sort->scode, $sort->filename, '', '');
					$cacheFile = RUN_PATH . '/cache/' . md5(get_http_url() . $link . $lg . $wap) . '.html';
					unlink($cacheFile);
					if (Config::get('open_wap')){
							$wap = 'wap';
							$cacheFilewap = RUN_PATH . '/cache/' . md5(get_http_url() . $link . $lg . $wap) . '.html';
							unlink($cacheFilewap);
						}
                } else {
                    $pagelink = $this->parser->parserLink($sort->type, $sort->urlname, 'list', $sort->scode, $sort->filename, '', '');
                    $page = 5000;
					$jishu = 1;
                    for ($i = 2; $i <= $page; $i ++) {
                        if ($i > 1) {
                            $path = rtrim($pagelink, '/') . '_' . $i . '/';
                        } else {
                            $path = $pagelink;
                        }
						
						$cacheFile = RUN_PATH . '/cache/' . md5(get_http_url() . $path . $lg . $wap) . '.html';
						if(!file_exists($cacheFile)){
							$jishu ++;
							if($jishu > 100){
							break;
							}
						}
						if (Config::get('open_wap')){
							$wap = 'wap';
							$cacheFilewap = RUN_PATH . '/cache/' . md5(get_http_url() . $path . $lg . $wap) . '.html';
							unlink($cacheFilewap);
						}
						unlink($cacheFile);
                    }
                }
            }
        }
    }


    // 删除详情页面缓存
    private function deleContont()
    {		$lg = cookie('lg');
			$wap = '';
		    $idzuixiao = get('idzuixiao', 'var');
			$idzuida = get('idzuida', 'var');
			for($i=$idzuixiao;$i<=$idzuida;$i++){
			$data = $this->model->getContent($i);
            if ($data) {
                $path = $this->parser->parserLink($data->type, $data->urlname, 'content', $data->scode, $data->sortfilename, $data->id, $data->filename);
				$cacheFile = RUN_PATH . '/cache/' . md5(get_http_url() . $path . $lg . $wap) . '.html';
				unlink($cacheFile);
				if (Config::get('open_wap')){
					$wap = 'wap';
					$cacheFilewap = RUN_PATH . '/cache/' . md5(get_http_url() . $path . $lg . $wap) . '.html';
					unlink($cacheFilewap);
				}
            }	
			}
    }

    // 更新内容栏目下拉选择
    private function makeSortSelect($tree, $ext_about = false)
    {
        $list_html = '';
        foreach ($tree as $value) {
            if (get('scode') != $value->scode) { // 不显示本身，避免出现自身为自己的父节点
                if (($ext_about && $value->type == 1) || $value->outlink != '') {
                    $list_html .= "<option value='{$value->scode}' disabled='disabled'>{$this->blank}{$value->name}</option>";
                } else {
                    $list_html .= "<option value='{$value->scode}' >{$this->blank}{$value->name}</option>";
                }
            }
            // 子菜单处理
            if ($value->son) {
                $this->blank .= '　　';
                $list_html .= $this->makeSortSelect($value->son, $ext_about);
            }
        }
        // 循环完后回归位置
        $this->blank = substr($this->blank, 0, - 6);
        return $list_html;
    }

    // 设置公共信息
    private function setinfo()
    {
        // 设置前台模板路径，避免后端调用问题
        if (($tpl_dir = Config::get('tpl_dir')) && array_key_exists('home', $tpl_dir)) {
            $this->assign('tplpath', ROOT_PATH . $tpl_dir['home']);
        } else {
            $this->assign('tplpath', APP_PATH . '/home/view');
        }
        // 定义当前操作为更新静态，后期动作判断
        define('DeleCache', true);
    }
}