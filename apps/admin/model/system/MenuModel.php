<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年4月3日
 *  菜单管理模型类
 */
namespace app\admin\model\system;

use core\basic\Model;

class MenuModel extends Model
{

    // 获取菜单列表
    public function getList()
    {
        $result = parent::table('ay_menu')->order('pcode,sorting,id')->select();
        $tree = get_tree($result, 0, 'mcode', 'pcode');
        return $tree;
    }

    // 获取菜单选择列表
    public function getSelect()
    {
        $result = parent::table('ay_menu')->field('id,pcode,mcode,name,url,sorting')
            ->order('pcode,sorting,id')
            ->select();
        $tree = get_tree($result, 0, 'mcode', 'pcode');
        return $tree;
    }

    // 获取菜单详情
    public function getMenu($mcode)
    {
        $result = parent::table('ay_menu')->where("mcode='$mcode'")->find();
        if ($result)
            $result->actions = $this->getMenuAction($result->mcode);
        return $result;
    }

    // 检查是否存在
    public function checkMenu($data)
    {
        return parent::table('ay_menu')->where($data)->find();
    }

    // 获取最后一个code
    public function getLastCode()
    {
        return parent::table('ay_menu')->order('mcode DESC')->value('mcode');
    }

    // 新增菜单,$actions为菜单功能按钮数组
    public function addMenu(array $data, array $actions = array())
    {
        $result = parent::table('ay_menu')->autoTime()->insert($data);
        if ($result && $actions) {
            $this->delMenuAction($data['mcode']);
            $this->addMenuAction($data['mcode'], $actions);
        }
        return $result;
    }

    // 删除菜单
    public function delMenu($mcode)
    {
        $result = parent::table('ay_menu')->where("mcode='$mcode' OR pcode='$mcode'")->delete();
        if ($result) {
            $this->delMenuAction($mcode);
        }
        return $result;
    }

    // 修改菜单
    public function modMenu($mcode, $data, array $actions = null)
    {
        $result = parent::table('ay_menu')->where("mcode='$mcode'")
            ->autoTime()
            ->update($data);
        if ($result) {
            if (array_key_exists('mcode', $data) && $mcode != $data['mcode']) {
                $this->modSubMenu($mcode, $data['mcode']);
            }
            if (is_array($actions)) {
                $this->delMenuAction($mcode);
            }
            if ($actions) {
                if (array_key_exists('mcode', $data)) {
                    $mcode = $data['mcode'];
                }
                $this->addMenuAction($mcode, $actions);
            }
        }
        return $result;
    }

    // 修改子菜单的父菜单
    private function modSubMenu($mcode, $mcodeNew)
    {
        return parent::table('ay_menu')->where("pcode='$mcode'")->update("mcode='$mcodeNew'");
    }

    // 获取指定菜单的功能数据
    private function getMenuAction($mcode)
    {
        return parent::table('ay_menu_action')->where("mcode='$mcode'")->column('action');
    }

    // 插入指定菜单功能关联数据
    private function addMenuAction($mcode, array $actions)
    {
        return parent::table('ay_menu_action')->field('mcode,action')
            ->relation($mcode, $actions)
            ->insert();
    }

    // 删除指定菜单功能关联数据
    private function delMenuAction($mcode)
    {
        return parent::table('ay_menu_action')->where("mcode='$mcode'")->delete();
    }

    // 获取菜单权限功能表
    public function getMenuLevel()
    {
        $table = array(
            'ay_menu',
            'ay_menu_action',
            'ay_type'
        );
        $field = array(
            'ay_menu.mcode',
            'ay_menu.name',
            'ay_menu.url',
            'ay_type.item',
            'ay_type.value'
        );
        $where = array(
            "ay_type.tcode='T101'",
            "ay_menu.mcode=ay_menu_action.mcode",
            "ay_type.value=ay_menu_action.action"
        );
        $order = array(
            'ay_menu.mcode',
            'ay_type.tcode',
            'ay_type.sorting'
        );
        $result = parent::table($table)->field($field)
            ->where($where)
            ->order($order)
            ->select();
        $data = array();
        foreach ($result as $key => $value) {
            $data[$value->mcode][] = $value;
        }
        return $data;
    }
}

