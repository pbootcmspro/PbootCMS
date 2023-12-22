<?php
/**
 * @copyright (C)2020-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2020年3月8日
 *  留言控制器
 */
namespace app\home\controller;

use core\basic\Controller;
use app\home\model\ParserModel;
use core\basic\Url;

class MessageController extends Controller
{

    protected $model;

    public function __construct()
    {
        $this->model = new ParserModel();
    }

    // 留言新增
    public function index()
    {
        if ($_POST) {
            
            if ($this->config('message_status') === '0') {
                error('系统已经关闭留言功能，请到后台开启再试！');
            }
            
            if (time() - session('lastsub') < 10) {
                alert_back('您提交太频繁了，请稍后再试！');
            }
            
            // 需登录
            if ($this->config('message_rqlogin') && ! session('pboot_uid')) {
                if (! ! $backurl = $_SERVER['HTTP_REFERER']) {
                    alert_location("请先注册登录后再留言！", Url::home('member/login', null, "backurl=" . urlencode($backurl)));
                } else {
                    alert_location("请先注册登录后再留言！", Url::home('member/login'));
                }
            }
            
            // 验证码验证
            $checkcode = strtolower(post('checkcode', 'var'));
            if ($this->config('message_check_code') !== '0') {
                if (! $checkcode) {
                    alert_back('验证码不能为空！');
                }
                
                if ($checkcode != session('checkcode')) {
                    alert_back('验证码错误！');
                }
            }
            
            // 读取字段
            if (! $form = $this->model->getFormField(1)) {
                alert_back('留言表单不存在任何字段，请核对后重试！');
            }
            
            // 接收数据
            $mail_body = '';
            foreach ($form as $value) {
                $field_data = post($value->name);
                if (is_array($field_data)) { // 如果是多选等情况时转换
                    $field_data = implode(',', $field_data);
                }
                $field_data = preg_replace_r('/pboot:if/i', '', $field_data);
                if ($value->required && ! $field_data) {
                    alert_back($value->description . '不能为空！');
                } else {
                    $data[$value->name] = $field_data;
                    $mail_body .= $value->description . '：' . $field_data . '<br>';
                }
            }
            
            $status = $this->config('message_verify') === '0' ? 1 : 0;
            
            // 设置额外数据
            if ($data) {
                $data['acode'] = get_lg();
                $data['user_ip'] = ip2long(get_user_ip());
                $data['user_os'] = get_user_os();
                $data['user_bs'] = get_user_bs();
                $data['recontent'] = '';
                $data['status'] = $status;
                $data['create_user'] = 'guest';
                $data['update_user'] = 'guest';
                $data['uid'] = session('pboot_uid');
            }
            
            if ($this->model->addMessage($data)) {
                session('lastsub', time()); // 记录最后提交时间
                $this->log('留言提交成功！');
                if ($this->config('message_send_mail') && $this->config('message_send_to')) {
                    $mail_subject = "【" . CMSNAME . "】您有新的" . $value->form_name . "信息，请注意查收！";
                    $mail_body .= '<br>来自网站 ' . get_http_url() . ' （' . date('Y-m-d H:i:s') . '）';
                    sendmail($this->config(), $this->config('message_send_to'), $mail_subject, $mail_body);
                }
                alert_location('提交成功！', '-1', 1);
            } else {
                $this->log('留言提交失败！');
                alert_back('提交失败！');
            }
        } else {
            alert_back('提交失败，请使用POST方式提交！');
        }
    }
}