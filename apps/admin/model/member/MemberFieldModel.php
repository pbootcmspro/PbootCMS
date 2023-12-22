<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2020年06月25日
 *  会员字段模型类
 */
namespace app\admin\model\member;

use core\basic\Model;

class MemberFieldModel extends Model
{

    // 获取会员字段列表
    public function getList()
    {
        return parent::table('ay_member_field')->order('sorting asc,id asc')
            ->page()
            ->select();
    }

    // 查找会员字段
    public function findField($field, $keyword)
    {
        return parent::table('ay_member_field')->like($field, $keyword)
            ->order('sorting asc,id asc')
            ->page()
            ->select();
    }

    // 检查会员字段
    public function checkField($name)
    {
        return parent::table('ay_member_field')->where("name='$name'")->find();
    }

    // 获取会员字段详情
    public function getField($id)
    {
        return parent::table('ay_member_field')->where("id=$id")->find();
    }

    // 获取会员字段名称
    public function getFieldName($id)
    {
        return parent::table('ay_member_field')->where("id=$id")->value('name');
    }

    // 添加会员字段
    public function addField(array $data)
    {
        return parent::table('ay_member_field')->autoTime()->insert($data);
    }

    // 删除会员字段
    public function delField($id)
    {
        return parent::table('ay_member_field')->where("id=$id")->delete();
    }

    // 修改会员字段
    public function modField($id, $data)
    {
        return parent::table('ay_member_field')->where("id=$id")->update($data);
    }

    // 判断字段是否存在
    public function isExistField($field)
    {
        $fields = parent::tableFields('ay_member');
        if (in_array($field, $fields)) {
            return true;
        } else {
            return false;
        }
    }
}