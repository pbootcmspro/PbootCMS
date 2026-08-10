<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年01月03日
 * 应用配置模型类
 */
namespace app\admin\model\system;

use core\basic\Config;
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

    /**
     * 动态缓存两项是否均已在 ay_config 中
     * @return bool
     */
    public function hasTplHtmlCacheConfig()
    {
        return $this->checkConfig("name='tpl_html_cache'") && $this->checkConfig("name='tpl_html_cache_time'");
    }

    /**
     * 将动态缓存配置从 config.php 迁移至 ay_config（幂等，不覆盖已有库值）
     * 新装/空值兜底与发布默认一致：开启缓存、近似永不过期
     * @return bool 是否成功新增过配置项
     */
    public function migrateTplHtmlCacheConfig()
    {
        $items = array(
            'tpl_html_cache' => array(
                'type' => '1',
                'description' => '动态缓存开关',
                'default' => '1'
            ),
            'tpl_html_cache_time' => array(
                'type' => '2',
                'description' => '缓存有效期(秒)',
                'default' => '900000000000'
            )
        );
        $migrated = false;
        foreach ($items as $name => $meta) {
            if ($this->checkConfig("name='$name'")) {
                continue;
            }
            $value = Config::get($name);
            if ($value === null || $value === '') {
                $value = $meta['default'];
            }
            $ok = $this->addConfig(array(
                'name' => $name,
                'value' => (string) $value,
                'type' => $meta['type'],
                'sorting' => 255,
                'description' => $meta['description']
            ));
            // 仅插入成功才报告迁移；失败时不触发无意义的「已迁移」标记
            if ($ok) {
                $migrated = true;
            }
        }
        return $migrated;
    }
}

