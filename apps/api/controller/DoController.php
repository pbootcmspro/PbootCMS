<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年3月8日
 *  
 */
namespace app\api\controller;

use core\basic\Controller;
use app\api\model\DoModel;

class DoController extends Controller
{

    private $model;

    public function __construct()
    {
        $this->model = new DoModel();
    }

    // 点赞
    public function likes()
    {
        if (! ! $id = request('id', 'int')) {
            $this->model->addLikes($id);
            json(1, '点赞成功');
        } else {
            json(0, '点赞失败');
        }
    }

    // 反对
    public function oppose()
    {
        if (! ! $id = request('id', 'int')) {
            $this->model->addOppose($id);
            json(1, '反对成功');
        } else {
            json(0, '反对失败');
        }
    }
}



