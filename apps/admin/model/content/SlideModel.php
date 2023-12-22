<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年3月1日
 *  轮播图模型类
 */
namespace app\admin\model\content;

use core\basic\Model;

class SlideModel extends Model
{

    // 获取轮播图列表
    public function getList()
    {
        return parent::table('ay_slide')->where("acode='" . session('acode') . "'")
            ->order('gid asc,sorting asc,id asc')
            ->page()
            ->select();
    }

    // 查找轮播图
    public function findSlide($field, $keyword)
    {
        return parent::table('ay_slide')->where("acode='" . session('acode') . "'")
            ->like($field, $keyword)
            ->order('gid asc,sorting asc,id asc')
            ->page()
            ->select();
    }

    // 获取轮播图详情
    public function getSlide($id)
    {
        return parent::table('ay_slide')->where("id=$id")
            ->where("acode='" . session('acode') . "'")
            ->find();
    }

    // 获取分组
    public function getGid()
    {
        return parent::table('ay_slide')->distinct()
            ->where("acode='" . session('acode') . "'")
            ->order('gid')
            ->column('gid');
    }

    // 获取最大分组值
    public function getMaxGid()
    {
        return parent::table('ay_slide')->max('gid');
    }

    // 添加轮播图
    public function addSlide(array $data)
    {
        return parent::table('ay_slide')->autoTime()->insert($data);
    }

    // 删除轮播图
    public function delSlide($id)
    {
        return parent::table('ay_slide')->where("id=$id")
            ->where("acode='" . session('acode') . "'")
            ->delete();
    }

    // 修改轮播图
    public function modSlide($id, $data)
    {
        return parent::table('ay_slide')->autoTime()
            ->where("id=$id")
            ->where("acode='" . session('acode') . "'")
            ->update($data);
    }

    public function getImage()
    {
        return parent::table('ay_slide')->column('pic');
    }
}