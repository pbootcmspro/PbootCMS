<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年01月03日
 * 应用配置模型类
 */
namespace app\admin\model\system;

use core\basic\Model;

class ConfigModel extends Model
{

    // 获取应用配置列表
    public function getList()
    {
        return parent::table('ay_config')->order('sorting,id')->column('name,value,type,description', 'name');
    }

    // 检查应用配置
    public function checkConfig($where)
    {
        return parent::table('ay_config')->field('id')
            ->where($where)
            ->find();
    }

    // 添加应用配置字段
    public function addConfig(array $data)
    {
        return parent::table('ay_config')->insert($data);
    }

    // 修改应用配置值
    public function modValue($name, $value)
    {
        return parent::table('ay_config')->where("name='$name'")->update("value='$value'");
    }

    // 获取区域及主题
    public function getAreaTheme()
    {
        $field = array(
            'a.*',
            'b.theme'
        );
        $join = array(
            'ay_site b',
            'a.acode=b.acode',
            'LEFT'
        );
        return parent::table('ay_area a')->field($field)
            ->join($join)
            ->order('is_default DESC')
            ->select(1);
    }

    // 获取配置参数
    public function getConfig()
    {
        return parent::table('ay_config')->column('value', 'name');
    }
}

