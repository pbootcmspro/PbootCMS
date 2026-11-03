<?php
/**
 * @copyright (C)2016-2099
 * @author pbootcms
 * @email support@pbootcms.com
 *  会员模型类
 */
namespace app\admin\model\member;

use core\basic\Model;

class MemberModel extends Model
{

    // 获取会员列表
    public function getList()
    {
        $field = array(
            'a.*',
            'b.gname'
        );
        $join = array(
            'ay_member_group b',
            'a.gid=b.id',
            'LEFT'
        );
        return parent::table('ay_member a')->field($field)
            ->join($join)
            ->order('a.id desc')
            ->page()
            ->select();
    }

    // 查找会员
    public function findMember($field, $keyword)
    {
        $fields = array(
            'a.*',
            'b.gname'
        );
        $join = array(
            'ay_member_group b',
            'a.gid=b.id',
            'LEFT'
        );
        return parent::table('ay_member a')->field($fields)
            ->join($join)
            ->like($field, $keyword)
            ->order('a.id desc')
            ->page()
            ->select();
    }

    // 检查会员
    public function checkMember($account, $excludeId = 0)
    {
        if (! is_string($account) && ! is_int($account)) {
            return true;
        }
        $query = parent::table('ay_member')->where(array(
            'username' => $account,
            'useremail' => $account,
            'usermobile' => $account
        ), 'OR');
        if ($excludeId) {
            $query->whereCompare('id', '<>', (int) $excludeId);
        }
        return !!$query->find();
    }

    // 获取最后一个code
    public function getLastCode()
    {
        return parent::table('ay_member')->order('id DESC')->value('ucode');
    }

    // 获取会员详情
    public function getMember($id)
    {
        $field = array(
            'a.*',
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
                'a.id' => (int) $id
            ))
            ->find();
    }

    // 添加会员
    public function addMember(array $data)
    {
        return parent::table('ay_member')->insert($data);
    }

    // 删除会员
    public function delMember($id)
    {
        return parent::table('ay_member')->where(array(
            'id' => (int) $id
        ))->delete();
    }

    // 删除会员
    public function delMemberList($ids)
    {
        return parent::table('ay_member')->delete($ids);
    }

    // 修改会员
    public function modMember($id, $data)
    {
        if (! is_array($data) || ! $data) {
            return false;
        }
        return parent::table('ay_member')->where(array(
            'id' => (int) $id
        ))->update($data);
    }

    public function modMemberStatus($id, $status)
    {
        if ($status !== 0 && $status !== 1 && $status !== '0' && $status !== '1') {
            return false;
        }
        return $this->modMember($id, array(
            'status' => (int) $status
        ));
    }

    // 修改会员
    public function modMemberList($ids, $data)
    {
        if (! is_array($data) || ! $data) {
            return false;
        }
        return parent::table('ay_member')->in('id', $ids)->update($data);
    }

    // 会员字段
    public function getFields()
    {
        return parent::table('ay_member_field')->where('status=1')
            ->order('sorting')
            ->select();
    }
}
