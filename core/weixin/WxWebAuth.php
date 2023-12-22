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

class WxWebAuth
{

    protected $appid;

    protected $secret;

    protected $noncestr;

    protected $redirect;

    protected $snsapi = 'snsapi_userinfo';

    public function __construct()
    {
        $this->appid = Config::get('weixin_appid');
        $this->secret = Config::get('weixin_secret');
        $this->noncestr = get_uniqid();
        $this->redirect = Config::get('weixin_redirect');
    }

    // 获取授权
    public function getAuthUser()
    {
        if (! ! $code = get('code')) { // 如果有code参数，意味重新授权方式获取数据，（在用户点击同意授权后跳转地址带code参数）
            $result = $this->getAccessToken($code); // 获取网页授权access_token
            $wx_user = $this->getAuthUserInfo($result->access_token, $result->openid);
        } else {
            // 在系统调取微信授权时，先检查是否已经缓存并且有效，如果无效则试着刷新，如果刷新失败则重新授权获取
            if (($token = session('weixin_web_access_token')) && ($openid = session('weixin_web_openid')) && $this->checkAccessToken($token, $openid)) { // 未过期,直接获取
                $wx_user = $this->getAuthUserInfo($token, $openid);
            } elseif (! ! $refresh_token = session('auth_refresh_token') && $result = $this->refreshAccessToken($refresh_token)) { // 刷新后获取
                $wx_user = $this->getAuthUserInfo($result->access_token, $result->openid);
            } else { // 重新授权
                $this->redirectWebAuth(get_current_url());
                exit();
            }
        }
        return $wx_user;
    }

    // 执行网页授权登录，返回指定地址
    private function redirectWebAuth($redirectUrl)
    {
        if (strpos($redirectUrl, 'http') === FALSE) {
            $http_type = is_https() ? 'https://' : 'http://';
            $redirectUrl = $http_type . $_SERVER['HTTP_HOST'] . $redirectUrl;
        }
        $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$this->appid}&redirect_uri=$redirectUrl&response_type=code&scope=" . $this->snsapi . "&state=weixin#wechat_redirect";
        header('Location:' . $url);
    }

    // 获取网页授权后用户信息，传递用户令牌及用户识别码
    private function getAuthUserInfo($token, $openid)
    {
        $url = "https://api.weixin.qq.com/sns/userinfo?access_token=$token&openid=$openid&lang=zh_CN";
        $result = json_decode(get_url($url));
        if (isset($result->errcode) && $result->errcode) {
            error('获取用户基础信息发生错误，请关闭后重新进入,错误：' . $result->errmsg);
        }
        return $result;
    }

    // 获取微信网页授权token,这里通过code换取的是一个特殊的网页授权access_token,与基础支持中的access_token（该access_token用于调用其他接口）不同
    private function getAccessToken($code)
    {
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$this->appid}&secret={$this->secret}&code=$code&grant_type=authorization_code";
        $result = json_decode(get_url($url));
        if (isset($result->errcode) && $result->errcode) {
            error('获取用户网页登录授权令牌发生错误，请关闭后重新进入,错误：' . $result->errmsg);
        }
        // 因为此access_token每个访问用户不同，这里用session来缓存
        session('weixin_web_access_token', $result->access_token);
        session('weixin_web_refresh_token', $result->refresh_token);
        session('weixin_web_openid', $result->openid);
        return $result;
    }

    // 检验微信网页授权access_token是否有效
    private function checkAccessToken($token, $openid)
    {
        $url = "https://api.weixin.qq.com/sns/auth?access_token=$token&openid=$openid";
        $result = json_decode(get_url($url));
        if (isset($result->errcode) && $result->errcode) {
            return false;
        } else {
            return true;
        }
    }

    // 刷新微信网页授权token，传递用户上一次获取的刷新令牌
    private function refreshAccessToken($refreshToken)
    {
        $url = "https://api.weixin.qq.com/sns/oauth2/refresh_token?appid={$this->appid}&grant_type=refresh_token&refresh_token=$refreshToken";
        $result = json_decode(get_url($url));
        if (isset($result->errcode) && $result->errcode) {
            return false;
        }
        session('weixin_web_access_token', $result->access_token);
        session('weixin_web_refresh_token', $result->refresh_token);
        session('weixin_web_openid', $result->openid);
        return $result;
    }
}
