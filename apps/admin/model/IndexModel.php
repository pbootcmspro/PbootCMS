<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年3月13日
 *  首页模型类
 */
namespace app\admin\model;

use core\basic\Model;

class IndexModel extends Model
{

    // 检查用户账号密码
    public function login($where)
    {
        // 执行登录
        $result = parent::table('ay_user')->field('id,ucode,username,realname')
            ->where($where)
            ->where('status=1')
            ->find();
        
        if ($result) { // 登录成功
            $this->updateLogin($where); // 执行更新登录记录
            $menus = $this->getUserMenu($result->ucode); // 用户菜单
            $result->menus = get_tree($menus, 0, 'mcode', 'pcode'); // 用户菜单树
            $result->rcodes = $this->getUserRcode($result->ucode); // 用户角色
            $result->levels = $this->getUserLevel($result->ucode); // 用户权限
            
            $acodes = $this->getUserAcode($result->ucode); // 获取用户区域
            $areas = $this->getAreas();
            $first_acode = $areas[0]->acode;
            if (in_array($first_acode, $acodes)) {
                array_unshift($acodes, $first_acode);
                $acodes = array_unique($acodes);
            }
            $result->acodes = $acodes;
            $result->area_map = get_mapping($areas, 'name', 'acode'); // 区域代码名称映射表
            $result->area_tree = $this->getUserAreaTree($areas, 0, 'acode', 'pcode', 'son', $result->acodes); // 当前用户的区域树
            return $result;
        } else {
            return false;
        }
    }

    // 用户登录成功后更新登录信息
    private function updateLogin($where)
    {
        $data = array(
            'last_login_ip' => ip2long(get_user_ip()),
            'login_count' => '+=1'
        );
        return parent::table('ay_user')->where($where)
            ->autoTime()
            ->update($data);
    }

    // 获取用户菜单列表
    public function getUserMenu($ucode)
    {
        if ($ucode == '10001') { // 管理员获所有区域
            $field = array(
                'id',
                'mcode',
                'pcode',
                'name',
                'url',
                'shortcut',
                'ico',
                'sorting',
                'status'
            );
            $order = array(
                'sorting',
                'pcode',
                'id'
            );
            $result = parent::table('ay_menu')->distinct()
                ->field($field)
                ->order($order)
                ->select();
        } else {
            $table = array(
                'ay_user',
                'ay_user_role',
                'ay_role',
                'ay_role_level',
                'ay_menu'
            );
            $field = array(
                'ay_menu.id',
                'ay_menu.mcode',
                'ay_menu.pcode',
                'ay_menu.name',
                'ay_menu.url',
                'ay_menu.shortcut',
                'ay_menu.ico',
                'ay_menu.sorting',
                'ay_menu.status'
            );
            $where = array(
                "ay_user.ucode='$ucode'",
                "ay_user.ucode=ay_user_role.ucode",
                "ay_role.rcode=ay_user_role.rcode",
                "ay_role.rcode=ay_role_level.rcode",
                "ay_menu.url=ay_role_level.level"
            );
            $order = array(
                'ay_menu.sorting',
                'ay_menu.pcode',
                'ay_menu.id'
            );
            $result = parent::table($table)->distinct()
                ->field($field)
                ->where($where)
                ->order($order)
                ->select();
        }
        return $result;
    }

    // 获取用户的角色代码
    public function getUserRcode($ucode)
    {
        return parent::table('ay_user_role')->where("ucode='$ucode'")->column('rcode');
    }

    // 获取用户权限列表
    public function getUserLevel($ucode)
    {
        $table = array(
            'ay_user',
            'ay_user_role',
            'ay_role',
            'ay_role_level'
        );
        $where = array(
            "ay_user.ucode='$ucode'",
            "ay_user.ucode=ay_user_role.ucode",
            "ay_role.rcode=ay_user_role.rcode",
            "ay_role.rcode=ay_role_level.rcode"
        );
        return parent::table($table)->distinct()
            ->where($where)
            ->column('ay_role_level.level');
    }

    // 获取用户可管理的区域代码
    public function getUserAcode($ucode)
    {
        if ($ucode == '10001') { // 管理员获所有区域
            $result = parent::table('ay_area')->distinct()->column('acode');
        } else {
            $table = array(
                'ay_user',
                'ay_user_role',
                'ay_role',
                'ay_role_area',
                'ay_area'
            );
            $where = array(
                "ay_user.ucode = '$ucode'",
                "ay_user.ucode=ay_user_role.ucode",
                "ay_role.rcode=ay_user_role.rcode",
                "ay_role.rcode=ay_role_area.rcode",
                "ay_area.acode=ay_role_area.acode"
            );
            $result = parent::table($table)->distinct()
                ->where($where)
                ->column('ay_area.acode');
        }
        return $result;
    }

    // 获取区域列表
    public function getAreas()
    {
        $result = parent::table('ay_area')->field('pcode,acode,name,is_default')
            ->order('is_default DESC,pcode,acode')
            ->select();
        return $result;
    }

    // 生成授权的区域树
    public function getUserAreaTree($data, $tid, $idField, $pidField, $sonName = 'son', $valid = array())
    {
        $tree = array();
        foreach ($data as $key => $value) {
            if ($value->$pidField == "$tid") { // 父亲找到儿子
                $value->$sonName = $this->getUserAreaTree($data, $value->$idField, $idField, $pidField, $sonName, $valid);
                if ($value->$sonName) {
                    $tree[] = $value;
                } elseif (in_array($value->$idField, $valid)) {
                    $tree[] = $value;
                }
            }
        }
        return $tree;
    }

    // 检查当前用户密码
    public function checkUserPwd($password)
    {
        return parent::table('ay_user')->field('id')
            ->where("id=" . session('id') . " AND password='$password'")
            ->find();
    }

    // 修改当前用户信息
    public function modUserInfo($data)
    {
        return parent::table('ay_user')->where("id=" . session('id'))->update($data);
    }

    // 获取用户信息
    public function getUserInfo($ucode)
    {
        $result = parent::table('ay_user')->field('id,ucode,username,realname,last_login_ip,update_time,login_count')
            ->where("ucode='$ucode'")
            ->find();
        return $result;
    }
}