<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年4月20日
 *  内容列表接口控制器
 */
namespace app\api\controller;

use core\basic\Controller;
use app\api\model\CmsModel;
use core\basic\Url;
use app\home\controller\ParserController;

class ListController extends Controller
{

    protected $model;

    public function __construct()
    {
        $this->model = new CmsModel();
    }

    public function index()
    {
        // 获取参数
        $acode = request('acode', 'var') ?: get_default_lg();
        $scode = request('scode', 'var') ?: '';
        $num = request('num', 'int') ?: $this->config('pagesize');
        $rorder = request('order');
        if (! preg_match('/^[\w\-,\s]+$/', $rorder)) {
            $order = 'a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
        } else {
            switch ($rorder) {
                case 'id':
                    $order = 'a.id DESC,a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC';
                    break;
                case 'date':
                    $order = 'a.date DESC,a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.id DESC';
                    break;
                case 'sorting':
                    $order = 'a.sorting ASC,a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.date DESC,a.id DESC';
                    break;
                case 'istop':
                    $order = 'a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
                    break;
                case 'isrecommend':
                    $order = 'a.isrecommend DESC,a.istop DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
                    break;
                case 'isheadline':
                    $order = 'a.isrecommend DESC,a.istop DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
                    break;
                case 'visits':
                case 'likes':
                case 'oppose':
                    $order = $rorder . ' DESC,a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
                    break;
                case 'random': // 随机取数
                    $db_type = get_db_type();
                    if ($db_type == 'mysql') {
                        $order = "RAND()";
                    } elseif ($db_type == 'sqlite') {
                        $order = "RANDOM()";
                    }
                    break;
                default:
                    if ($rorder) {
                        $orders = explode(',', $rorder);
                        foreach ($orders as $k => $v) {
                            if (strpos($v, 'ext_') === 0) {
                                $orders[$k] = 'e.' . $v;
                            } else {
                                $orders[$k] = 'a.' . $v;
                            }
                        }
                        $order = implode(',', $orders);
                        $order .= ',a.istop DESC,a.isrecommend DESC,a.isheadline DESC,a.sorting ASC,a.date DESC,a.id DESC';
                    }
            }
        }
        
        // 读取数据
        $data = $this->model->getLists($acode, $scode, $num, $order);
        $Parser = new ParserController();
        
        foreach ($data as $key => $value) {
            if ($value->outlink) {
                $data[$key]->apilink = $value->outlink;
            } else {
                $data[$key]->apilink = url('/api/content/index/id/' . $value->id, false);
            }
            $data[$key]->likeslink = url('/home/Do/likes/id/' . $value->id, false);
            $data[$key]->opposelink = url('/home/Do/oppose/id/' . $value->id, false);
            $data[$key]->content = str_replace(STATIC_DIR . '/upload/', get_http_url() . STATIC_DIR . '/upload/', $value->content);
            
            // 返回网页链接地址，便于AJAX调用内容
            $data[$key]->contentlink = $Parser->parserLink(2, $value->urlname, 'content', $value->scode, $value->sortfilename, $value->id, $value->filename);
        }
        
        // 输出数据
        if (request('page') <= PAGECOUNT) {
            json(1, $data);
        } else {
            return json(0, '已经到底了！');
        }
    }
}