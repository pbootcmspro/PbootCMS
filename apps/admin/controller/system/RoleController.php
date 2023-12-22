<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年4月3日
 *  角色控制器
 */
namespace app\admin\controller\system;

use core\basic\Controller;
use app\admin\model\system\RoleModel;

class RoleController extends Controller
{

    private $blank;

    private $model;

    public function __construct()
    {
        $this->model = new RoleModel();
    }

    // 角色列表
    public function index()
    {
        $this->assign('list', true);
        $this->assign('roles', $this->model->getList());
        
        // 数据区域选择
        $area_model = model('admin.system.Area');
        $area_tree = $area_model->getSelect();
        $area_checkbox = $this->makeAreaCheckbox($area_tree);
        $this->assign('area_checkbox', $area_checkbox);
        
        // 菜单权限表
        $menu_model = model('admin.system.Menu');
        $menu_level = $menu_model->getMenuLevel();
        $menus = $menu_model->getSelect();
        $menu_list = $this->makeLevelList($menus, $menu_level);
        $this->assign('menu_list', $menu_list);
        
        $this->display('system/role.html');
    }

    // 角色增加
    public function add()
    {
        if ($_POST) {
            // 获取数据
            $rcode = get_auto_code($this->model->getLastCode()); // 自动编码
            $name = post('name');
            $description = post('description');
            $acodes = post('acodes', 'array', false, '角色数据区域', array()); // 区域
            $levels = post('levels', 'array', false, '角色权限', array()); // 权限
            
            if (! $rcode) {
                alert_back('编码不能为空！');
            }
            
            if (! $name) {
                alert_back('角色名不能为空！');
            }
            
            // 检查编码
            if ($this->model->checkRole("rcode='$rcode'")) {
                alert_back('该角色编号已经存在，不能再使用！');
            }
            
            // 构建数据
            $data = array(
                'rcode' => $rcode,
                'name' => $name,
                'description' => $description,
                'create_user' => session('username'),
                'update_user' => session('username')
            );
            
            // 执行添加
            if ($this->model->addRole($data, $acodes, $levels)) {
                $this->log('修改角色' . $rcode . '成功！');
                if (! ! $backurl = get('backurl')) {
                    success('新增成功！', base64_decode($backurl));
                } else {
                    success('新增成功！', url('admin/Role/index'));
                }
            } else {
                $this->log('修改角色' . $rcode . '失败！');
                error('新增失败！', - 1);
            }
        }
    }

    // 生成区域选择，无限制
    private function makeAreaCheckbox($tree, $checkeds = array())
    {
        $list_html = '';
        foreach ($tree as $values) {
            if (in_array($values->acode, $checkeds)) {
                $checked = 'checked="checked"';
            } else {
                $checked = '';
            }
            if (! $values->son) { // 没有子类才显示选择框
                $list_html .= "<input type='checkbox' $checked name='acodes[]' value='{$values->acode}' title='{$values->acode}-{$values->name}'>";
            } else {
                $list_html .= $this->makeAreaCheckbox($values->son, $checkeds);
            }
        }
        return $list_html;
    }

    // 生成无限级菜单权限列表
    private function makeLevelList($menus, $menu_level, $checkeds = array())
    {
        $menu_html = '';
        foreach ($menus as $value) {
            $string = '';
            // 根据是否有子栏目生成图标
            if ($value->son) {
                $ico = "<i class='fa fa-folder-open-o' aria-hidden='true'></i>";
            } else {
                $ico = "<i class='fa fa-folder-o' aria-hidden='true'></i>";
            }
            
            // 选中状态
            if (in_array($value->url, $checkeds)) {
                $checked = 'checked="checked"';
            } else {
                $checked = '';
            }
            
            // 获取模块及控制器路径
            if ($value->url) {
                $pre_url = substr($value->url, 0, get_strpos($value->url, '/', 3) + 1);
            } else {
                error('"' . $value->name . '"菜单地址为空，请核对！');
            }
            
            $string = "<input type='checkbox' $checked class='checkbox' lay-skin='primary'  name='levels[]' value='" . $value->url . "' title='浏览'>";
            $mcode = $value->mcode;
            if (array_key_exists($mcode, $menu_level)) {
                foreach ($menu_level[$mcode] as $key2 => $value2) {
                    $url = $pre_url . $value2->value;
                    if (in_array($url, $checkeds)) {
                        $checked = 'checked="checked"';
                    } else {
                        $checked = '';
                    }
                    $string .= "<input type='checkbox' $checked class='checkbox'lay-skin='primary' name='levels[]' value='$url' title='{$value2->item}'>";
                }
            }
            
            // 生成菜单html
            $menu_html .= "<div class='layui-row'><div class='layui-col-md3 layui-col-lg2' style='margin-top:10px;'>{$this->blank} $ico {$value->name}</div><div class='layui-col-md9'>$string</div></div>";
            
            // 子菜单处理
            if ($value->son) {
                $this->blank .= '　　';
                $menu_html .= $this->makeLevelList($value->son, $menu_level, $checkeds);
            }
        }
        
        // 循环完后回归缩进位置
        $this->blank = substr($this->blank, 0, - 6);
        return $menu_html;
    }

    // 角色删除
    public function del()
    {
        if (! $rcode = get('rcode', 'var')) {
            error('传递的参数值错误！', - 1);
        }
        if ($this->model->delRole($rcode)) {
            $this->log('删除角色' . $rcode . '成功！');
            success('删除成功！', - 1);
        } else {
            $this->log('删除角色' . $rcode . '失败！');
            error('删除失败！', - 1);
        }
    }

    // 角色修改
    public function mod()
    {
        if (! $rcode = get('rcode', 'var')) {
            error('传递的参数值错误！', - 1);
        }
        
        // 修改操作
        if ($_POST) {
            // 获取数据
            $name = post('name');
            $description = post('description');
            $acodes = post('acodes', 'array', false, '角色数据区域', array()); // 区域
            $levels = post('levels', 'array', false, '角色权限', array()); // 权限
            
            if (! $name) {
                alert_back('角色名不能为空！');
            }
            
            // 构建数据
            $data = array(
                'name' => $name,
                'description' => $description,
                'update_user' => session('username')
            );
            
            // 执行修改
            if ($this->model->modRole($rcode, $data, $acodes, $levels)) {
                $this->log('修改角色' . $rcode . '成功！');
                if (! ! $backurl = get('backurl')) {
                    success('修改成功！', base64_decode($backurl));
                } else {
                    success('修改成功！', url('admin/Role/index'));
                }
            } else {
                location(- 1);
            }
        } else {
            $this->assign('mod', true);
            
            // 调取修改内容
            $result = $this->model->getRole($rcode);
            if (! $result) {
                error('编辑的内容已经不存在！', - 1);
            }
            $this->assign('role', $result);
            
            // 数据区域选择
            $area_model = model('admin.system.Area');
            $area_tree = $area_model->getSelect();
            $area_checkbox = $this->makeAreaCheckbox($area_tree, $result->acodes);
            $this->assign('area_checkbox', $area_checkbox);
            
            // 菜单权限表
            $menu_model = model('admin.system.Menu');
            $menu_level = $menu_model->getMenuLevel();
            $menus = $menu_model->getSelect();
            $menu_list = $this->makeLevelList($menus, $menu_level, $result->levels);
            $this->assign('menu_list', $menu_list);
            
            $this->display('system/role.html');
        }
    }
}