<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2020年06月27日
 *  文章评论控制器
 */
namespace app\admin\controller\member;

use core\basic\Controller;
use app\admin\model\member\MemberCommentModel;

class MemberCommentController extends Controller
{

    private $model;

    public function __construct()
    {
        $this->model = new MemberCommentModel();
    }

    // 文章评论列表
    public function index()
    {
        if ((! ! $id = get('id', 'int')) && $result = $this->model->getComment($id)) {
            $this->assign('more', true);
            $this->assign('comment', $result);
        } else {
            $this->assign('list', true);
            if (! ! ($field = get('field', 'var')) && ! ! ($keyword = get('keyword', 'vars'))) {
                $result = $this->model->findComment($field, $keyword);
            } else {
                $result = $this->model->getList();
            }
            $this->assign('comments', $result);
        }
        $this->display('member/comment.html');
    }

    // 文章评论删除
    public function del()
    {
        $back = admin_stay_target(url('/admin/MemberComment/index'));
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请使用正确方式提交！', $back);
        }
        if (! ! $list = post('list')) {
            if ($this->model->delCommentList($list)) {
                $this->log('批量删除评论成功！');
                success('删除成功！', $back);
            } else {
                $this->log('批量删除评论失败！');
                error('删除失败！', $back);
            }
        }
        
        if (! $id = get('id', 'int')) {
            error('请选择要删除的评论！', $back);
        }
        
        if ($this->model->delComment($id)) {
            $this->log('删除文章评论' . $id . '成功！');
            success('删除成功！', $back);
        } else {
            $this->log('删除文章评论' . $id . '失败！');
            error('删除失败！', $back);
        }
    }

    // 字段修改
    public function mod()
    {
        if (! ! $submit = post('submit')) {
            switch ($submit) {
                case 'verify1':
                    $list = post('list');
                    $back = admin_stay_target(url('/admin/MemberComment/index'));
                    if (! $list) {
                        alert_back('请选择要操作的评论！');
                    }
                    if (admin_saved($this->model->modCommentList($list, "status=1"), true)) {
                        $this->log('评论批量审核成功！');
                        success('审核成功！', $back);
                    } else {
                        error('审核失败！', $back);
                    }
                    break;
                case 'verify0':
                    $list = post('list');
                    $back = admin_stay_target(url('/admin/MemberComment/index'));
                    if (! $list) {
                        alert_back('请选择要操作的评论！');
                    }
                    if (admin_saved($this->model->modCommentList($list, "status=0"), true)) {
                        $this->log('评论批量禁用成功！');
                        success('禁用成功！', $back);
                    } else {
                        error('禁用失败！', $back);
                    }
                    break;
            }
        }
        
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', url('/admin/MemberComment/index'));
        }
        
        // 单独修改状态
        $posted = admin_posted_field(array('status'), url('/admin/MemberComment/index'));
        if ($posted) {
            if (admin_saved($this->model->modComment($id, $posted[0] . "='" . $posted[1] . "',update_user='" . session('username') . "'"), true)) {
                success('修改成功！', url('/admin/MemberComment/index'));
            } else {
                error('修改失败！', url('/admin/MemberComment/index'));
            }
        }
    }
}