<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年4月13日
 *  类型控制器
 */
namespace app\admin\controller\system;

use core\basic\Controller;
use app\admin\model\system\TypeModel;

class TypeController extends Controller
{

    private $model;

    public function __construct()
    {
        $this->model = new TypeModel();
    }

    // 类型列表
    public function index()
    {
        $this->assign('list', true);
        if (! ! ($field = get('field', 'var')) && ! ! ($keyword = get('keyword', 'vars'))) {
            $result = $this->model->findType($field, $keyword);
        } else {
            $result = $this->model->getList();
        }
        
        $this->assign('types', $result);
        
        // 类型选择
        $this->assign('type_select', $this->model->getSelect());
        
        $this->display('system/type.html');
    }

    // 类型增加
    public function add()
    {
        if ($_POST) {
            // 获取数据
            $tcode = post('tcode', 'var');
            $name = post('name');
            $item = post('item');
            $value = post('value', 'var');
            $sorting = post('sorting', 'int');
            
            if (! $tcode) {
                $tcode = get_auto_code($this->model->getLastCode()); // 自动编码
            }
            
            if (! $name) {
                alert_back('类型名不能为空！');
            }
            
            if (! $item) {
                alert_back('类型项名称不能为空！');
            }
            
            if (is_null($value)) {
                alert_back('类型项值不能为空！');
            }
            
            // 构建数据
            $data = array(
                'tcode' => $tcode,
                'name' => $name,
                'item' => $item,
                'value' => $value,
                'sorting' => $sorting,
                'create_user' => session('username'),
                'update_user' => session('username')
            );
            
            // 执行添加
            if ($this->model->addType($data)) {
                $this->log('新增类型' . $tcode . '-' . $item . '成功！');
                if (! ! $backurl = get('backurl')) {
                    success('新增成功！', base64_decode($backurl));
                } else {
                    success('新增成功！', url('/admin/Type/index'));
                }
            } else {
                $this->log('新增类型' . $tcode . '-' . $item . '失败！');
                error('新增失败！', - 1);
            }
        }
    }

    // 类型删除
    public function del()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        if ($id < 7) {
            alert_back('该类型不允许删除！');
        }
        
        if ($this->model->delType($id)) {
            $this->log('删除类型项' . $id . '成功！');
            success('删除成功！', - 1);
        } else {
            $this->log('删除类型项' . $id . '失败！');
            error('删除失败！', - 1);
        }
    }

    // 类型修改
    public function mod()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        
        // 修改操作
        if ($_POST) {
            // 获取数据
            $name = post('name');
            $item = post('item');
            $value = post('value', 'var');
            $sorting = post('sorting', 'int');
            
            if (! $name) {
                alert_back('类型名不能为空！');
            }
            
            if (! $item) {
                alert_back('类型项名称不能为空！');
            }
            
            if (is_null($value)) {
                alert_back('类型项值不能为空！');
            }
            
            // 构建数据
            $data = array(
                'name' => $name,
                'item' => $item,
                'value' => $value,
                'sorting' => $sorting,
                'update_user' => session('username')
            );
            
            // 执行添加
            if ($this->model->modType($id, $data)) {
                $this->log('修改类型项' . $id . '成功！');
                if (! ! $backurl = get('backurl')) {
                    success('修改成功！', base64_decode($backurl));
                } else {
                    success('修改成功！', url('/admin/Type/index'));
                }
            } else {
                location(- 1);
            }
        } else {
            // 调取修改内容
            $this->assign('mod', true);
            
            if (! $result = $this->model->getType($id)) {
                error('编辑的内容已经不存在！', - 1);
            }
            $this->assign('type', $result);
            $this->display('system/type.html');
        }
    }
}