<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年04月07日
 *  用户模型类
 */
namespace app\admin\model\system;

use core\basic\Model;

class UserModel extends Model
{

    // 获取用户列表
    public function getList()
    {
        $result = parent::table('ay_user')->page()
            ->order('id DESC')
            ->select();
        // 获取每用户的第一角色
        foreach ($result as $key => $value) {
            if ($value->ucode == '10001') {
                $value->rolename = '创始人';
                continue;
            }
            $roles = $this->getUserRole($value->ucode);
            if ($roles) {
                $value->rolename = $roles[0]->name;
            } else {
                $value->rolename = '';
            }
        }
        return $result;
    }

    // 查找用户资料
    public function findUser($field, $keyword)
    {
        $result = parent::table('ay_user')->like($field, $keyword)
            ->order('id DESC')
            ->page()
            ->select();
        // 获取每用户的第一角色
        foreach ($result as $key => $value) {
            if ($value->ucode == '10001') {
                $value->rolename = '创始人';
                continue;
            }
            $roles = $this->getUserRole($value->ucode);
            if ($roles) {
                $value->rolename = $roles[0]->name;
            } else {
                $value->rolename = '';
            }
        }
        return $result;
    }

    // 获取用户列表
    public function getSelect()
    {
        return parent::table('ay_user')->field('ucode,username,realname')
            ->order('id DESC')
            ->select();
    }

    // 检查用户
    public function checkUser($where)
    {
        return parent::table('ay_user')->field('id')
            ->where($where)
            ->find();
    }

    // 获取用户详情
    public function getUser($ucode)
    {
        $result = parent::table('ay_user')->where("ucode='$ucode'")->find();
        // 用户角色信息
        if ($result) {
            $roles = $this->getUserRole($ucode);
            $result->roles = $roles;
            $result->rcodes = get_mapping($roles, 'rcode');
        }
        return $result;
    }

    // 获取最后一个code
    public function getLastCode()
    {
        return parent::table('ay_user')->order('id DESC')->value('ucode');
    }

    // 添加用户
    public function addUser(array $data, array $roles)
    {
        $result = parent::table('ay_user')->insert($data);
        if ($result && $roles) {
            $this->addUserRole($data['ucode'], $roles);
        }
        return $result;
    }

    // 删除用户
    public function delUser($ucode)
    {
        $result = parent::table('ay_user')->where("ucode='$ucode' AND ucode<>10001")->delete();
        if ($result) {
            $this->delUserRole($ucode);
        }
        return $result;
    }

    // 修改用户资料
    public function modUser($ucode, $data, array $roles = null)
    {
        $result = parent::table('ay_user')->where("ucode='$ucode'")->update($data);
        if (is_array($roles)) {
            $result = $this->delUserRole($ucode);
            if ($roles) {
                if (array_key_exists('ucode', $data)) {
                    $ucode = $data['ucode'];
                }
                if ($ucode != '10001')
                    $result = $this->addUserRole($ucode, $roles);
            }
        }
        return $result;
    }

    // 获取指定用户角色表
    private function getUserRole($ucode)
    {
        $table = array(
            'ay_role',
            'ay_user_role'
        );
        $field = array(
            'ay_role.rcode',
            'ay_role.name'
        );
        $where = array(
            "ay_user_role.ucode='$ucode'",
            "ay_role.rcode=ay_user_role.rcode"
        );
        return parent::table($table)->field($field)
            ->where($where)
            ->select();
    }

    // 插入用户角色关联数据
    private function addUserRole($ucode, array $roles)
    {
        return parent::table('ay_user_role')->field('ucode,rcode')
            ->relation($ucode, $roles)
            ->insert();
    }

    // 删除用户角色关联数据
    private function delUserRole($ucode)
    {
        return parent::table('ay_user_role')->where("ucode='$ucode' AND ucode<>10001")->delete();
    }
}