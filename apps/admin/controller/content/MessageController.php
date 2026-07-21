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
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        
        if ($this->model->delMessage($id)) {
            $this->log('删除留言' . $id . '成功！');
            success('删除成功！', - 1);
        } else {
            $this->log('删除留言' . $id . '失败！');
            error('删除失败！', - 1);
        }
    }

    // 修改
    public function mod()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        
        // 单独修改状态
        if (($field = get('field', 'var')) && ! is_null($value = get('value', 'var'))) {
            if ($this->model->modMessage($id, "$field='$value',update_user='" . session('username') . "'")) {
                location(- 1);
            } else {
                alert_back('修改失败！');
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
                if (! ! $backurl = get('backurl')) {
                    success('修改成功！', base64_decode($backurl));
                } else {
                    success('修改成功！', url('/admin/Message/index'));
                }
            } else {
                location(- 1);
            }
        } else {
            // 调取修改内容
            $this->assign('mod', true);
            if (! $result = $this->model->getMessage($id)) {
                error('编辑的内容已经不存在！', - 1);
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
            error('非法请求！', - 1);
        }
        
        $ids = post('ids');
        if (($idList = parse_strict_id_list($ids)) === false) {
            error('传递的参数值错误！', - 1);
        }
        
        if ($this->model->delMessageByIds($idList)) {
            $this->log('删除留言' . implode(',', $idList) . '成功！');
            alert_location('删除成功！', url('/admin/Message/index'));
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
            error('非法请求！', - 1);
        }
        
        if ($this->model->clearMessage()) {
            $this->log('清空全部留言成功！');
            alert_location('清空成功！', url('/admin/Message/index'));
        } else {
            $this->log('清空全部留言失败！');
            alert_location('清空失败！', url('/admin/Message/index'));
        }
    }
}