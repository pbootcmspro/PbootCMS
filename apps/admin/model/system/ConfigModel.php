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
        return parent::table('ay_config')->where("name='$name'")->update(['value' => $value]);
    }

    // 获取单个配置值
    public function getValue($name)
    {
        $row = parent::table('ay_config')->field('value')->where("name='$name'")->find();
        return $row ? $row->value : null;
    }

    /**
     * 追加 iframe 白名单精确域名（保存内容时自动加白）
     * @param array $hosts 精确 host 列表
     * @return array ok, added, added_hosts, msg
     */
    public function appendIframeWhitelistHosts(array $hosts)
    {
        if (! $hosts) {
            return array(
                'ok' => true,
                'added' => false,
                'added_hosts' => array(),
                'msg' => '',
            );
        }

        $current = $this->getValue('content_iframe_whitelist');
        $new_hosts = filter_iframe_whitelist_filter_new_hosts($hosts, $current);
        if (! $new_hosts) {
            return array(
                'ok' => true,
                'added' => false,
                'added_hosts' => array(),
                'msg' => '',
            );
        }

        $rs = filter_iframe_whitelist_merge_exact_hosts($current, $new_hosts);
        if (! $rs['added']) {
            return array(
                'ok' => true,
                'added' => false,
                'added_hosts' => array(),
                'msg' => '',
            );
        }

        if ($this->checkConfig("name='content_iframe_whitelist'")) {
            $saved = $this->modValue('content_iframe_whitelist', $rs['value']);
        } else {
            $saved = $this->addConfig(array(
                'name' => 'content_iframe_whitelist',
                'value' => $rs['value'],
                'type' => 2,
                'sorting' => 255,
                'description' => 'iframe域名级白名单',
            ));
        }

        if (! $saved) {
            return array(
                'ok' => false,
                'added' => false,
                'added_hosts' => $new_hosts,
                'msg' => '白名单写入失败',
            );
        }

        path_delete(RUN_PATH . '/config');
        return array(
            'ok' => true,
            'added' => true,
            'added_hosts' => $rs['added_hosts'],
            'msg' => '',
        );
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

