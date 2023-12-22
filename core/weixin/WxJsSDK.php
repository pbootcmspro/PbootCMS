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

class WxJsSDK
{

    protected $appid;

    protected $secret;

    protected $noncestr;

    public function __construct()
    {
        $this->appid = Config::get('weixin_appid');
        $this->secret = Config::get('weixin_secret');
        $this->noncestr = get_uniqid();
    }

    // 获取JsAPI签名授权信息
    public function getAuthInfo()
    {
        // 签名数据数组
        $data['jsapi_ticket'] = $this->getJsapiTicket();
        $data['noncestr'] = $this->noncestr;
        $data['timestamp'] = time();
        $data['url'] = get_current_url();
        $signature = sha1(urldecode(http_build_query($data)));
        
        // 返回数据数组
        $result['appid'] = $this->appid;
        $result['timestamp'] = $data['timestamp'];
        $result['noncestr'] = $this->noncestr;
        $result['signature'] = $signature;
        return $result;
    }

    // 获取访问Ticket
    private function getJsapiTicket()
    {
        if (! $this->checkJsapiTicket()) {
            $access_token = WxAccessToken::get();
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=$access_token&type=jsapi";
            $result = json_decode(get_url($url));
            if (isset($result->errcode) && $result->errcode) {
                error('获取微信JsapiTicket发生错误：' . $result->errmsg . '(' . $result->errcode . ')');
                return false;
            } else {
                $data['weixin_jsapi_ticket'] = $result->ticket;
                $data['weixin_jsapi_ticket_expires_in'] = $result->expires_in;
                $data['weixin_jsapi_ticket_timestamp'] = time();
                Config::set(sha1('weixin_jsapi_ticket'), $data); // 缓存数据
                return $result->ticket;
            }
        } else {
            return Config::get('weixin_jsapi_ticket');
        }
    }

    // 检查Ticket是否可用
    private function checkJsapiTicket()
    {
        Config::assign(RUN_PATH . '/config/' . sha1('weixin_jsapi_ticket') . '.php');
        if (Config::get('weixin_jsapi_ticket') && time() - Config::get('weixin_jsapi_ticket_timestamp') < Config::get('weixin_jsapi_ticket_expires_in')) {
            return true;
        } else {
            return false;
        }
    }
}
