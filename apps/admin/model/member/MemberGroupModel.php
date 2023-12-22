<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2020年06月25日
 *  会员等级模型类
 */
namespace app\admin\model\member;

use core\basic\Model;

class MemberGroupModel extends Model
{

    // 获取会员等级列表
    public function getList()
    {
        return parent::table('ay_member_group')->order('gcode,id')
            ->page()
            ->select();
    }

    // 查找会员等级
    public function findGroup($field, $keyword)
    {
        return parent::table('ay_member_group')->like($field, $keyword)
            ->order('gcode,id')
            ->page()
            ->select();
    }

    // 获取最后一个code
    public function getLastGid()
    {
        return parent::table('ay_member_group')->order('id DESC')->value('gcode');
    }

    // 获取等级选择列表
    public function getSelect()
    {
        return parent::table('ay_member_group')->field('id,gcode,gname')
            ->order('gcode,id')
            ->select();
    }

    // 获取会员等级详情
    public function getGroup($id)
    {
        return parent::table('ay_member_group')->where("id=$id")->find();
    }

    // 获取会员等级名称
    public function getGroupName($id)
    {
        return parent::table('ay_member_group')->where("id=$id")->value('gname');
    }

    // 添加会员等级
    public function addGroup(array $data)
    {
        return parent::table('ay_member_group')->autoTime()->insert($data);
    }

    // 删除会员等级
    public function delGroup($id)
    {
        return parent::table('ay_member_group')->where("id=$id")->delete();
    }

    // 修改会员等级
    public function modGroup($id, $data)
    {
        return parent::table('ay_member_group')->where("id=$id")->update($data);
    }

    // 查找等级下会员是否存在
    public function findGroupUser($id)
    {
        return parent::table('ay_member')->field('id')
            ->where("gid=$id")
            ->find();
    }

    // 查找等级编号
    public function findGroupCode($gcode, $id = null)
    {
        if ($id) {
            return parent::table('ay_member_group')->field('id')
                ->where("gcode='$gcode' and id<>$id")
                ->find();
        } else {
            return parent::table('ay_member_group')->field('id')
                ->where("gcode='$gcode'")
                ->find();
        }
    }
}