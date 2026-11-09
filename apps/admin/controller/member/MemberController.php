<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2019年10月05日
 *  会员控制器
 */
namespace app\admin\controller\member;

use core\basic\Controller;
use app\admin\model\member\MemberModel;

class MemberController extends Controller
{

    private $model;

    public function __construct()
    {
        $this->model = new MemberModel();
    }

    // 会员列表
    public function index()
    {
        if ((! ! $id = get('id', 'int')) && $result = $this->model->getMember($id)) {
            $this->assign('more', true);
            $this->assign('fields', $this->model->getFields());
            $this->assign('member', $result);
        } else {
            $this->assign('list', true);
            if (! ! ($field = get('field', 'var')) && ! ! ($keyword = get('keyword', 'vars'))) {
                $result = $this->model->findMember($field, $keyword);
            } else {
                $result = $this->model->getList();
            }
            
            // 会员等级
            $this->assign('groups', model('admin.member.MemberGroup')->getSelect());
            $this->assign('members', $result);
        }
        $this->display('member/member.html');
    }

    // 会员增加
    public function add()
    {
        if ($_POST) {
            // 获取数据
            $ucode = get_auto_code($this->model->getLastCode(), 1);
            $username = post('username');
            $useremail = post('useremail');
            $usermobile = post('usermobile');
            $nickname = post('nickname');
            $password = post('password');
            $headpic = post('headpic');
            $status = post('status') ?: 1;
            $gid = post('gid');
            $score = post('score');
            $register_time = get_datetime();
            
            if (! $username) {
                alert_back('用户账号不能为空！');
            }
            
            if (! preg_match('/^[\w\@\.]+$/', $username)) {
                alert_back('用户账号含有不允许的特殊字符！');
            }
            
            if ($useremail && ! preg_match('/^[\w]+@[\w\.]+\.[a-zA-Z]+$/', $useremail)) {
                alert_back('邮箱账号格式不正确！');
            }
            
            if ($usermobile && ! preg_match('/^1[0-9]{10}$/', $usermobile)) {
                alert_back('手机号码格式不正确！');
            }
            
            if ($username && $this->model->checkMember($username)) {
                alert_back('用户名已经存在！');
            }
            
            if ($useremail && $this->model->checkMember($useremail)) {
                alert_back('邮箱账号已经存在！');
            }
            
            if ($usermobile && $this->model->checkMember($usermobile)) {
                alert_back('手机号码已经存在！');
            }
            
            if (! $password) {
                alert_back('密码不能为空！');
            } else {
                $password = md5(md5($password));
            }
            
            // 构建数据
            $data = array(
                'ucode' => $ucode,
                'username' => $username,
                'useremail' => $useremail,
                'usermobile' => $usermobile,
                'nickname' => $nickname,
                'password' => $password,
                'headpic' => $headpic,
                'status' => $status,
                'gid' => $gid,
                'wxid' => '',
                'qqid' => '',
                'wbid' => '',
                'activation' => 1,
                'score' => $score,
                'register_time' => $register_time,
                'login_count' => 0,
                'last_login_ip' => 0,
                'last_login_time' => 0
            );
            
            // 执行添加
            $memberBack = admin_back_target(url('/admin/Member/index'));
            if ($this->model->addMember($data)) {
                $this->log('新增会员成功！');
                success('新增成功！', $memberBack);
            } else {
                $this->log('新增会员失败！');
                alert_back('新增失败！');
            }
        }
    }

    // 会员删除
    public function del()
    {
        $memberUrl = url('/admin/Member/index');
        $back = admin_stay_target($memberUrl);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请使用正确方式提交！', $memberUrl);
        }
        
        // 执行批量删除
        if (! ! $list = post('list')) {
            if ($this->model->delMemberList($list)) {
                $this->log('批量删除会员成功！');
                success('删除成功！', $back);
            } else {
                $this->log('批量删除会员失败！');
                error('删除失败！', $back);
            }
        }
        
        if (! $id = get('id', 'int')) {
            error('请选择要删除的会员！', $back);
        }
        
        if ($this->model->delMember($id)) {
            $this->log('删除会员' . $id . '成功！');
            success('删除成功！', $back);
        } else {
            $this->log('删除会员' . $id . '失败！');
            error('删除失败！', $back);
        }
    }

    // 会员修改
    public function mod()
    {
        $memberUrl = url('/admin/Member/index');
        if (! ! $submit = post('submit')) {
            $back = admin_stay_target($memberUrl);
            switch ($submit) {
                case 'verify1':
                    $list = post('list');
                    if (! $list) {
                        alert_back('请选择要操作的会员！');
                    }
                    if (admin_saved($this->model->modMemberList($list, array(
                        'status' => 1
                    )), true)) {
                        $this->log('会员批量启用成功！');
                        success('启用成功！', $back);
                    } else {
                        error('启用失败！', $back);
                    }
                    break;
                case 'verify0':
                    $list = post('list');
                    if (! $list) {
                        alert_back('请选择要操作的会员！');
                    }
                    if (admin_saved($this->model->modMemberList($list, array(
                        'status' => 0
                    )), true)) {
                        $this->log('会员批量禁用成功！');
                        success('禁用成功！', $back);
                    } else {
                        error('禁用失败！', $back);
                    }
                    break;
            }
        }
        
        if (! isset($_GET['id']) || ! is_string($_GET['id']) || ! ($id = get('id', 'int'))) {
            error('传递的参数值错误！', $memberUrl);
        }
        
        // 单独修改状态（仅允许 status=0/1）
        $posted = admin_posted_field(array('status'), $memberUrl);
        if ($posted) {
            if ($posted[1] !== '0' && $posted[1] !== '1') {
                error('传递的参数值错误！', $memberUrl);
            }
            if (admin_saved($this->model->modMemberStatus($id, $posted[1]), true)) {
                success('修改成功！', $memberUrl);
            } else {
                error('修改失败！', $memberUrl);
            }
        }
        
        // 修改操作
        if ($_POST) {
            
            // 获取数据
            $username = post('username');
            $useremail = post('useremail');
            $usermobile = post('usermobile');
            $nickname = post('nickname');
            $password = post('password');
            $headpic = post('headpic');
            $status = post('status') ?: 1;
            $gid = post('gid');
            $score = post('score');
            
            if (! $username) {
                alert_back('用户账号不能为空！');
            }
            
            if (! preg_match('/^[\w\@\.]+$/', $username)) {
                alert_back('用户账号含有不允许的特殊字符！');
            }
            
            if ($useremail && ! preg_match('/^[\w]+@[\w\.]+\.[a-zA-Z]+$/', $useremail)) {
                alert_back('邮箱账号格式不正确！');
            }
            
            if ($usermobile && ! preg_match('/^1[0-9]{10}$/', $usermobile)) {
                alert_back('手机号码格式不正确！');
            }
            
            if ($username && $this->model->checkMember($username, $id)) {
                alert_back('用户名已经存在！');
            }
            
            if ($useremail && $this->model->checkMember($useremail, $id)) {
                alert_back('邮箱账号已经存在！');
            }
            
            if ($usermobile && $this->model->checkMember($usermobile, $id)) {
                alert_back('手机号码已经存在！');
            }
            
            // 构建数据
            $data = array(
                'username' => $username,
                'useremail' => $useremail,
                'usermobile' => $usermobile,
                'nickname' => $nickname,
                'headpic' => $headpic,
                'status' => $status,
                'gid' => $gid,
                'score' => $score
            );
            
            if ($password) {
                $data['password'] = md5(md5($password));
            }
            
            // 执行添加
            $back = admin_back_target($memberUrl);
            if ($this->model->modMember($id, $data)) {
                $this->log('修改会员成功！');
                success('修改成功！', $back);
            } else {
                if ($this->model->getMember($id)) {
                    success('修改成功！', $back);
                } else {
                    $this->log('修改会员失败！');
                    error('编辑的内容已经不存在！', $memberUrl);
                }
            }
        } else {
            // 调取修改内容
            $this->assign('mod', true);
            if (! $result = $this->model->getMember($id)) {
                error('编辑的内容已经不存在！', $memberUrl);
            }
            // 会员等级
            $this->assign('groups', model('admin.member.MemberGroup')->getSelect());
            $this->assign('member', $result);
            $this->display('member/member.html');
        }
    }
}
