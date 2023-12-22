<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年3月25日
 *  内容模型模型类
 */
namespace app\admin\model\content;

use core\basic\Model;

class ModelModel extends Model
{

    // 获取内容模型列表
    public function getList()
    {
        return parent::table('ay_model')->order('id ASC')
            ->page()
            ->select();
    }

    // 获取模型名称
    public function getName($mcode)
    {
        return parent::table('ay_model')->where("mcode='$mcode'")->value('name');
    }

    // 获取内容模型选择
    public function getSelectAll()
    {
        return parent::table('ay_model')->field('mcode,name,type,listtpl,contenttpl')
            ->order('id ASC')
            ->select();
    }

    // 获取内容模型选择
    public function getModelMenu()
    {
        return parent::table('ay_model')->field('mcode,name,type')
            ->where('status=1')
            ->order('id ASC')
            ->select();
    }

    // 获取模型内容数量
    public function getModelCount($mcode)
    {
        $join = array(
            array(
                'ay_content_sort b',
                'a.scode=b.scode',
                'LEFT'
            ),
            array(
                'ay_model d',
                'b.mcode=d.mcode',
                'LEFT'
            )
        );
        return parent::table('ay_content a')->field('count(*) as count')
            ->where("b.mcode='$mcode'")
            ->where("a.acode='" . session('acode') . "'")
            ->join($join)
            ->find();
    }

    // 获取内容模型选择
    public function getSelect()
    {
        return parent::table('ay_model')->field('mcode,name,type,listtpl,contenttpl')
            ->where('status=1')
            ->order('id ASC')
            ->select();
    }

    // 查找内容模型
    public function findModel($field, $keyword)
    {
        return parent::table('ay_model')->like($field, $keyword)
            ->page()
            ->select();
    }

    // 获取最后一个code
    public function getLastCode()
    {
        return parent::table('ay_model')->order('id DESC')->value('mcode');
    }

    // 获取内容模型详情
    public function getModel($id)
    {
        return parent::table('ay_model')->where("id=$id")->find();
    }

    // 添加内容模型
    public function addModel(array $data)
    {
        return parent::table('ay_model')->autoTime()->insert($data);
    }

    // 删除内容模型
    public function delModel($id)
    {
        $model = parent::table('ay_model')->where('id=' . $id)->find();
        if (parent::table('ay_content_sort')->where("mcode='$model->mcode'")->find()) {
            alert_back('模型下面有栏目，不允许直接删除！');
        }
        return parent::table('ay_model')->where("id=$id")
            ->where("issystem=0")
            ->delete();
    }

    // 修改内容模型
    public function modModel($id, $data)
    {
        return parent::table('ay_model')->autoTime()
            ->where("id=$id")
            ->update($data);
    }

    // 检查URL名字冲突
    public function checkUrlname($urlname, $type, $where = array())
    {
        return parent::table('ay_model')->field('id')
            ->where("urlname='$urlname' AND type<>$type")
            ->where($where)
            ->find();
    }

    // 检查栏目名字冲突
    public function checkSortFilename($urlname)
    {
        return parent::table('ay_content_sort')->field('id')
            ->where("filename='$urlname'")
            ->find();
    }
}