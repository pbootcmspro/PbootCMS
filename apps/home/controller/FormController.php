<?php
/**
 * @copyright (C)2020-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2020年3月8日
 *  表单控制器
 */
namespace app\home\controller;

use core\basic\Controller;
use app\home\model\ParserModel;

class FormController extends Controller
{

    protected $model;

    public function __construct()
    {
        $this->model = new ParserModel();
    }

    // 表单提交
    public function index()
    {
        // 在非兼容模式接受地址第二参数值
        if (defined('RVAR')) {
            $_GET['fcode'] = RVAR;
        }
        
        if ($_POST) {
            
            if ($this->config('form_status') === '0') {
                error('系统已经关闭表单功能，请到后台开启再试！');
            }
            
            if (time() - session('lastsub') < 10) {
                alert_back('您提交太频繁了，请稍后再试！');
            }
            
            if (! $fcode = get('fcode', 'var')) {
                alert_back('传递的表单编码有误！');
            }
            
            if ($fcode == 1) {
                alert_back('表单提交地址有误，留言提交请使用留言专用地址!');
            }
            
            // 验证码验证
            $checkcode = strtolower(post('checkcode', 'var'));
            if ($this->config('form_check_code') !== '0') {
                if (! $checkcode) {
                    alert_back('验证码不能为空！');
                }
                if ($checkcode != session('checkcode')) {
                    alert_back('验证码错误！');
                }
            }
            
            // 读取字段
            if (! $form = $this->model->getFormField($fcode)) {
                alert_back('接收表单不存在任何字段，请核对后重试！');
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
            
            // 设置创建时间
            if ($data) {
                $data['create_time'] = get_datetime();
            }
            
            // 写入数据
            if ($this->model->addForm($value->table_name, $data)) {
                session('lastsub', time()); // 记录最后提交时间
                $this->log('提交表单数据成功！');
                if ($this->config('form_send_mail') && $this->config('message_send_to')) {
                    $mail_subject = "【" . CMSNAME . "】您有新的" . $value->form_name . "信息，请注意查收！";
                    $mail_body .= '<br>来自网站 ' . get_http_url() . ' （' . date('Y-m-d H:i:s') . '）';
                    sendmail($this->config(), $this->config('message_send_to'), $mail_subject, $mail_body);
                }
                alert_location('提交成功！', '-1', 1);
            } else {
                $this->log('提交表单数据失败！');
                alert_back('提交失败！');
            }
        } else {
            alert_back('提交失败，请使用POST方式提交！');
        }
    }
}