<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2020年06月25日
 *  会员等级控制器
 */
namespace app\admin\controller\member;

use core\basic\Controller;
use app\admin\model\member\MemberGroupModel;

class MemberGroupController extends Controller
{

    private $model;

    public function __construct()
    {
        $this->model = new MemberGroupModel();
    }

    // 会员等级列表
    public function index()
    {
        if ((! ! $id = get('id', 'int')) && $result = $this->model->getGroup($id)) {
            $this->assign('more', true);
            $this->assign('group', $result);
        } else {
            $this->assign('list', true);
            if (! ! ($field = get('field', 'var')) && ! ! ($keyword = get('keyword', 'vars'))) {
                $result = $this->model->findGroup($field, $keyword);
            } else {
                $result = $this->model->getList();
            }
            $this->assign('groups', $result);
        }
        $this->display('member/group.html');
    }

    // 会员等级增加
    public function add()
    {
        if ($_POST) {
            
            // 获取数据
            $gcode = post('gcode', 'int') ?: get_auto_code($this->model->getLastGid(), 1);
            $gname = post('gname');
            $description = post('description');
            $status = post('status') ?: 1;
            $lscore = post('lscore', 'int') ?: 0;
            $uscore = post('uscore', 'int') ?: 9999999999;
            
            if (! $gcode) {
                alert_back('等级编号不能为空！');
            }
            
            if (! $gname) {
                alert_back('等级名称不能为空！');
            }
            
            if ($this->model->findGroupCode($gcode)) {
                alert_back('等级编码不能重复！');
            }
            
            // 构建数据
            $data = array(
                'gcode' => $gcode,
                'gname' => $gname,
                'description' => $description,
                'status' => $status,
                'lscore' => $lscore,
                'uscore' => $uscore,
                'create_user' => session('username'),
                'update_user' => session('username')
            );
            
            // 执行会员等级添加
            if ($this->model->addGroup($data)) {
                $this->log('新增会员等级成功！');
                if (! ! $backurl = get('backurl')) {
                    success('新增成功！', base64_decode($backurl));
                } else {
                    success('新增成功！', url('/admin/MemberGroup/index'));
                }
            } else {
                $this->log('新增会员等级失败！');
                error('新增失败！', - 1);
            }
        }
    }

    // 会员等级删除
    public function del()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        
        if ($this->model->findGroupUser($id)) {
            error('会员等级下存在用户，无法直接删除！', - 1);
        }
        
        if ($this->model->delGroup($id)) {
            $this->log('删除会员等级' . $id . '成功！');
            success('删除成功！', - 1);
        } else {
            $this->log('删除会员等级' . $id . '失败！');
            error('删除失败！', - 1);
        }
    }

    // 会员等级修改
    public function mod()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        
        // 单独修改状态
        if (($field = get('field', 'var')) && ! is_null($value = get('value', 'var'))) {
            if ($this->model->modGroup($id, "$field='$value',update_user='" . session('username') . "'")) {
                location(- 1);
            } else {
                alert_back('修改失败！');
            }
        }
        
        // 修改操作
        if ($_POST) {
            
            // 获取数据
            $gcode = post('gcode', 'int') ?: get_auto_code($this->model->getLastGid(), 1);
            $gname = post('gname');
            $description = post('description');
            $status = post('status') ?: 1;
            $lscore = post('lscore', 'int') ?: 0;
            $uscore = post('uscore', 'int') ?: 9999999999;
            
            if (! $gcode) {
                alert_back('等级编号不能为空！');
            }
            
            if (! $gname) {
                alert_back('等级名称不能为空！');
            }
            
            if ($this->model->findGroupCode($gcode, $id)) {
                alert_back('等级编码不能重复！');
            }
            
            // 构建数据
            $data = array(
                'gcode' => $gcode,
                'gname' => $gname,
                'description' => $description,
                'status' => $status,
                'lscore' => $lscore,
                'uscore' => $uscore,
                'update_user' => session('username')
            );
            
            // 执行会员等级修改
            if ($this->model->modGroup($id, $data)) {
                $this->log('修改会员等级成功！');
                if (! ! $backurl = get('backurl')) {
                    success('修改成功！', base64_decode($backurl));
                } else {
                    success('修改成功！', url('/admin/MemberGroup/index'));
                }
            } else {
                $this->log('修改会员等级失败！');
                error('修改失败！', - 1);
            }
        } else {
            // 调取修改内容
            $this->assign('mod', true);
            if (! $result = $this->model->getGroup($id)) {
                error('编辑的内容已经不存在！', - 1);
            }
            
            $this->assign('group', $result);
            $this->display('member/group.html');
        }
    }
}