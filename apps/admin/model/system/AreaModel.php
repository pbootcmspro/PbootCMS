<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年04月07日
 *  区域模型类
 */
namespace app\admin\model\system;

use core\basic\Model;

class AreaModel extends Model
{

    // 获取区域列表
    public function getList()
    {
        $result = parent::table('ay_area')->order('pcode,acode')
            ->page()
            ->select();
        $tree = get_tree($result, 0, 'acode', 'pcode');
        return $tree;
    }

    // 获取区域选择列表
    public function getSelect()
    {
        $result = parent::table('ay_area')->field('pcode,acode,name')
            ->order('pcode,acode')
            ->select();
        $tree = get_tree($result, 0, 'acode', 'pcode');
        return $tree;
    }

    // 检查区域
    public function checkArea($where)
    {
        return parent::table('ay_area')->field('id')
            ->where($where)
            ->find();
    }

    // 获取区域详情
    public function getArea($acode)
    {
        return parent::table('ay_area')->where("acode='$acode'")->find();
    }

    // 获取最后一个code
    public function getLastCode()
    {
        return parent::table('ay_area')->order('id DESC')->value('acode');
    }

    // 添加区域
    public function addArea(array $data)
    {
        if ($data['is_default']) {
            $this->unsetDefault($data['acode']);
        }
        return parent::table('ay_area')->autoTime()->insert($data);
    }

    // 删除区域
    public function delArea($acode)
    {
        return parent::table('ay_area')->where("acode='$acode' OR pcode='$acode'")
            ->where('is_default=0')
            ->delete();
    }

    // 修改区域资料
    public function modArea($acode, $data)
    {
        $result = parent::table('ay_area')->autoTime()
            ->where("acode='$acode'")
            ->update($data);
        if ($data['is_default']) {
            $this->unsetDefault($data['acode']);
        }
        if ($result && array_key_exists('acode', $data) && $acode != $data['acode']) {
            $this->modSubArea($acode, $data['acode']);
        }
        return $result;
    }

    // 当父编号改变时，修改子栏目的父编码
    private function modSubArea($pcode, $pcodeNew)
    {
        return parent::table('ay_area')->where("pcode='$pcode'")
            ->autoTime()
            ->update("pcode='$pcodeNew'");
    }

    // 去除$acode以外的默认区域
    private function unsetDefault($acode)
    {
        parent::table('ay_area')->where("acode<>'$acode'")->update('is_default=0');
    }
}