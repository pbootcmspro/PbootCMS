<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2019年5月27日
 *  
 */
namespace core\weixin;

class WxUser
{

    // 获取指定用户信息
    public function getUserInfo($openid)
    {
        $access_token = WxAccessToken::get();
        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=" . $access_token . "&openid=$openid&lang=zh_CN";
        $result = json_decode(get_url($url));
        if (isset($result->errcode) && $result->errcode) {
            error('获取微信用户信息发生错误：' . $result->errmsg . '(' . $result->errcode . ')');
        } else {
            return $result;
        }
    }

    // 获取用户列表
    public function getUserList($getinfo = false, $next_openid = '')
    {
        $access_token = WxAccessToken::get();
        $url = "https://api.weixin.qq.com/cgi-bin/user/get?access_token=" . $access_token . "&next_openid=" . $next_openid;
        $result = json_decode(get_url($url));
        if (isset($result->errcode) && $result->errcode) {
            error('获取微信用户列表发生错误：' . $result->errmsg . '(' . $result->errcode . ')');
        } else {
            if ($getinfo) {
                foreach ($result->data->openid as $key => $value) {
                    $result->data->userinfo[] = $this->getUserInfo($value);
                }
            }
            return $result;
        }
    }

    // 设置用户标记
    public function setUserRemark($openid, $remark)
    {
        $access_token = WxAccessToken::get();
        $url = "https://api.weixin.qq.com/cgi-bin/user/info/updateremark?access_token=" . $access_token;
        $data = array(
            'openid' => $openid,
            'remark' => $remark
        );
        $result = json_decode(get_url($url, json_encode($data, JSON_UNESCAPED_UNICODE)));
        if (isset($result->errcode) && $result->errcode) {
            error('获取微信用户信息发生错误：' . $result->errmsg . '(' . $result->errcode . ')');
        } else {
            return true;
        }
    }
}