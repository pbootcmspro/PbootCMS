<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年4月3日
 *  区域控制器
 */
namespace app\admin\controller\system;

use core\basic\Controller;
use app\admin\model\system\AreaModel;

class AreaController extends Controller
{

    private $count;

    private $blank;

    private $outData = array();

    private $model;

    public function __construct()
    {
        $this->model = new AreaModel();
    }

    // 区域列表
    public function index()
    {
        $this->assign('list', true);
        $area_tree = $this->model->getList();
        $areas = $this->makeAreaList($area_tree);
        $this->assign('areas', $areas);
        
        // 区域下拉表
        $area_tree = $this->model->getSelect();
        $area_select = $this->makeAreaSelect($area_tree);
        $this->assign('area_select', $area_select);
        
        $this->display('system/area.html');
    }

    // 生成无限级区域列表
    private function makeAreaList($tree)
    {
        // 循环生成
        foreach ($tree as $value) {
            $this->count ++;
            $this->outData[$this->count] = new \stdClass();
            $this->outData[$this->count]->id = $value->id;
            $this->outData[$this->count]->blank = $this->blank;
            $this->outData[$this->count]->name = $value->name;
            $this->outData[$this->count]->domain = $value->domain;
            $this->outData[$this->count]->acode = $value->acode;
            $this->outData[$this->count]->pcode = $value->pcode;
            $this->outData[$this->count]->is_default = $value->is_default;
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
                $this->makeAreaList($value->son);
            }
        }
        
        // 循环完后回归缩进位置
        $this->blank = substr($this->blank, 6);
        return $this->outData;
    }

    // 区域增加
    public function add()
    {
        if ($_POST) {
            // 获取数据
            $acode = post('acode', 'var');
            $pcode = post('pcode', 'var');
            $name = post('name');
            $domain = post('domain');
            $is_default = post('is_default');
            
            if (! $acode) {
                alert_back('编码不能为空！');
            }
            
            if (! $pcode) { // 父编码默认为0
                $pcode = 0;
            }
            
            if (! $name) {
                alert_back('区域名称不能为空！');
            }
            
            if ($domain) {
                $reg = '{^(https://|http://)?([\w\-.]+)([\/]+)?$}';
                if (preg_match($reg, $domain)) {
                    $domain = preg_replace($reg, '$2', $domain);
                } else {
                    alert_back('要绑定的域名输入有错！');
                }
                
                // 检查绑定
                if ($this->model->checkArea("domain='$domain'")) {
                    alert_back('该域名已经绑定其他区域，不能再使用！');
                }
            }
            
            // 检查编码
            if ($this->model->checkArea("acode='$acode'")) {
                alert_back('该区域编号已经存在，不能再使用！');
            }
            
            // 构建数据
            $data = array(
                'acode' => $acode,
                'pcode' => $pcode,
                'name' => $name,
                'domain' => $domain,
                'is_default' => $is_default,
                'create_user' => session('username'),
                'update_user' => session('username')
            );
            
            // 执行添加
            if ($this->model->addArea($data)) {
                if (session('ucode') == '10001') {
                    $acodes = session('acodes');
                    $acodes[] = $acode;
                    session('acodes', $acodes); // 更新管理员管理区域
                    $model = model('Index');
                    $areas = $model->getAreas();
                    session('area_map', get_mapping($areas, 'name', 'acode')); // 更新区域代码名称映射表
                    session('area_tree', $model->getUserAreaTree($areas, 0, 'acode', 'pcode', 'son', $acodes)); // 更新当前用户的区域树
                }
                $this->log('新增数据区域' . $acode . '成功！');
                path_delete(RUN_PATH . '/config'); // 清理缓存的配置文件
                if (! ! $backurl = get('backurl')) {
                    success('新增成功！', base64_decode($backurl));
                } else {
                    success('新增成功！', url('/admin/Area/index'));
                }
            } else {
                $this->log('新增数据区域' . $acode . '失败！');
                error('新增失败！', - 1);
            }
        }
    }

    // 生成区域选择
    private function makeAreaSelect($tree, $selectid = null)
    {
        $list_html = '';
        foreach ($tree as $value) {
            // 默认选择项
            if ($selectid == $value->acode) {
                $select = "selected='selected'";
            } else {
                $select = '';
            }
            if (get('acode') != $value->acode) { // 不显示本身，避免出现自身为自己的父节点
                $list_html .= "<option value='{$value->acode}' $select>{$this->blank}{$value->acode} {$value->name}</option>";
            }
            // 子菜单处理
            if ($value->son) {
                $this->blank .= '　　';
                $list_html .= $this->makeAreaSelect($value->son, $selectid);
            }
        }
        // 循环完后回归位置
        $this->blank = substr($this->blank, 0, - 6);
        return $list_html;
    }

    // 区域删除
    public function del()
    {
        if (! $acode = get('acode', 'var')) {
            error('传递的参数值错误！', - 1);
        }
        
        if ($acode == 'cn') {
            error('系统内置区域不允许删除！', - 1);
        }
        
        if ($this->model->delArea($acode)) {
            path_delete(RUN_PATH . '/config'); // 清理缓存的配置文件
            $this->log('删除数据区域' . $acode . '成功！');
            session_unset();
            success('删除成功,请重新登录', url('/admin/Index/index'));
        } else {
            $this->log('删除数据区域' . $acode . '失败！');
            error('删除失败，请核对是否为默认区域！', - 1);
        }
    }

    // 区域修改
    public function mod()
    {
        if (! $acode = get('acode', 'var')) {
            error('传递的参数值错误！', - 1);
        }
        
        // 修改操作
        if ($_POST) {
            // 获取数据
            $acode_new = post('acode', 'var');
            $pcode = post('pcode', 'var');
            $name = post('name');
            $domain = post('domain');
            $is_default = post('is_default');
            
            if (! $acode_new) {
                alert_back('编码不能为空！');
            }
            
            if (! $pcode) { // 父编码默认为0
                $pcode = 0;
            }
            
            if (! $name) {
                alert_back('区域名称不能为空！');
            }
            
            if ($domain) {
                $reg = '{^(https://|http://)?([\w\-.]+)([\/]+)?$}';
                if (preg_match($reg, $domain)) {
                    $domain = preg_replace($reg, '$2', $domain);
                } else {
                    alert_back('要绑定的域名输入有错！');
                }
                
                // 检查绑定
                if ($this->model->checkArea("domain='$domain' AND acode<>'$acode'")) {
                    alert_back('该域名已经绑定其他区域，不能再使用！');
                }
            }
            
            // 检查编码
            if ($this->model->checkArea("acode='$acode_new' AND acode<>'$acode'")) {
                alert_back('该区域编号已经存在，不能再使用！');
            }
            
            // 构建数据
            $data = array(
                'acode' => $acode_new,
                'pcode' => $pcode,
                'name' => $name,
                'domain' => $domain,
                'is_default' => $is_default,
                'update_user' => session('username')
            );
            
            // 执行添加
            if ($this->model->modArea($acode, $data)) {
                if (session('ucode') == '10001') {
                    $acodes = session('acodes');
                    $acodes[] = $acode_new;
                    session('acodes', $acodes); // 更新管理员管理区域
                    $model = model('Index');
                    $areas = $model->getAreas();
                    session('area_map', get_mapping($areas, 'name', 'acode')); // 更新区域代码名称映射表
                    session('area_tree', $model->getUserAreaTree($areas, 0, 'acode', 'pcode', 'son', $acodes)); // 更新当前用户的区域树
                }
                $this->log('修改数据区域' . $acode . '成功！');
                path_delete(RUN_PATH . '/config'); // 清理缓存的配置文件
                if (! ! $backurl = get('backurl')) {
                    success('修改成功！', base64_decode($backurl));
                } else {
                    success('修改成功！', url('/admin/Area/index'));
                }
            } else {
                location(- 1);
            }
        } else { // 调取修改内容
            $this->assign('mod', true);
            
            $area = $this->model->getArea($acode);
            if (! $area) {
                error('编辑的内容已经不存在！', - 1);
            }
            $this->assign('area', $area);
            
            // 父编码下拉选择
            $area_tree = $this->model->getSelect();
            $area_select = $this->makeAreaSelect($area_tree, $area->pcode);
            $this->assign('area_select', $area_select);
            
            $this->display('system/area.html');
        }
    }
}