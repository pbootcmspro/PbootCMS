<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年4月3日
 *  菜单控制器 
 */
namespace app\admin\controller\system;

use core\basic\Controller;
use app\admin\model\system\MenuModel;

class MenuController extends Controller
{

    private $count;

    private $blank;

    private $outData = array();

    private $model;

    public function __construct()
    {
        $this->model = new MenuModel();
    }

    // 菜单列表
    public function index()
    {
        $this->assign('list', true);
        $menus = $this->model->getList();
        $this->assign('menus', $this->makeMenuList($menus));
        
        // 菜单下拉列表
        $menus = $this->model->getSelect();
        $this->assign('menu_select', $this->makeMenuSelect($menus));
        
        // 获取菜单按钮
        $this->assign('actions', get_type('T101'));
        
        $this->display('system/menu.html');
    }

    // 生成无限级菜单管理列表
    private function makeMenuList($tree)
    {
        // 循环生成
        foreach ($tree as $value) {
            $this->count ++;
            $this->outData[$this->count] = new \stdClass();
            $this->outData[$this->count]->id = $value->id;
            $this->outData[$this->count]->blank = $this->blank;
            $this->outData[$this->count]->name = $value->name;
            $this->outData[$this->count]->mcode = $value->mcode;
            $this->outData[$this->count]->pcode = $value->pcode;
            $this->outData[$this->count]->sorting = $value->sorting;
            $this->outData[$this->count]->url = $value->url;
            $this->outData[$this->count]->status = $value->status;
            $this->outData[$this->count]->shortcut = $value->shortcut;
            $this->outData[$this->count]->ico = $value->ico;
            $this->outData[$this->count]->create_user = $value->create_user;
            $this->outData[$this->count]->update_user = $value->update_user;
            $this->outData[$this->count]->create_time = $value->create_time;
            $this->outData[$this->count]->update_time = $value->update_time;
            
            if ($value->son) {
                $this->outData[$this->count]->son = true;
            } else {
                $this->outData[$this->count]->son = false;
            }
            // 子菜单处理
            if ($value->son) {
                $this->blank .= '　　';
                $this->makeMenuList($value->son);
            }
        }
        // 循环完后回归缩进位置
        $this->blank = substr($this->blank, 0, - 6);
        return $this->outData;
    }

    // 菜单增加
    public function add()
    {
        if ($_POST) {
            // 获取数据
            $mcode = get_auto_code($this->model->getLastCode()); // 自动编码
            $pcode = post('pcode', 'var');
            $name = post('name');
            $url = post('url');
            $sorting = post('sorting', 'int');
            $status = post('status', 'int');
            $shortcut = post('shortcut', 'int');
            $ico = post('ico');
            $actions = post('actions', 'array', false, '菜单按钮', array());
            
            if (! $mcode) {
                alert_back('编码不能为空！');
            }
            if (! $pcode) {
                $pcode = 0; // 父编码默认为0
            }
            if (! $name) {
                alert_back('菜单名称不能为空！');
            }
            
            if ($this->model->checkMenu("mcode='$mcode'")) {
                alert_back('该菜单编号已经存在，不能再使用！');
            }
            
            // 菜单地址自动填充
            if (! $url) {
                $url = '/' . M . '/' . $mcode . '/index';
            }
            
            // 构建数据
            $data = array(
                'mcode' => $mcode,
                'pcode' => $pcode,
                'name' => $name,
                'url' => $url,
                'sorting' => $sorting,
                'status' => $status,
                'shortcut' => $shortcut,
                'ico' => $ico,
                'create_user' => session('username'),
                'update_user' => session('username')
            );
            
            // 执行添加
            $menuBack = admin_back_target(url('admin/Menu/index'));
            if ($this->model->addMenu($data, $actions)) {
                $this->log('新增菜单' . $mcode . '成功！');
                success('新增成功！', $menuBack);
            } else {
                $this->log('新增菜单' . $mcode . '失败！');
                alert_back('新增失败！');
            }
        }
    }

    // 生成菜单下拉列表
    private function makeMenuSelect($tree, $selectid = null)
    {
        // 初始化
        $menu_html = '';
        // 循环生成
        foreach ($tree as $value) {
            // 默认选择项
            if ($selectid == $value->mcode) {
                $select = "selected='selected'";
            } else {
                $select = '';
            }
            if (get('mcode') != $value->mcode) { // 不显示本身，避免出现自身为自己的父节点
                $menu_html .= "<option value='{$value->mcode}' $select />{$this->blank}{$value->mcode} {$value->name}";
            }
            // 子菜单处理
            if ($value->son) {
                $this->blank .= '　　';
                $menu_html .= $this->makeMenuSelect($value->son, $selectid);
            }
        }
        // 循环完后回归位置
        $this->blank = substr($this->blank, 0, - 6);
        return $menu_html;
    }

    // 菜单删除
    public function del()
    {
        $back = admin_stay_target(url('admin/Menu/index'));
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请使用正确方式提交！', $back);
        }
        if (! $mcode = get('mcode', 'var')) {
            error('传递的参数值错误！', $back);
        }
        if ($this->model->delMenu($mcode)) {
            $this->log('删除菜单' . $mcode . '成功！');
            success('删除成功！', $back);
        } else {
            $this->log('删除菜单' . $mcode . '失败！');
            error('删除失败！', $back);
        }
    }

    // 菜单修改
    public function mod()
    {
        if (! $mcode = get('mcode', 'var')) {
            error('传递的参数值错误！', url('admin/Menu/index'));
        }
        
        // 单独修改状态
        $posted = admin_posted_field(array('status'), url('admin/Menu/index'));
        if ($posted) {
            if (admin_saved($this->model->modMenu($mcode, $posted[0] . "='" . $posted[1] . "',update_user='" . session('username') . "'"), true)) {
                $this->log('修改菜单' . $mcode . '状态' . $posted[1] . '成功！');
                success('修改成功！', url('admin/Menu/index'));
            } else {
                $this->log('修改菜单' . $mcode . '状态' . $posted[1] . '失败！');
                error('修改失败！', url('admin/Menu/index'));
            }
        }
        
        // 修改操作
        if ($_POST) {
            // 获取数据
            $pcode = post('pcode', 'var');
            $name = post('name');
            $sorting = post('sorting', 'int');
            $url = post('url');
            $status = post('status', 'int');
            $shortcut = post('shortcut', 'int');
            $ico = post('ico');
            $actions = post('actions', 'array', false, '菜单按钮', array());
            
            if (! $pcode) {
                $pcode = 0; // 父编码默认为0
            }
            if (! $name) {
                alert_back('菜单名称不能为空！');
            }
            // 菜单地址自动填充
            if (! $url) {
                $url = '/' . M . '/' . $mcode . '/index';
            }
            
            // 构建数据
            $data = array(
                'pcode' => $pcode,
                'name' => $name,
                'sorting' => $sorting,
                'url' => $url,
                'status' => $status,
                'shortcut' => $shortcut,
                'ico' => $ico,
                'update_user' => session('username')
            );
            
            // 执行修改
            if ($this->model->modMenu($mcode, $data, $actions)) {
                $this->log('修改菜单' . $mcode . '成功！');
                success('修改成功！', admin_back_target(url('admin/Menu/index')));
            } else {
                if ($this->model->getMenu($mcode)) {
                    success('修改成功！', url('admin/Menu/index'));
                } else {
                    error('编辑的内容已经不存在！', url('admin/Menu/index'));
                }
            }
        } else { // 调取修改内容
            
            $this->assign('mod', true);
            
            $result = $this->model->getMenu($mcode);
            if (! $result) {
                error('编辑的内容已经不存在！', url('admin/Menu/index'));
            }
            $this->assign('menu', $result); // 菜单信息
                                            
            // 获取菜单按钮组
            $this->assign('actions', get_type('T101'));
            
            // 菜单下拉列表
            $menus = $this->model->getSelect();
            $this->assign('menu_select', $this->makeMenuSelect($menus, $result->pcode));
            
            // 显示
            $this->display('system/menu.html');
        }
    }
}