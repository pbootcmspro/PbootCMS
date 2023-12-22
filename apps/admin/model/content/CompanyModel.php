<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年3月24日
 *  公司信息模型类
 */
namespace app\admin\model\content;

use core\basic\Model;

class CompanyModel extends Model
{

    // 获取公司信息
    public function getList()
    {
        return parent::table('ay_company')->where("acode='" . session('acode') . "'")->find();
    }

    // 检查公司信息
    public function checkCompany()
    {
        return parent::table('ay_company')->where("acode='" . session('acode') . "'")->find();
    }

    // 增加公司信息
    public function addCompany($data)
    {
        return parent::table('ay_company')->insert($data);
    }

    // 修改公司信息
    public function modCompany($data)
    {
        return parent::table('ay_company')->where("acode='" . session('acode') . "'")->update($data);
    }

    public function getImage()
    {
        return parent::table('ay_company')->column('weixin');
    }
}