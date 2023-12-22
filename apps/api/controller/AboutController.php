<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年11月11日
 *  单页内容通过分类编码调用接口控制器
 */
namespace app\api\controller;

use core\basic\Controller;
use app\api\model\CmsModel;
use core\basic\Url;
use app\home\controller\ParserController;

class AboutController extends Controller
{

    protected $model;

    public function __construct()
    {
        $this->model = new CmsModel();
    }

    public function index()
    {
        if (! ! $scode = request('scode', 'var')) {
            // 区域获取
            $acode = request('acode', 'var') ?: get_default_lg();
            
            // 读取数据
            if (! ! $data = $this->model->getAbout($acode, $scode)) {
                
                if ($data->outlink) {
                    $data->apilink = $data->outlink;
                } else {
                    $data->apilink = url('/api/content/index/id/' . $data->id, false);
                }
                
                $data->likeslink = url('/home/Do/likes/id/' . $data->id, false);
                $data->opposelink = url('/home/Do/oppose/id/' . $data->id, false);
                
                $Parser = new ParserController();
                $data->contentlink = $Parser->parserLink(1, $data->urlname, 'about', $data->scode, $data->sortfilename);
                $data->content = str_replace(STATIC_DIR . '/upload/', get_http_url() . STATIC_DIR . '/upload/', $data->content);
                json(1, $data);
            } else {
                json(0, '分类编码为' . $scode . '的内容已经不存在了！');
            }
        } else {
            json(1, '请求错误，传递的内容scode有误！');
        }
    }
}