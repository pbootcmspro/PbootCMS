<?php
/**
 * @copyright (C)2016-2099
 * @author pbootcms
 * @email support@pbootcms.com
 *  会员前台模型
 */
namespace app\home\model;

use core\basic\Model;
use core\basic\Config;

class MemberModel extends Model
{

    private function isScalarBindValue($value)
    {
        return is_string($value) || is_int($value) || is_float($value);
    }

    // 用户名/邮箱/手机 OR 匹配条件（参数绑定）
    private function accountOrCondition($account)
    {
        return array(
            'username' => $account,
            'useremail' => $account,
            'usermobile' => $account
        );
    }

    private function bindWhere(array $where, array $allowed)
    {
        if (! $where) {
            return false;
        }
        $bound = array();
        foreach ($where as $key => $value) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                return false;
            }
            if (! $this->isScalarBindValue($value)) {
                return false;
            }
            $bound[$key] = $value;
        }
        return $bound;
    }

    // 按用户名/邮箱/手机任一匹配查找会员
    public function findByAccount($account)
    {
        if (! $this->isScalarBindValue($account)) {
            return null;
        }
        return parent::table('ay_member')->where($this->accountOrCondition($account), 'OR')->find();
    }

    // 检查账号（用户名/邮箱/手机）是否已存在
    public function existsByAccount($account)
    {
        if (! $this->isScalarBindValue($account)) {
            return true;
        }
        return !!$this->findByAccount($account);
    }

    // 检查邮箱或用户名是否已存在
    public function existsByEmail($email, $excludeId = null)
    {
        if (! $this->isScalarBindValue($email)) {
            return true;
        }
        $query = parent::table('ay_member')->where(array(
            'useremail' => $email,
            'username' => $email
        ), 'OR');
        if ($excludeId) {
            $query->whereCompare('id', '<>', (int) $excludeId);
        }
        return !!$query->find();
    }

    // 检查手机或用户名是否已存在
    public function existsByMobile($mobile, $excludeId = null)
    {
        if (! $this->isScalarBindValue($mobile)) {
            return true;
        }
        $query = parent::table('ay_member')->where(array(
            'usermobile' => $mobile,
            'username' => $mobile
        ), 'OR');
        if ($excludeId) {
            $query->whereCompare('id', '<>', (int) $excludeId);
        }
        return !!$query->find();
    }

    // 验证会员密码
    public function verifyMemberPassword($uid, $passwordHash)
    {
        return !!parent::table('ay_member')->where(array(
            'password' => $passwordHash,
            'id' => (int) $uid
        ))->find();
    }

    // 会员登录（按账号 + 密码，参数绑定）
    public function loginByAccount($account, $passwordHash)
    {
        if (! $this->isScalarBindValue($account) || ! $this->isScalarBindValue($passwordHash)) {
            return null;
        }
        $field = array(
            'a.id',
            'a.ucode',
            'a.username',
            'a.useremail',
            'a.usermobile',
            'a.gid',
            'a.status',
            'b.gcode',
            'b.gname'
        );
        $join = array(
            'ay_member_group b',
            'a.gid=b.id',
            'LEFT'
        );
        if (! ! $user = parent::table('ay_member a')->field($field)
            ->join($join)
            ->where($this->accountOrCondition($account), 'OR')
            ->where(array(
                'password' => $passwordHash
            ))
            ->find()) {
            $this->updateLoginStats($user->id);
        }
        return $user;
    }

    // 更新会员登录统计
    private function updateLoginStats($uid)
    {
        $data = array(
            'login_count' => '+=1',
            'last_login_ip' => ip2long(get_user_ip()),
            'last_login_time' => get_datetime()
        );
        $score = Config::get('login_score') ?: 0;
        if (is_numeric($score) && $score > 0) {
            $data['score'] = '+=' . $score;
        }
        parent::table('ay_member')->where(array(
            'id' => (int) $uid
        ))->update($data);
    }

    // 会员注册
    public function register($data)
    {
        return parent::table('ay_member')->insert($data);
    }

    // 检查会员名称
    public function checkUsername($where)
    {
        if (! is_array($where)) {
            return null;
        }
        $bound = $this->bindWhere($where, array(
            'username',
            'useremail',
            'usermobile',
            'id'
        ));
        if ($bound === false) {
            return null;
        }
        return parent::table('ay_member')->where($bound)->find();
    }

    // 读取会员字段
    public function getField()
    {
        return parent::table('ay_member_field')->field('name,description,required')
            ->where('status=1')
            ->order('sorting,id')
            ->select();
    }

    // 获取最后一个code
    public function getLastUcode()
    {
        return parent::table('ay_member')->order('id DESC')->value('ucode');
    }

    // 获取当前会员信息
    public function getUser()
    {
        $uid = session('pboot_uid');
        if ($uid === '' || $uid === null || $uid === false) {
            return null;
        }
        $field = array(
            'a.*',
            'b.gcode',
            'b.gname'
        );
        $join = array(
            'ay_member_group b',
            'a.gid=b.id',
            'LEFT'
        );
        return parent::table('ay_member a')->field($field)
            ->join($join)
            ->where(array(
                'a.id' => $uid
            ))
            ->find();
    }

    // 修改会员资料
    public function modUser($data)
    {
        $uid = session('pboot_uid');
        if ($uid === '' || $uid === null || $uid === false) {
            return false;
        }
        if (! is_array($data) || ! $data) {
            return false;
        }
        return parent::table('ay_member')->where(array(
            'id' => $uid
        ))->update($data);
    }

    // 获取第一个等级
    public function getFirstGroup()
    {
        return parent::table('ay_member_group')->order('gcode asc')->find();
    }

    // 获取等级ID
    public function getGroupID($gcode)
    {
        if (! $this->isScalarBindValue($gcode)) {
            return null;
        }
        return parent::table('ay_member_group')->where(array(
            'gcode' => $gcode
        ))->value('id');
    }

    //未登录状态下找回密码
    public function updatePassword($where, $data)
    {
        if (! is_array($where) || ! is_array($data)) {
            return false;
        }
        $bound = $this->bindWhere($where, array(
            'username',
            'useremail',
            'usermobile',
            'id'
        ));
        if ($bound === false) {
            return false;
        }
        if (count($data) !== 1 || ! array_key_exists('password', $data) || ! is_string($data['password'])) {
            return false;
        }
        return parent::table('ay_member')->where($bound)->update($data);
    }

    public function getImage()
    {
        return parent::table('ay_member')->column('headpic');
    }
}
