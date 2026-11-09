<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年3月29日
 *  留言控制器
 */
namespace app\admin\controller\content;

use core\basic\Controller;
use app\admin\model\content\MessageModel;

class MessageController extends Controller
{

    private $model;

    public function __construct()
    {
        $this->model = new MessageModel();
    }

    // 列表
    public function index()
    {
        $this->assign('list', true);
        $this->assign('fields', $this->model->getFormFieldByCode(1)); // 获取字段
        
        if (get('export')) {
            $this->assign('messages', $this->model->getList(false));
            header("Content-Type:application/vnd.ms-excel");
            header('Cache-Control: max-age=0');
            header("Content-Disposition:filename=留言记录-" . date("YmdHis") . ".xls");
            $this->display('content/exmessage.html');
        } else {
            $this->assign('messages', $this->model->getList(true));
            $this->display('content/message.html');
        }
    }

    // 删除
    public function del()
    {
        $back = admin_stay_target(url('/admin/Message/index'));
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请使用正确方式提交！', $back);
        }
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', $back);
        }
        
        if ($this->model->delMessage($id)) {
            $this->log('删除留言' . $id . '成功！');
            success('删除成功！', $back);
        } else {
            $this->log('删除留言' . $id . '失败！');
            error('删除失败！', $back);
        }
    }

    // 修改
    public function mod()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', url('/admin/Message/index'));
        }
        
        // 单独修改状态
        $posted = admin_posted_field(array('status'), url('/admin/Message/index'));
        if ($posted) {
            if (admin_saved($this->model->modMessage($id, $posted[0] . "='" . $posted[1] . "',update_user='" . session('username') . "'"), true)) {
                success('修改成功！', url('/admin/Message/index'));
            } else {
                error('修改失败！', url('/admin/Message/index'));
            }
        }
        
        // 修改操作
        if ($_POST) {
            // 获取数据
            $recontent = post('recontent');
            $status = post('status');
            
            // 构建数据
            $data = array(
                'recontent' => $recontent,
                'status' => $status,
                'update_user' => session('username')
            );
            
            // 执行修改
            if ($this->model->modMessage($id, $data)) {
                $this->log('修改留言' . $id . '成功！');
                success('修改成功！', admin_back_target(url('/admin/Message/index')));
            } else {
                if ($this->model->getMessage($id)) {
                    success('修改成功！', url('/admin/Message/index'));
                } else {
                    error('编辑的内容已经不存在！', url('/admin/Message/index'));
                }
            }
        } else {
            // 调取修改内容
            $this->assign('mod', true);
            if (! $result = $this->model->getMessage($id)) {
                error('编辑的内容已经不存在！', url('/admin/Message/index'));
            }
            $this->assign('message', $result);
            
            $this->display('content/message.html');
        }
    }

    // 删除选中留言（仅允许POST，仅接受严格整数ID集合，如1,2,3）
    public function clear()
    {
        // 非POST请求直接拒绝，避免通过GET链接触发批量删除
        if (! $_POST) {
            error('非法请求！', url('/admin/Message/index'));
        }
        
        $ids = post('ids');
        if (($idList = parse_strict_id_list($ids)) === false) {
            error('传递的参数值错误！', url('/admin/Message/index'));
        }
        
        if (admin_saved($this->model->delMessageByIds($idList), true)) {
            $this->log('删除留言' . implode(',', $idList) . '成功！');
            alert_location('删除成功！', url('/admin/Message/index'), 1);
        } else {
            $this->log('删除留言' . implode(',', $idList) . '失败！');
            alert_location('删除失败！', url('/admin/Message/index'));
        }
    }

    // 清空全部留言（独立显式操作，仅允许POST）
    public function clearAll()
    {
        // 非POST请求直接拒绝，避免通过GET链接触发全表清空
        if (! $_POST) {
            error('非法请求！', url('/admin/Message/index'));
        }
        
        if (admin_saved($this->model->clearMessage(), true)) {
            $this->log('清空全部留言成功！');
            alert_location('清空成功！', url('/admin/Message/index'), 1);
        } else {
            $this->log('清空全部留言失败！');
            alert_location('清空失败！', url('/admin/Message/index'));
        }
    }
}