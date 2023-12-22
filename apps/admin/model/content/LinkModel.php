<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年3月1日
 *  友情链接模型类
 */
namespace app\admin\model\content;

use core\basic\Model;

class LinkModel extends Model
{

    // 获取友情链接列表
    public function getList()
    {
        return parent::table('ay_link')->where("acode='" . session('acode') . "'")
            ->order('gid asc,sorting asc,id asc')
            ->page()
            ->select();
    }

    // 查找友情链接
    public function findLink($field, $keyword)
    {
        return parent::table('ay_link')->where("acode='" . session('acode') . "'")
            ->like($field, $keyword)
            ->order('gid asc,sorting asc,id asc')
            ->page()
            ->select();
    }

    // 获取友情链接详情
    public function getLink($id)
    {
        return parent::table('ay_link')->where("id=$id")
            ->where("acode='" . session('acode') . "'")
            ->find();
    }

    // 获取分组
    public function getGid()
    {
        return parent::table('ay_link')->distinct()
            ->where("acode='" . session('acode') . "'")
            ->order('gid')
            ->column('gid');
    }

    // 获取最大分组值
    public function getMaxGid()
    {
        return parent::table('ay_link')->max('gid');
    }

    // 添加友情链接
    public function addLink(array $data)
    {
        return parent::table('ay_link')->autoTime()->insert($data);
    }

    // 删除友情链接
    public function delLink($id)
    {
        return parent::table('ay_link')->where("id=$id")
            ->where("acode='" . session('acode') . "'")
            ->delete();
    }

    // 修改友情链接
    public function modLink($id, $data)
    {
        return parent::table('ay_link')->autoTime()
            ->where("id=$id")
            ->where("acode='" . session('acode') . "'")
            ->update($data);
    }

    public function getImage()
    {
        return parent::table('ay_link')->column('logo');
    }
}