<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年04月07日
 *  角色管理模型类
 */
namespace app\admin\model\system;

use core\basic\Model;

class RoleModel extends Model
{

    // 获取角色列表
    public function getList()
    {
        return parent::table('ay_role')->order('rcode,id DESC')
            ->page()
            ->select();
    }

    // 获取角色选择列表
    public function getSelect()
    {
        return parent::table('ay_role')->field('rcode,name')
            ->order('rcode,id')
            ->select();
    }

    // 检查角色
    public function checkRole($where)
    {
        return parent::table('ay_role')->field('id')
            ->where($where)
            ->find();
    }

    // 获取角色详情
    public function getRole($rcode)
    {
        $result = parent::table('ay_role')->where("rcode='$rcode'")->find();
        if ($result) {
            $result->acodes = $this->getRoleArea($rcode);
            $result->levels = $this->getRoleLevel($rcode);
        }
        return $result;
    }

    // 获取最后一个code
    public function getLastCode()
    {
        return parent::table('ay_role')->order('id DESC')->value('rcode');
    }

    // 添加角色
    public function addRole(array $data, array $acodes, array $levels)
    {
        $result = parent::table('ay_role')->autoTime()->insert($data);
        if ($result) {
            if ($acodes) {
                $this->delRoleArea($data['rcode']);
                $this->addRoleArea($data['rcode'], $acodes);
            }
            if ($levels) {
                $this->delRoleLevel($data['rcode']);
                $this->addRoleLevel($data['rcode'], $levels);
            }
        }
        return $result;
    }

    // 删除角色
    public function delRole($rcode)
    {
        $result = parent::table('ay_role')->where("rcode='$rcode'")->delete();
        if ($result) {
            $this->delRoleArea($rcode);
            $this->delRoleLevel($rcode);
            $this->delUserRole($rcode);
        }
        return $result;
    }

    // 修改角色资料
    public function modRole($rcode, $data, array $acodes = null, array $levels = null)
    {
        $result = parent::table('ay_role')->where("rcode='$rcode'")
            ->autoTime()
            ->update($data);
        if ($result) {
            if (is_array($acodes)) {
                $this->delRoleArea($rcode);
            }
            
            if (is_array($levels)) {
                $this->delRoleLevel($rcode);
            }
            
            if (array_key_exists('rcode', $data)) {
                if ($rcode != $data['rcode']) {
                    $this->modUserRole($rcode, "rcode='" . $data['rcode'] . "'");
                    $rcode = $data['rcode'];
                }
            }
            if ($acodes) {
                $this->addRoleArea($rcode, $acodes);
            }
            if ($levels) {
                $this->addRoleLevel($rcode, $levels);
            }
        }
        return $result;
    }

    // 获取角色的区域数据
    private function getRoleArea($rcode)
    {
        return parent::table('ay_role_area')->where("rcode='$rcode'")->column('acode');
    }

    // 插入角色区域关联数据
    private function addRoleArea($rcode, array $acodes)
    {
        return parent::table('ay_role_area')->field('rcode,acode')
            ->relation($rcode, $acodes)
            ->insert();
    }

    // 删除角色区域关联数据
    private function delRoleArea($rcode)
    {
        return parent::table('ay_role_area')->where("rcode='$rcode'")->delete();
    }

    // 获取角色的权限数据
    private function getRoleLevel($rcode)
    {
        return parent::table('ay_role_level')->where("rcode='$rcode'")->column('level');
    }

    // 插入角色权限关联数据
    private function addRoleLevel($rcode, array $levels)
    {
        return parent::table('ay_role_level')->field('rcode,level')
            ->relation($rcode, $levels)
            ->insert();
    }

    // 删除角色权限关联数据
    private function delRoleLevel($rcode)
    {
        return parent::table('ay_role_level')->where("rcode='$rcode'")->delete();
    }

    // 删除角色用户关联数据
    private function delUserRole($rcode)
    {
        return parent::table('ay_user_role')->where("rcode='$rcode'")->delete();
    }

    // 修改角色用户关联数据
    private function modUserRole($rcode, $data)
    {
        return parent::table('ay_user_role')->where("rcode='$rcode'")->update($data);
    }
}

