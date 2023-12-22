<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年5月28日
 *  自定义表单模型类
 */
namespace app\admin\model\content;

use core\basic\Model;

class FormModel extends Model
{

    // 获取自定义表单列表
    public function getList()
    {
        return parent::table('ay_form')->page()->select();
    }

    // 查找自定义表单
    public function findForm($field, $keyword)
    {
        return parent::table('ay_form')->like($field, $keyword)
            ->page()
            ->select();
    }

    // 获取最后一个code
    public function getLastCode()
    {
        return parent::table('ay_form')->order('id DESC')->value('fcode');
    }

    // 获取自定义表单详情
    public function getForm($id)
    {
        return parent::table('ay_form')->where("id=$id")->find();
    }

    // 获取自定义表单详情
    public function getFormByCode($fcode)
    {
        return parent::table('ay_form')->where("fcode='$fcode'")->find();
    }

    // 获取自定义表单表
    public function getFormTable($id)
    {
        return parent::table('ay_form')->where("id=$id")->value('table_name');
    }

    // 获取自定义表单表
    public function getFormCode($id)
    {
        return parent::table('ay_form')->where("id=$id")->value('fcode');
    }

    // 获取自定义表单表
    public function getFormTableByCode($fcode)
    {
        return parent::table('ay_form')->where("fcode='$fcode'")->value('table_name');
    }

    // 添加自定义表单
    public function addForm(array $data)
    {
        return parent::table('ay_form')->autoTime()->insert($data);
    }

    // 删除自定义表单
    public function delForm($id)
    {
        $form = parent::table('ay_form')->field('fcode,form_name')
            ->where("id=$id")
            ->find();
        
        // 删除可能存在的菜单
        if (! ! $rs = parent::table('ay_menu')->like('url', '/Form/index/fcode/' . $form->fcode . '/action/showdata')->find()) {
            parent::table('ay_menu')->where("mcode='" . $rs->mcode . "'")->delete();
            $menu = session('menu_tree');
            foreach ($menu as $key => $value) {
                if (! ! $delkey = result_value_search($rs->mcode, $menu[$key]->son, 'mcode')) {
                    unset($menu[$key]->son[$delkey]);
                }
            }
        }
        $result = parent::table('ay_form')->where("id=$id")->delete(); // 删除表单
        return $result;
    }

    // 修改自定义表单
    public function modForm($id, $data)
    {
        return parent::table('ay_form')->where("id=$id")
            ->autoTime()
            ->update($data);
    }

    // 获取表单字段
    public function getFormFieldByCode($fcode)
    {
        return parent::table('ay_form_field')->where("fcode='$fcode'")
            ->order('sorting ASC,id ASC')
            ->select();
    }

    // 获取字段详情
    public function getFormField($id)
    {
        return parent::table('ay_form_field')->where("id=$id")->find();
    }

    // 检查表单字段
    public function checkFormField($fcode, $name)
    {
        return parent::table('ay_form_field')->where("fcode='$fcode' AND name='$name'")->find();
    }

    // 获取表单字段名称
    public function getFormFieldName($id)
    {
        return parent::table('ay_form_field')->where("id=$id")->value('name');
    }

    // 新增表单字段
    public function addFormField(array $data)
    {
        return parent::table('ay_form_field')->autoTime()->insert($data);
    }

    // 删除表单字段
    public function delFormField($id)
    {
        return parent::table('ay_form_field')->where("id=$id")->delete();
    }

    // 删除表单字段
    public function delFormFieldByCode($fcode)
    {
        return parent::table('ay_form_field')->where("fcode='$fcode'")->delete();
    }

    // 修改表单字段
    public function modFormField($id, $data)
    {
        return parent::table('ay_form_field')->where("id=$id")
            ->autoTime()
            ->update($data);
    }

    // 判断字段是否存在
    public function isExistField($table, $field)
    {
        $fields = parent::tableFields($table);
        if (in_array($field, $fields)) {
            return true;
        } else {
            return false;
        }
    }

    // 获取表单数据
    public function getFormData($table, $page = true)
    {
        return parent::table($table)->page($page)
            ->order('id DESC')
            ->select();
    }

    // 删除自定义表单数据
    public function delFormData($table, $id)
    {
        return parent::table($table)->where("id=$id")->delete();
    }

    // 清空自定义表单数据
    public function clearFormData($table)
    {
        return parent::table($table)->delete();
    }

    // 增加表单数据查看到菜单
    public function addFormMenu($id)
    {
        $form = parent::table('ay_form')->field('fcode,form_name')
            ->where("id=$id")
            ->find();
        
        $menus = session('menu_tree');
        
        // 判断是否已经在菜单中
        if (! ! $menu = parent::table('ay_menu')->like('url', '/Form/index/fcode/' . $form->fcode . '/action/showdata')->find()) {
            if ($form->form_name != $menu->name) {
                // 更新缓存菜单
                parent::table('ay_menu')->where('mcode="' . $menu->mcode . '"')->update('name="' . $form->form_name . '"');
                foreach ($menus as $key => $value) {
                    if ($value->mcode == 'M157') {
                        if (($skey = result_value_search($menu->mcode, $menus[$key]->son, 'mcode')) !== false) {
                            $menus[$key]->son[$skey]->name = $form->form_name;
                        }
                        break;
                    }
                }
            }
            return false;
        }
        
        // 构建数据
        $lastmcode = parent::table('ay_menu')->order('mcode DESC')->value('mcode');
        $mcode = get_auto_code($lastmcode);
        $data = array(
            'mcode' => $mcode,
            'pcode' => 'M157',
            'name' => $form->form_name,
            'url' => '/Form/index/fcode/' . $form->fcode . '/action/showdata',
            'sorting' => 599,
            'status' => 1,
            'shortcut' => 0,
            'ico' => 'fa-plus-square-o',
            'create_user' => session('username'),
            'update_user' => session('username')
        );
        
        // 加入菜单
        foreach ($menus as $key => $value) {
            if ($value->mcode == 'M157') {
                // 未在缓存菜单中才执行添加
                if (result_value_search($mcode, $menus[$key]->son, 'mcode') === false) {
                    $menus[$key]->son[] = array_to_object($data);
                    return parent::table('ay_menu')->autoTime()->insert($data); // 插入到数据库
                }
                break;
            }
        }
    }
}