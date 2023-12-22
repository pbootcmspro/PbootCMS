<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年03月23日
 *  自定义标签控制器
 */
namespace app\admin\controller\content;

use core\basic\Controller;
use app\admin\model\content\LabelModel;

class LabelController extends Controller
{

    private $model;

    public function __construct()
    {
        $this->model = new LabelModel();
    }

    // 自定义标签列表
    public function index()
    {
        // 修改参数配置
        if ($_POST) {
            foreach ($_POST as $key => $value) {
                if (preg_match('/^[\w\-]+$/', $key)) { // 带有违规字符时不带入查询
                    $data = post($key);
                    $data = str_replace("\r\n", "<br>", $data); // 多行文本时替换回车
                    $this->model->modValue($key, $data);
                }
            }
            success('修改成功！', url('admin/Label/index'));
        }
        $this->assign('list', true);
        $this->assign('labels', $this->model->getList());
        $this->display('content/label.html');
    }

    // 自定义标签字段增加
    public function add()
    {
        if ($_POST) {
            // 获取数据
            $name = post('name', 'var');
            $description = post('description');
            $type = post('type');
            
            if (! $name) {
                alert_back('标签名称不能为空！');
            }

            if ($name && ! preg_match('/^[a-zA-Z0-9\_\/]+$/', $name)) {
                alert_back('标签名称只允许字母、数字、下划线组成!');
            }

            if (! $description) {
                alert_back('标题描述不能为空！');
            }
            
            if (! $type) {
                alert_back('标签类型不能为空！');
            }
            
            // 检查标签名称
            if ($this->model->checkLabel("name='$name'")) {
                alert_back('该自定义标签称已经存在，不能再使用！');
            }
            
            // 构建数据
            $data = array(
                'name' => $name,
                'description' => $description,
                'value' => '', // 添加时设置为空
                'type' => $type,
                'create_user' => session('username'),
                'update_user' => session('username')
            );
            
            // 执行添加
            if ($this->model->addLabel($data)) {
                $this->log('修改自定义标签' . $name . '成功！');
                if (! ! $backurl = get('backurl')) {
                    success('新增成功！', base64_decode($backurl));
                } else {
                    success('新增成功！', url('admin/Label/index' . get_tab('t2'), false));
                }
            } else {
                $this->log('新增自定义标签' . $name . '失败！');
                error('新增失败！', url('admin/Label/index' . get_tab('t2'), false));
            }
        }
    }

    // 自定义标签字段删除
    public function del()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        if ($this->model->delLabel($id)) {
            $this->log('删除自定义标签' . $id . '成功！');
            success('删除成功！', url('admin/Label/index' . get_tab('t2'), false));
        } else {
            $this->log('删除自定义标签' . $id . '失败！');
            error('删除失败！', - 1);
        }
    }

    // 自定义标签字段修改
    public function mod()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        
        // 修改操作
        if ($_POST) {
            // 获取数据
            $name = post('name', 'var');
            $description = post('description');
            $type = post('type');
            if (! $name) {
                alert_back('标签名称不能为空！');
            }

            if ($name && ! preg_match('/^[a-zA-Z0-9\_\/]+$/', $name)) {
                alert_back('标签名称只允许字母、数字、下划线组成!');
            }

            if (! $description) {
                alert_back('标签描述不能为空！');
            }
            
            if (! $type) {
                alert_back('标签类型不能为空！');
            }
            
            // 检查标签名称
            if ($this->model->checkLabel("name='$name' AND id<>$id")) {
                alert_back('该自定义标签名称已经存在，不能再使用！');
            }
            
            // 构建数据
            $data = array(
                'name' => $name,
                'description' => $description,
                'type' => $type,
                'update_user' => session('username')
            );
            
            // 执行添加
            if ($this->model->modLabel($id, $data)) {
                $this->log('修改自定义标签字段' . $id . '成功！');
                success('修改成功！', url('admin/Label/index' . get_tab('t2'), false));
            } else {
                location(- 1);
            }
        } else {
            $this->assign('mod', true);
            
            // 调取修改内容
            $result = $this->model->getLabel($id);
            if (! $result) {
                error('编辑的内容已经不存在！', - 1);
            }
            $this->assign('label', $result);
            
            $this->display('content/label.html');
        }
    }
}