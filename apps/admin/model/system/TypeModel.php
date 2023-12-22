<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年04月07日
 *  类型模型类
 */
namespace app\admin\model\system;

use core\basic\Model;

class TypeModel extends Model
{

    // 获取类型列表
    public function getList()
    {
        return parent::table('ay_type')->order('tcode DESC,sorting')
            ->page()
            ->select();
    }

    // 查找类型
    public function findType($field, $keyword)
    {
        return parent::table('ay_type')->like($field, $keyword)
            ->order('tcode DESC,sorting')
            ->page()
            ->select();
    }

    // 获取类型编码选择
    public function getSelect()
    {
        return parent::table('ay_type')->distinct()
            ->field('tcode,name')
            ->order('tcode')
            ->select();
    }

    // 检查类型
    public function checkType($where)
    {
        return parent::table('ay_type')->field('id')
            ->where($where)
            ->find();
    }

    // 获取类型详情
    public function getType($id)
    {
        return parent::table('ay_type')->where("id=$id")->find();
    }

    // 获取指定分类项
    public function getItem($tcode)
    {
        return parent::table('ay_type')->field('item,value')
            ->where("tcode='$tcode'")
            ->select();
    }

    // 获取最后一个code
    public function getLastCode()
    {
        return parent::table('ay_type')->order('id DESC')->value('tcode');
    }

    // 添加类型
    public function addType(array $data)
    {
        return parent::table('ay_type')->autoTime()->insert($data);
    }

    // 删除类型
    public function delType($id)
    {
        return parent::table('ay_type')->where("id=$id")->delete();
    }

    // 修改类型资料
    public function modType($id, $data)
    {
        return parent::table('ay_type')->where("id=$id")
            ->autoTime()
            ->update($data);
    }
}