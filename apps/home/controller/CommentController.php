<?php
/**
 * @copyright (C)2020-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2020年06月27日
 *  评论控制器
 */
namespace app\home\controller;

use core\basic\Controller;
use app\home\model\ParserModel;
use core\basic\Url;

class CommentController extends Controller
{

    protected $parser;

    protected $model;

    protected $htmldir;

    public function __construct()
    {
        $this->model = new ParserModel();
        $this->parser = new ParserController();
        $this->htmldir = $this->config('tpl_html_dir') ? $this->config('tpl_html_dir') . '/' : '';
    }

    // 评论新增
    public function add()
    {
        if ($_POST) {
            
            if ($this->config('comment_status') === '0') {
                error('系统已经关闭评论功能，请到后台开启再试！');
            }
            
            if (time() - session('lastsub') < 10) {
                alert_back('您提交太频繁了，请稍后再试！');
            }
            
            if (! session('pboot_uid') && ! $this->config('comment_anonymous')) {
                if (! ! $backurl = $_SERVER['HTTP_REFERER']) {
                    alert_location("请先注册登录后再评论！", Url::home('member/login', null, "backurl=" . urlencode($backurl)));
                } else {
                    alert_location("请先注册登录后再评论！", Url::home('member/login'));
                }
            }
            
            // 验证码验证
            $checkcode = strtolower(post('checkcode', 'var'));
            if ($this->config('comment_check_code') !== '0') {
                if (! $checkcode) {
                    alert_back('验证码不能为空！');
                }
                
                if ($checkcode != session('checkcode')) {
                    alert_back('验证码错误！');
                }
            }
            
            // 接收数据
            
            $status = $this->config('comment_verify') === '0' ? 1 : 0;
            if (! $contentid = request('contentid', 'int')) {
                alert_back('文章ID未能正常获取，请使用POST或URL参数传递！');
            }
            
            $comment = post('comment');
            
            $data = array(
                'pid' => request('pid', 'int') ?: 0,
                'contentid' => $contentid,
                'comment' => $comment,
                'uid' => session('pboot_uid'),
                'puid' => request('puid', 'int'),
                'likes' => 0,
                'oppose' => 0,
                'status' => $status,
                'user_ip' => ip2long(get_user_ip()),
                'user_os' => get_user_os(),
                'user_bs' => get_user_bs(),
                'create_time' => get_datetime(),
                'update_user' => '',
                'update_time' => ''
            );
            
            if ($this->model->addComment($data)) {
                session('lastsub', time()); // 记录最后提交时间
                $this->log('文章' . $contentid . '评论提交成功！');
                if ($this->config('comment_send_mail') && $this->config('message_send_to')) {
                    $mail_subject = "【" . CMSNAME . "】您有新的文章评论信息，请注意查收！";
                    $mail_body = '评论内容：' . $comment . '<br>';
                    $mail_body .= '<br>来自网站 ' . get_http_url() . ' （' . date('Y-m-d H:i:s') . '）';
                    sendmail($this->config(), $this->config('message_send_to'), $mail_subject, $mail_body);
                }
                if ($status) {
                    alert_location('评论提交成功！', '-1', 1);
                } else {
                    alert_location('评论提交成功,请等待管理员审核！', '-1', 1);
                }
            } else {
                $this->log('文章评论提交失败！');
                alert_back('提交失败！');
            }
        } else {
            alert_back('提交失败，请使用POST方式提交！');
        }
    }

    // 我的评论
    public function my()
    {
        // 未登录时跳转到用户登录
        if (! session('pboot_uid')) {
            location(Url::home('member/login'));
        }
        
        $content = parent::parser($this->htmldir . 'member/mycomment.html'); // 框架标签解析
        $content = $this->parser->parserBefore($content); // CMS公共标签前置解析
        $content = str_replace('{pboot:pagetitle}', '我的评论-{pboot:sitetitle}-{pboot:sitesubtitle}', $content);
        $content = $this->parser->parserPositionLabel($content, 0, '我的评论', Url::home('comment/my')); // CMS当前位置标签解析
        $content = $this->parser->parserSpecialPageSortLabel($content, - 3, '我的评论', Url::home('comment/my')); // 解析分类标签
        $content = $this->parser->parserMyCommentLabel($content); // 我的评论
        $content = $this->parser->parserAfter($content); // CMS公共标签后置解析
        echo $content;
        exit();
    }

    // 评论删除
    public function del()
    {
        // 未登录时跳转到用户登录
        if (! session('pboot_uid')) {
            location(Url::home('member/login'));
        }
        
        // 执行删除
        if (! ! $id = get('id', 'int')) {
            if ($this->model->delComment($id)) {
                alert_back('删除成功！', 1);
            } else {
                alert_back('删除失败！');
            }
        } else {
            alert_back('传递参数有误！');
        }
    }
}