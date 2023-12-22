<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2020年06月26日
 *  会员前台控制器
 */
namespace app\home\controller;

use core\basic\Controller;
use app\home\model\MemberModel;
use core\basic\Url;

class MemberController extends Controller
{

    protected $parser;

    protected $model;

    protected $htmldir;

    public function __construct()
    {
        $this->model = new MemberModel();
        $this->parser = new ParserController();
        $this->htmldir = $this->config('tpl_html_dir') ? $this->config('tpl_html_dir') . '/' : '';
    }

    // 会员登录页面
    public function login()
    {
        // 已经登录时跳转到用户中心
        if (session('pboot_uid')) {
            location(Url::home('member/ucenter'));
        }
        
        // 执行登录验证
        if ($_POST) {
            if ($this->config('login_status') === '0') {
                error('系统已经关闭登录功能，请到后台开启再试！');
            }
            
            // 验证码验证
            $checkcode = strtolower(post('checkcode', 'var'));
            if ($this->config('login_check_code') !== '0') {
                if (! $checkcode) {
                    alert_back('验证码不能为空！');
                }
                
                if ($checkcode != session('checkcode')) {
                    alert_back('验证码错误！');
                }
            }
            
            $username = post('username');
            $password = post('password');
            
            if (! $username) {
                alert_back('用户账号不能为空！');
            }
            
            // 检查用户名
            if (! $this->model->checkUsername("username='$username' or useremail='$username' or usermobile='$username'")) {
                alert_back('用户账号不存在！');
            }
            
            // 检查密码
            if (! $password) {
                alert_back('用户密码不能为空！');
            } else {
                $password = md5(md5($password));
            }
            
            // 登录验证
            if (! ! $login = $this->model->login("(username='$username' or useremail='$username' or usermobile='$username') AND password='$password'")) {
                if (! $login->status) {
                    alert_back('您的账号待审核，请联系管理员！');
                }
                session('pboot_uid', $login->id);
                session('pboot_ucode', $login->ucode);
                session('pboot_username', $login->username);
                session('pboot_useremail', $login->seremail);
                session('pboot_usermobile', $login->usermobile);
                session('pboot_gid', $login->gid);
                session('pboot_gcode', $login->gcode);
                session('pboot_gname', $login->gname);
                
                if (! ! $backurl = get('backurl')) {
                    alert_location('登录成功！', $backurl, 1);
                } else {
                    alert_location('登录成功！', Url::home('member/ucenter'), 1);
                }
            } else {
                alert_back('账号密码错误，请核对后重试！', 0);
            }
        } else {
            $content = parent::parser($this->htmldir . 'member/login.html'); // 框架标签解析
            $content = $this->parser->parserBefore($content); // CMS公共标签前置解析
            $content = str_replace('{pboot:pagetitle}', $this->config('login_title') ?: '会员登录-{pboot:sitetitle}-{pboot:sitesubtitle}', $content);
            $content = $this->parser->parserPositionLabel($content, 0, '会员登录', Url::home('member/login')); // CMS当前位置标签解析
            $content = $this->parser->parserSpecialPageSortLabel($content, - 2, '会员登录', Url::home('member/login')); // 解析分类标签
            $content = $this->parser->parserAfter($content); // CMS公共标签后置解析
            echo $content;
            exit();
        }
    }

    // 会员注册页面
    public function register()
    {
        // 已经登录时跳转到用户中心
        if (session('pboot_uid')) {
            location(Url::home('member/ucenter'));
        }
        
        // 执行注册
        if ($_POST) {
            if ($this->config('register_status') === '0') {
                error('系统已经关闭注册功能，请到后台开启再试！');
            }
            
            if (time() - session('lastreg') < 10) {
                alert_back('您注册太频繁了，请稍后再试！');
            }
            
            // 验证码验证
            $checkcode = strtolower(post('checkcode', 'var'));
            if ($this->config('register_check_code') !== '0') {
                if (! $checkcode) {
                    alert_back('验证码不能为空！');
                }
                
                if ($checkcode != session('checkcode')) {
                    alert_back('验证码错误！');
                }
            }
            
            $ucode = get_auto_code($this->model->getLastUcode(), 1);
            $username = post('username'); // 接受用户名、邮箱、手机三种方式
            $nickname = post('nickname');
            $password = post('password');
            $rpassword = post('rpassword');
            
            $useremail = '';
            $usermobile = '';
            // 注册类型判断
            if ($this->config('register_type') == 2) { // 邮箱注册
                $useremail = $username;
                if (! $useremail) {
                    alert_back('账号不能为空，请输入注册的邮箱账号！');
                }
                if (! preg_match('/^[\w]+@[\w\.]+\.[a-zA-Z]+$/', $useremail)) {
                    alert_back('账号格式不正确，请输入正确的邮箱账号！');
                }
                if ($this->model->checkUsername("useremail='$useremail' OR username='$useremail'")) {
                    alert_back('您输入的邮箱已被注册！');
                }
            } elseif ($this->config('register_type') == 3) { // 手机注册
                $usermobile = $username;
                if (! $usermobile) {
                    alert_back('账号不能为空，请输入注册的手机号码！');
                }
                if (! preg_match('/^1[0-9]{10}$/', $usermobile)) {
                    alert_back('账号格式不正确，请输入正确的手机号码！');
                }
                if ($this->model->checkUsername("usermobile='$usermobile' OR username='$usermobile'")) {
                    alert_back('您输入的手机号码已被注册！');
                }
            } else { // 账号注册
                if (! $username) {
                    alert_back('用户名不能为空！');
                }
                if (! preg_match('/^[\w\@\.]+$/', $username)) {
                    alert_back('用户账号含有不允许的特殊字符！');
                }
                // 检查用户名
                if ($this->model->checkUsername("username='$username' OR useremail='$username' OR usermobile='$username'")) {
                    alert_back('您输入的账号已被注册！');
                }
            }
            
            if ($password != $rpassword) {
                alert_back('确认密码不正确！');
            }
            
            if (! $password) {
                alert_back('密码不能为空！');
            } else {
                $password = md5(md5($password));
            }
            
            // 默认值设置
            $status = $this->config('register_verify') ? 0 : 1; // 默认不需要审核
            $score = $this->config('register_score') ?: 0;
            
            $group = $this->model->getFirstGroup();
            $gid = $this->model->getGroupID($this->config('register_gcode')) ?: $group->id;
            
            // 构建数据
            $data = array(
                'ucode' => $ucode,
                'username' => $username,
                'useremail' => $useremail,
                'usermobile' => $usermobile,
                'nickname' => $nickname,
                'password' => $password,
                'headpic' => '',
                'status' => $status,
                'gid' => $gid,
                'wxid' => '',
                'qqid' => '',
                'wbid' => '',
                'activation' => 1,
                'score' => $score,
                'register_time' => get_datetime(),
                'login_count' => 0,
                'last_login_ip' => 0,
                'last_login_time' => 0
            );
            
            // 读取字段
            if (! ! $field = $this->model->getField()) {
                foreach ($field as $value) {
                    $field_data = post($value->name);
                    if (is_array($field_data)) { // 如果是多选等情况时转换
                        $field_data = implode(',', $field_data);
                    }
                    $field_data = preg_replace_r('pboot:if', '', $field_data);
                    if ($value->required && ! $field_data) {
                        alert_back($value->description . '不能为空！');
                    } else {
                        $data[$value->name] = $field_data;
                    }
                }
            }
            
            // 执行注册
            if ($this->model->register($data)) {
                session('lastreg', time()); // 记录最后提交时间
                if ($status) {
                    alert_location('注册成功！', Url::home('member/login'), 1);
                } else {
                    alert_location('注册成功，请等待管理员审核！', Url::home('member/login'), 1);
                }
            } else {
                error('会员注册失败！', - 1);
            }
        } else {
            $content = parent::parser($this->htmldir . 'member/register.html'); // 框架标签解析
            $content = $this->parser->parserBefore($content); // CMS公共标签前置解析
            $content = str_replace('{pboot:pagetitle}', $this->config('register_title') ?: '会员注册-{pboot:sitetitle}-{pboot:sitesubtitle}', $content);
            $content = $this->parser->parserPositionLabel($content, 0, '会员注册', Url::home('member/register')); // CMS当前位置标签解析
            $content = $this->parser->parserSpecialPageSortLabel($content, - 3, '会员注册', Url::home('member/register')); // 解析分类标签
            $content = $this->parser->parserAfter($content); // CMS公共标签后置解析
            echo $content;
            exit();
        }
    }

    //找回密码
    public function retrieve(){
        if($_POST){
            // 验证码验证
            $checkcode = strtolower(post('checkcode', 'var'));
            $email = post('email');
            $username = post('username');
            $password = post('password');
            if (! $checkcode) {
                alert_back('验证码不能为空！');
            }
            if ($checkcode != session('checkcode')) {
                alert_back('验证码错误！');
            }
            $where = ['username' => $username];
            $userInfo = object_to_array($this->model->checkUsername($where));
            if(!$userInfo){
                alert_back('该用户不存在！');
            }
            if(!empty($userInfo['useremail']) && $userInfo['useremail'] != $email){
                alert_back('与注册邮箱不匹配，请联系管理员！');
            }
            $data = [
                'useremail' => $email,
                'password' => md5(md5($password))
            ];
            $this->model->updatePassword($where,$data);
            alert_location('修改成功！', Url::home('member/login'), 1);
        } else {
            $content = parent::parser($this->htmldir . 'member/retrieve.html'); // 框架标签解析
            $content = $this->parser->parserBefore($content); // CMS公共标签前置解析
            $content = str_replace('{pboot:pagetitle}', $this->config('register_title') ?: '找回密码-{pboot:sitetitle}-{pboot:sitesubtitle}', $content);
            $content = $this->parser->parserPositionLabel($content, 0, '找回密码', Url::home('member/retrieve')); // CMS当前位置标签解析
            $content = $this->parser->parserSpecialPageSortLabel($content, - 3, '找回密码', Url::home('member/retrieve')); // 解析分类标签
            $content = $this->parser->parserAfter($content); // CMS公共标签后置解析
            echo $content;
            exit();
        }

    }

    // 用户中心
    public function ucenter()
    {
        // 未登录时跳转到用户登录
        if (! session('pboot_uid')) {
            location(Url::home('member/login'));
        }
        
        $content = parent::parser($this->htmldir . 'member/ucenter.html'); // 框架标签解析
        $content = $this->parser->parserBefore($content); // CMS公共标签前置解析
        $content = str_replace('{pboot:pagetitle}', $this->config('ucenter_title') ?: '个人中心-{pboot:sitetitle}-{pboot:sitesubtitle}', $content);
        $content = $this->parser->parserPositionLabel($content, 0, '个人中心', Url::home('member/ucenter')); // CMS当前位置标签解析
        $content = $this->parser->parserSpecialPageSortLabel($content, - 4, '个人中心', Url::home('member/ucenter')); // 解析分类标签
        $content = $this->parser->parserAfter($content); // CMS公共标签后置解析
        echo $content;
        exit();
    }

    // 用户修改
    public function umodify()
    {
        // 未登录时跳转到用户登录
        if (! session('pboot_uid')) {
            location(Url::home('member/login'));
        }
        
        // 执行资料修改
        if ($_POST && session('pboot_uid')) {
            $nickname = post('nickname');
            $useremail = post('useremail');
            $usermobile = post('usermobile');
            $opassword = post('opassword');
            $password = post('password');
            $rpassword = post('rpassword');
            $headpic = str_replace(SITE_DIR, '', post('headpic'));
            
            if (! $opassword) {
                alert_back('请输入当前密码！');
            } else {
                if (! $this->model->checkUsername(" password='" . md5(md5($opassword)) . "' AND id='" . session('pboot_uid') . "'")) {
                    alert_back('您输入的当前密码不正确！');
                }
            }
            
            if ($useremail) { // 邮箱校验
                if (! preg_match('/^[\w]+@[\w\.]+\.[a-zA-Z]+$/', $useremail)) {
                    alert_back('邮箱格式不正确，请输入正确的邮箱账号！');
                }
                if ($this->model->checkUsername("(useremail='$useremail' OR username='$useremail') AND id<>'" . session('pboot_uid') . "'")) {
                    alert_back('您输入的邮箱已被注册！');
                }
            }
            
            if ($usermobile) { // 手机检验
                if (! preg_match('/^1[0-9]{10}$/', $usermobile)) {
                    alert_back('手机格式不正确，请输入正确的手机号码！');
                }
                if ($this->model->checkUsername("(usermobile='$usermobile' OR username='$usermobile') AND id<>'" . session('pboot_uid') . "'")) {
                    alert_back('您输入的手机号码已被注册！');
                }
            }
            
            // 构建数据
            $data = array(
                'nickname' => $nickname,
                'useremail' => $useremail,
                'usermobile' => $usermobile,
                'headpic' => $headpic
            );
            
            // 密码修改
            if ($password) {
                if ($password != $rpassword) {
                    alert_back('确认密码不正确！');
                } else {
                    $data['password'] = md5(md5($password));
                }
            }
            
            // 读取字段
            if (! ! $field = $this->model->getField()) {
                foreach ($field as $value) {
                    $field_data = post($value->name);
                    if (is_array($field_data)) { // 如果是多选等情况时转换
                        $field_data = implode(',', $field_data);
                    }
                    $field_data = preg_replace_r('pboot:if', '', $field_data);
                    if ($value->required && ! $field_data) {
                        alert_back($value->description . '不能为空！');
                    } else {
                        $data[$value->name] = $field_data;
                    }
                }
            }
            
            // 不允许修改的字段
            unset($data['id']);
            unset($data['ucode']);
            unset($data['username']);
            unset($data['status']);
            unset($data['gid']);
            unset($data['wxid']);
            unset($data['qqid']);
            unset($data['wbid']);
            unset($data['score']);
            unset($data['register_time']);
            unset($data['login_count']);
            unset($data['last_login_ip']);
            unset($data['last_login_time']);
            
            // 执行修改
            if ($this->model->modUser($data)) {
                alert_location('修改成功！', Url::home('member/umodify'), 1);
            } else {
                error('资料修改失败！', - 1);
            }
        } else {
            $content = parent::parser($this->htmldir . 'member/umodify.html'); // 框架标签解析
            $content = $this->parser->parserBefore($content); // CMS公共标签前置解析
            $content = str_replace('{pboot:pagetitle}', $this->config('umodify_title') ?: '资料修改-{pboot:sitetitle}-{pboot:sitesubtitle}', $content);
            $content = $this->parser->parserPositionLabel($content, 0, '资料修改', Url::home('member/umodify')); // CMS当前位置标签解析
            $content = $this->parser->parserSpecialPageSortLabel($content, - 5, '资料修改', Url::home('member/umodify')); // 解析分类标签
            $content = $this->parser->parserAfter($content); // CMS公共标签后置解析
            echo $content;
            exit();
        }
    }

    // 退出登录
    public function logout()
    {
        session('pboot_uid', '');
        session('pboot_ucode', '');
        session('pboot_username', '');
        session('pboot_useremail', '');
        session('pboot_usermobile', '');
        session('pboot_gid', '');
        session('pboot_gcode', '');
        session('pboot_gname', '');
        location(Url::home('member/login'));
    }

    // 文件上传方法(Ajax)
    public function upload()
    {
        // 必须登录
        if (! session('pboot_uid')) {
            json(0, '请先登录！');
        }
        
        $ext = $this->config('home_upload_ext') ?: "jpg,jpeg,png,gif,xls,xlsx,doc,docx,ppt,pptx,rar,zip,pdf,txt";
        $upload = upload('upload', $ext);
        if (is_array($upload)) {
            json(1, $upload);
        } else {
            json(0, $upload);
        }
    }

    // 发送邮件
    public function sendEmail()
    {
        $retrieve = post('retrieve');
        //$retrieve存在时为找回密码邮箱验证，不进行验证码模式判断
        if(!$retrieve){
            if ($this->config('register_check_code') != 2) {
                json(0, '发送失败，后台配置非邮箱验证码模式！');
            }
        }

        if (time() - session('lastsend') < 10) {
            json(0, '您提交太频繁了，请稍后再试！');
        }
        
        if (! session('sendemail')) {
            json(0, '非法提交发送邮件！');
        }
        
        // 发送邮箱参数
        if (! ! $to = post('to')) {
            if (! preg_match('/^[\w]+@[\w]+\.[a-zA-Z]+$/', $to)) {
                json(0, '邮箱格式不正确，请输入正确的邮箱账号！');
            }
        } else {
            json(0, '发送失败，缺少发送对象参数to！');
        }
        
        // 检查邮箱注册
        if(!$retrieve) {
            if ($this->model->checkUsername("useremail='$to' OR username='$to'")) {
                alert_back('您输入的邮箱已被注册！');
            }
        }
        
        $rs = false;
        if ($to) {
            session('lastsend', time()); // 记录最后提交时间
            $mail_subject = "【" . CMSNAME . "】您有新的验证码信息，请注意查收！";
            $code = create_code(4);
            session('checkcode', strtolower($code));
            $mail_body = "您的验证码为：" . $code;
            $mail_body .= '<br>来自网站 ' . get_http_url() . ' （' . date('Y-m-d H:i:s') . '）';
            $rs = sendmail($this->config(), $to, $mail_subject, $mail_body);
        }
        if ($rs === true) {
            json(1, '发送成功！');
        } else {
            json(0, '发送失败，' . $rs);
        }
    }

    // 检查用户是否注册
    public function isRegister()
    {
        // 接受用户名、邮箱、手机三种方式
        $info = '';
        if (! $username = post('username')) {
            $err = '账号不能为空！';
        }
        
        // 注册类型判断
        if ($this->config('register_type') == 2) { // 邮箱注册
            if (! preg_match('/^[\w]+@[\w\.]+\.[a-zA-Z]+$/', $username)) {
                $err = '账号格式不正确，请输入正确的邮箱账号！';
            }
            if ($this->model->checkUsername("useremail='$username' OR username='$username'")) {
                $err = '您输入的邮箱已被注册！';
            } else {
                $suc = '您输入的邮箱可以使用！';
            }
        } elseif ($this->config('register_type') == 3) { // 手机注册
            if (! preg_match('/^1[0-9]{10}$/', $username)) {
                $err = '账号格式不正确，请输入正确的手机号码！';
            }
            if ($this->model->checkUsername("usermobile='$username' OR username='$username'")) {
                $err = '您输入的手机号码已被注册！';
            } else {
                $suc = '您输入的手机号码可以使用！';
            }
        } else { // 账号注册
            if (! preg_match('/^[\w\@\.]+$/', $username)) {
                $err = '用户账号含有不允许的特殊字符！';
            }
            // 检查用户名
            if ($this->model->checkUsername("username='$username' OR useremail='$username' OR usermobile='$username'")) {
                $err = '您输入的账号已被注册！';
            } else {
                $suc = '您输入的账号可以使用！';
            }
        }
        
        if ($err) {
            json(1, $err);
        } else {
            json(0, $suc);
        }
    }

    public function _empty()
    {
        _404('您访问的地址不存在，请核对再试！');
    }
}