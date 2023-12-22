<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年4月22日
 *  API公共控制类
 */
namespace app\common;

use core\basic\Controller;
use core\basic\Config;

class ApiController extends Controller
{

    public function __construct()
    {
        // 自动缓存基础信息
        cache_config();
        
        // 从配置文件读取cmsname参数来设置系统名称
        define("CMSNAME", $this->config("cmsname") ?: 'PbootCMS');
        
        $this->checkAccess($this->config());
    }

    /**
     * 客户端发起请求必须包含appid、timestamp、signature三个参数;
     * signature通过appid、secret、timestamp连接为一个字符串,然后进行双层md5加密生成;
     */
    public static function checkAccess($config)
    {
        if (! isset($config['api_open']) || ! $config['api_open']) {
            json(0, '系统尚未开启API功能，请到后台配置');
        }
        
        // 验证总开关
        if ($config['api_auth']) {
            
            // 判断用户
            if (! $config['api_appid']) {
                json(0, '请求失败：管理后台接口认证用户配置有误');
            }
            
            // 判断密钥
            if (! $config['api_secret']) {
                json(0, '请求失败：管理后台接口认证密钥配置有误');
            }
            
            // 获取参数
            if (! $appid = request('appid')) {
                json(0, '请求失败：未检查到appid参数');
            }
            if (! $timestamp = request('timestamp')) {
                json(0, '请求失败：未检查到timestamp参数');
            }
            if (! $signature = request('signature')) {
                json(0, '请求失败：未检查到signature参数');
            }
            
            // 验证时间戳
            if (strpos($_SERVER['HTTP_REFERER'], get_http_url()) === false && time() - $timestamp > 15) { // 请求时间戳认证，不得超过15秒
                json(0, '请求失败：接口时间戳验证失败！');
            }
            
            // 验证签名
            if ($signature != md5(md5($config['api_appid'] . $config['api_secret'] . $timestamp))) {
                error('请求失败：接口签名信息错误！');
            }
        }
    }
}