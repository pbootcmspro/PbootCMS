<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2020年06月25日
 *  会员字段控制器
 */
namespace app\admin\controller\member;

use core\basic\Controller;
use app\admin\model\member\MemberFieldModel;

class MemberFieldController extends Controller
{

    private $model;

    public function __construct()
    {
        $this->model = new MemberFieldModel();
    }

    // 会员字段列表
    public function index()
    {
        if ((! ! $id = get('id', 'int')) && $result = $this->model->getField($id)) {
            $this->assign('more', true);
            $this->assign('field', $result);
        } else {
            $this->assign('list', true);
            if (! ! ($field = get('field', 'var')) && ! ! ($keyword = get('keyword', 'vars'))) {
                $result = $this->model->findField($field, $keyword);
            } else {
                $result = $this->model->getList();
            }
            $this->assign('fields', $result);
        }
        $this->display('member/field.html');
    }

    // 会员字段增加
    public function add()
    {
        if ($_POST) {
            
            // 获取数据
            $name = post('name', 'var');
            $length = post('length', 'int') ?: 20;
            $required = post('required', 'int') ?: 0;
            $description = post('description');
            $sorting = post('sorting', 'int') ?: 255;
            $status = post('status') ?: 1;
            
            if (! $name) {
                alert_back('字段名称不能为空！');
            }
            
            if (! preg_match('/^[a-zA-Z][\w]+$/', $name)) {
                alert_back('字段名称必须以字母开头！');
            }
            
            if (! $description) {
                alert_back('字段描述不能为空！');
            }
            
            // 构建数据
            $data = array(
                'name' => $name,
                'length' => $length,
                'required' => $required,
                'description' => $description,
                'sorting' => $sorting,
                'status' => $status,
                'create_user' => session('username'),
                'update_user' => session('username')
            );
            
            // 字段类型及长度
            $mysql = "varchar($length)";
            $sqlite = "TEXT($length)";
            
            // 字段不存在时创建
            if (! $this->model->isExistField($name)) {
                if (get_db_type() == 'sqlite') {
                    $result = $this->model->amd("ALTER TABLE ay_member ADD COLUMN $name $sqlite NULL");
                } else {
                    $result = $this->model->amd("ALTER TABLE ay_member ADD $name $mysql NULL COMMENT '$description'");
                }
            } elseif ($this->model->checkField($name)) { // 字段存在且已使用则报错
                alert_back('字段已经存在，不能重复添加！');
            }
            
            // 执行会员字段添加
            if ($this->model->addField($data)) {
                $this->log('新增会员字段成功！');
                if (! ! $backurl = get('backurl')) {
                    success('新增成功！', base64_decode($backurl));
                } else {
                    success('新增成功！', url('/admin/MemberField/index'));
                }
            } else {
                $this->log('新增会员字段失败！');
                error('新增失败！', - 1);
            }
        }
    }

    // 会员字段删除
    public function del()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        
        $name = $this->model->getFieldName($id);
        if ($this->model->delField($id)) {
            // mysql数据库执行字段删除，sqlite暂时不支持
            if (! ! $name) {
                if (get_db_type() == 'mysql') {
                    $result = $this->model->amd("ALTER TABLE ay_member DROP COLUMN $name");
                }
            }
            $this->log('删除会员字段' . $id . '成功！');
            success('删除成功！', - 1);
        } else {
            $this->log('删除会员字段' . $id . '失败！');
            error('删除失败！', - 1);
        }
    }

    // 会员字段修改
    public function mod()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        
        // 单独修改状态
        if (($field = get('field', 'var')) && ! is_null($value = get('value', 'var'))) {
            if ($this->model->modField($id, "$field='$value',update_user='" . session('username') . "'")) {
                location(- 1);
            } else {
                alert_back('修改失败！');
            }
        }
        
        // 修改操作
        if ($_POST) {
            
            // 获取数据
            $required = post('required', 'int') ?: 0;
            $description = post('description');
            $sorting = post('sorting', 'int') ?: 255;
            $status = post('status') ?: 1;
            
            if (! $description) {
                alert_back('字段描述不能为空！');
            }
            
            // 构建数据
            $data = array(
                'required' => $required,
                'description' => $description,
                'sorting' => $sorting,
                'status' => $status,
                'update_user' => session('username')
            );
            
            // 执行会员字段修改
            if ($this->model->modField($id, $data)) {
                $this->log('修改会员字段成功！');
                if (! ! $backurl = get('backurl')) {
                    success('修改成功！', base64_decode($backurl));
                } else {
                    success('修改成功！', url('/admin/MemberField/index'));
                }
            } else {
                $this->log('修改会员字段失败！');
                error('修改失败！', - 1);
            }
        } else {
            // 调取修改内容
            $this->assign('mod', true);
            if (! $result = $this->model->getField($id)) {
                error('编辑的内容已经不存在！', - 1);
            }
            
            $this->assign('field', $result);
            $this->display('member/field.html');
        }
    }
}