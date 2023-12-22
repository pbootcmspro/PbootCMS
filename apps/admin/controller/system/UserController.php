<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年4月3日
 *  用户控制器
 */
namespace app\admin\controller\system;

use core\basic\Controller;
use app\admin\model\system\UserModel;

class UserController extends Controller
{

    private $model;

    public function __construct()
    {
        $this->model = new UserModel();
    }

    // 用户列表
    public function index()
    {
        $this->assign('list', true);
        if ((! ! $field = get('field', 'var')) && (! ! $keyword = get('keyword', 'vars'))) {
            $result = $this->model->findUser($field, $keyword);
        } else {
            $result = $this->model->getList();
        }
        $this->assign('users', $result);
        // 角色列表
        $role_model = model('admin.system.Role');
        $this->assign('roles', $role_model->getSelect());
        
        $this->display('system/user.html');
    }

    // 用户新增
    public function add()
    {
        if ($_POST) {
            // 获取数据
            $ucode = get_auto_code($this->model->getLastCode());
            $username = post('username');
            $realname = post('realname');
            $password = post('password');
            $rpassword = post('rpassword');
            $status = post('status', 'int');
            $roles = post('roles', 'array', true, '用户角色', array()); // 用户角色
            
            if (! $ucode) {
                alert_back('编码不能为空！');
            }
            if (! $username) {
                alert_back('用户名不能为空！');
            }
            if (! $realname) {
                alert_back('真实名字不能为空！');
            }
            if (! $password) {
                alert_back('密码不能为空！');
            }
            if (! $rpassword) {
                alert_back('确认密码不能为空！');
            }
            if ($password != $rpassword) {
                alert_back('确认密码不正确！');
            }
            
            if (! preg_match('/^[\x{4e00}-\x{9fa5}\w\-\.@]+$/u', $username)) {
                alert_back('用户名含有不允许的特殊字符！');
            }
            
            // 检查编码重复
            if ($this->model->checkUser("ucode='$ucode'")) {
                alert_back('该用户编号已经存在，不能再使用！');
            }
            
            // 检查用户名重复
            if ($this->model->checkUser("username='$username'")) {
                alert_back('该用户名已经存在，不能再使用！');
            }
            
            // 构建数据
            $data = array(
                'ucode' => $ucode,
                'username' => $username,
                'realname' => $realname,
                'password' => encrypt_string($password),
                'status' => $status,
                'login_count' => 0,
                'last_login_ip' => 0,
                'create_user' => session('username'),
                'update_user' => session('username'),
                'create_time' => get_datetime(),
                'update_time' => '0000-00-00 00:00:00'
            );
            
            // 执行添加
            if ($this->model->addUser($data, $roles)) {
                $this->log('新增用户' . $ucode . '成功！');
                if (! ! $backurl = get('backurl')) {
                    success('新增成功！', base64_decode($backurl));
                } else {
                    success('新增成功！', url('/admin/User/index'));
                }
            } else {
                $this->log('新增用户' . $ucode . '失败！');
                error('新增失败', - 1);
            }
        }
    }

    // 用户删除
    public function del()
    {
        if (! $ucode = get('ucode', 'var')) {
            error('传递的参数值错误！', - 1);
        }
        
        if ($ucode == '10001') {
            error('内置管理员不允许删除！', - 1);
        }
        
        if ($this->model->delUser($ucode)) {
            $this->log('删除用户' . $ucode . '成功！');
            success('删除成功！', - 1);
        } else {
            $this->log('删除用户' . $ucode . '失败！');
            error('删除失败', - 1);
        }
    }

    // 用户修改
    public function mod()
    {
        if (! $ucode = get('ucode', 'var')) {
            error('传递的参数值错误！', - 1);
        }
        
        if ($ucode == '10001') {
            error('内置管理员不允许此操作！', - 1);
        }
        
        // 单独修改状态
        if (($field = get('field', 'var')) && ! is_null($value = get('value', 'var'))) {
            if ($this->model->modUser($ucode, "$field='$value',update_user='" . session('username') . "'")) {
                location(- 1);
            } else {
                alert_back('修改失败！');
            }
        }
        
        // 修改操作
        if ($_POST) {
            // 获取数据
            $username = post('username');
            $realname = post('realname');
            $password = post('password');
            $rpassword = post('rpassword');
            $status = post('status', 'int');
            $roles = post('roles', 'array', true, '用户角色', array()); // 用户角色
            
            if (! $username) {
                alert_back('用户名不能为空！');
            }
            if (! $realname) {
                alert_back('真实名字不能为空！');
            }
            
            if (! preg_match('/^[\x{4e00}-\x{9fa5}\w\-\.@]+$/u', $username)) {
                alert_back('用户名含有不允许的特殊字符！');
            }
            
            // 检查用户名重复
            if ($this->model->checkUser("username='$username' AND ucode<>'$ucode'")) {
                alert_back('该用户名已经存在，不能再使用！');
            }
            
            // 构建数据
            $data = array(
                'username' => $username,
                'realname' => $realname,
                'status' => $status,
                'update_user' => session('username')
            );
            
            if ($password) {
                if (! $rpassword) {
                    alert_back('确认密码不能为空！');
                }
                if ($password != $rpassword) {
                    alert_back('确认密码不正确！');
                }
                $data['password'] = encrypt_string($password);
            }
            
            // 执行添加
            if ($this->model->modUser($ucode, $data, $roles)) {
                $this->log('修改用户' . $ucode . '成功！');
                if (! ! $backurl = get('backurl')) {
                    success('修改成功！', base64_decode($backurl));
                } else {
                    success('修改成功！', url('/admin/User/index'));
                }
            } else {
                location(- 1);
            }
        } else { // 调取修改内容
            $this->assign('mod', true);
            
            $result = $this->model->getUser($ucode);
            if (! $result) {
                error('编辑的内容已经不存在！', - 1);
            }
            $this->assign('user', $result);
            
            // 角色列表
            $role_model = model('admin.system.Role');
            $this->assign('roles', $role_model->getSelect());
            $this->display('system/user.html');
        }
    }
}