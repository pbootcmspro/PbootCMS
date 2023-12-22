<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2019年07月12日
 *  内链链接模型类
 */
namespace app\admin\model\content;

use core\basic\Model;

class TagsModel extends Model
{

    // 获取文章内链列表
    public function getList()
    {
        return parent::table('ay_tags')->where("acode='" . session('acode') . "'")
            ->order('id asc')
            ->page()
            ->select();
    }

    // 查找文章内链
    public function findTags($field, $keyword)
    {
        return parent::table('ay_tags')->where("acode='" . session('acode') . "'")
            ->like($field, $keyword)
            ->order('id asc')
            ->page()
            ->select();
    }

    // 获取文章内链详情
    public function getTags($id)
    {
        return parent::table('ay_tags')->where("id=$id")
            ->where("acode='" . session('acode') . "'")
            ->find();
    }

    // 添加文章内链
    public function addTags(array $data)
    {
        return parent::table('ay_tags')->autoTime()->insert($data);
    }

    // 删除文章内链
    public function delTags($id)
    {
        return parent::table('ay_tags')->where("id=$id")
            ->where("acode='" . session('acode') . "'")
            ->delete();
    }

    // 修改文章内链
    public function modTags($id, $data)
    {
        return parent::table('ay_tags')->autoTime()
            ->where("id=$id")
            ->where("acode='" . session('acode') . "'")
            ->update($data);
    }
}