<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2019年05月27日
 *  微信网页授权
 */
namespace core\weixin;

use core\basic\Config;

class WxAccessToken
{

    // 获取全局access_token
    public static function get()
    {
        if (! self::check()) {
            $appid = Config::get('weixin_appid');
            $secret = Config::get('weixin_secret');
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $appid . "&secret=" . $secret;
            $result = json_decode(get_url($url));
            if (isset($result->errcode) && $result->errcode) {
                error('获取微信AccessToken发生错误：' . $result->errmsg . '(' . $result->errcode . ')');
                return false;
            } else {
                $data['weixin_access_token'] = $result->access_token;
                $data['weixin_expires_in'] = $result->expires_in;
                $data['weixin_timestamp'] = time();
                Config::set(sha1('weixin_access_token'), $data); // 缓存数据
                return $result->access_token;
            }
        } else {
            return Config::get('weixin_access_token');
        }
    }

    // 检查access_token是否可用
    private static function check()
    {
        Config::assign(RUN_PATH . '/config/' . sha1('weixin_access_token') . '.php');
        if (Config::get('weixin_access_token') && time() - Config::get('weixin_timestamp') < Config::get('weixin_expires_in')) {
            return true;
        } else {
            return false;
        }
    }
}
