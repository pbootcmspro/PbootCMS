<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2020年06月27日
 *  文章评论模型类
 */
namespace app\admin\model\member;

use core\basic\Model;

class MemberCommentModel extends Model
{

    // 获取列表
    public function getList()
    {
        $field = array(
            'a.*',
            'b.title',
            'c.username',
            'c.nickname',
            'c.headpic'
        );
        $join = array(
            array(
                'ay_content b',
                'a.contentid=b.id',
                'LEFT'
            ),
            array(
                'ay_member c',
                'a.uid=c.id',
                'LEFT'
            )
        );
        return parent::table('ay_member_comment a')->field($field)
            ->join($join)
            ->order('a.id desc')
            ->page()
            ->select();
    }

    // 查找
    public function findComment($field, $keyword)
    {
        $fields = array(
            'a.*',
            'b.title',
            'c.username',
            'c.nickname',
            'c.headpic'
        );
        $join = array(
            array(
                'ay_content b',
                'a.contentid=b.id',
                'LEFT'
            ),
            array(
                'ay_member c',
                'a.uid=c.id',
                'LEFT'
            )
        );
        return parent::table('ay_member_comment a')->field($fields)
            ->join($join)
            ->like($field, $keyword)
            ->order('a.id desc')
            ->page()
            ->select();
    }

    // 获取详情
    public function getComment($id)
    {
        $field = array(
            'a.*',
            'b.title',
            'c.username',
            'c.nickname',
            'c.headpic',
            'd.username as pusername',
            'd.nickname as pnickname'
        );
        $join = array(
            array(
                'ay_content b',
                'a.contentid=b.id',
                'LEFT'
            ),
            array(
                'ay_member c',
                'a.uid=c.id',
                'LEFT'
            ),
            array(
                'ay_member d',
                'a.puid=d.id',
                'LEFT'
            )
        );
        
        return parent::table('ay_member_comment a')->field($field)
            ->join($join)
            ->where("a.id=$id")
            ->find();
    }

    // 删除
    public function delComment($id)
    {
        return parent::table('ay_member_comment')->where("id=$id")->delete();
    }

    // 删除多个
    public function delCommentList($ids)
    {
        return parent::table('ay_member_comment')->delete($ids);
    }

    // 修改
    public function modComment($id, $data)
    {
        return parent::table('ay_member_comment')->where("id=$id")
            ->autoTime()
            ->update($data);
    }

    // 修改多个
    public function modCommentList($ids, $data)
    {
        return parent::table('ay_member_comment')->in('id', $ids)->update($data);
    }
}