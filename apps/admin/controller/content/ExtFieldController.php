<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年3月1日
 *  扩展字段控制器
 */
namespace app\admin\controller\content;

use core\basic\Controller;
use app\admin\model\content\ExtFieldModel;

class ExtFieldController extends Controller
{

    private $model;

    public function __construct()
    {
        $this->model = new ExtFieldModel();
    }

    // 扩展字段列表
    public function index()
    {
        if ((! ! $id = get('id', 'int')) && $result = $this->model->getExtField($id)) {
            $this->assign('more', true);
            $this->assign('extfield', $result);
        } else {
            $this->assign('list', true);
            if (! ! ($field = get('field', 'var')) && ! ! ($keyword = get('keyword', 'vars'))) {
                $result = $this->model->findExtField($field, $keyword);
            } else {
                $result = $this->model->getList();
            }
            
            // 内容模型
            $models = model('admin.content.Model');
            $this->assign('models', $models->getSelect());
            
            $this->assign('extfields', $result);
        }
        $this->display('content/extfield.html');
    }

    ##全局配置-模型字段-扩展字段新增
    public function add()
    {
        if ($_POST) {
            // 获取数据
            $mcode = post('mcode');
            $name = post('name', 'var');
            $type = post('type', 'int');
            if (! ! $value = post('value')) {
                $value = str_replace("\r\n", ",", $value); // 替换回车
                $value = str_replace("，", ",", $value); // 替换中文逗号分割符
            }
            $description = post('description');
            $sorting = post('sorting', 'int');
            
            if (! $mcode) {
                alert_back('内容模型不能为空！');
            }
            
            if (! $name) {
                alert_back('字段名称不能为空！');
            } else {
                $name = "ext_" . $name;
            }
            
            if (! $type) {
                alert_back('字段类型不能为空！');
            }
            
            if (! $description) {
                alert_back('字段描述不能为空！');
            }
            
            // 构建数据
            $data = array(
                'mcode' => $mcode,
                'name' => $name,
                'type' => $type,
                'value' => $value,
                'description' => $description,
                'sorting' => $sorting
            );
            
            // 字段类型及长度
            switch ($type) {
                case '2': // 多行
                    $mysql = 'varchar(1000)';
                    $sqlite = 'TEXT(1000)';
                    break;
                case '7': // 时间日期
                    $mysql = 'datetime';
                    $sqlite = 'TEXT';
                    break;
                case '8': // 编辑器
                    $mysql = 'TEXT';
                    $sqlite = 'TEXT(10000)';
                    break;
                case '10': // 多图
                    $mysql = 'varchar(1000)';
                    $sqlite = 'TEXT(1000)';
                    break;
                default:
                    $mysql = 'varchar(200)';
                    $sqlite = 'TEXT(200)';
            }
            
            $extBack = admin_back_target(url('/admin/ExtField/index'));
            
            // 字段不存在时创建
            if (! $this->model->isExistField($name)) {
                if (get_db_type() == 'sqlite') {
                    $result = $this->model->amd("ALTER TABLE ay_content_ext ADD COLUMN $name $sqlite NULL");
                } else {
                    $result = $this->model->amd("ALTER TABLE ay_content_ext ADD $name $mysql NULL COMMENT '$description'");
                }
            } elseif ($this->model->checkExtField($name)) { // 字段存在且已使用则 报错
                alert_back('字段已经存在，不能重复添加！');
            }
            
            // 执行扩展字段记录添加
            if ($this->model->addExtField($data)) {
                $this->log('新增扩展字段成功！');
                success('新增成功！', $extBack);
            } else {
                $this->log('新增扩展字段失败！');
                alert_back('新增失败！');
            }
        }
    }

    // 扩展字段删除
    public function del()
    {
        $back = admin_stay_target(url('/admin/ExtField/index'));
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请使用正确方式提交！', url('/admin/ExtField/index'));
        }
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', url('/admin/ExtField/index'));
        }
        
        $name = $this->model->getExtFieldName($id);
        if ($this->model->delExtField($id)) {
            // mysql数据库执行字段删除，sqlite暂时不支持
            if (! ! $name) {
                if (get_db_type() == 'mysql') {
                    $result = $this->model->amd("ALTER TABLE ay_content_ext DROP COLUMN $name");
                    //删除索引(如果有)
                    $contentExt = $this->model->checkExtIndex();
                    foreach ($contentExt as $items){
                        if($items[2] == "ay_content_{$name}_index"){
                            $this->model->amd("ALTER table ay_content_ext drop key ay_content_{$name}_index");
                        }
                    }
                }
            }
            $this->log('删除扩展字段' . $id . '成功！');
            success('删除成功！', url('/admin/ExtField/index'));
        } else {
            $this->log('删除扩展字段' . $id . '失败！');
            error('删除失败！', url('/admin/ExtField/index'));
        }
    }

    // 扩展字段修改
    public function mod()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', url('/admin/ExtField/index'));
        }
        
        // 单独修改状态
        $posted = admin_posted_field(array('status'), url('/admin/ExtField/index'));
        if ($posted) {
            if (admin_saved($this->model->modExtField($id, $posted[0] . "='" . $posted[1] . "',update_user='" . session('username') . "'"), true)) {
                success('修改成功！', url('/admin/ExtField/index'));
            } else {
                error('修改失败！', url('/admin/ExtField/index'));
            }
        }
        
        // 修改操作
        if ($_POST) {
            
            // 获取数据
            $mcode = post('mcode');
            $type = post('type');
            if (! ! $value = post('value')) {
                $value = str_replace("\r\n", ",", $value); // 替换回车
                $value = str_replace("，", ",", $value); // 替换中文逗号分割符
            }
            $description = post('description');
            $sorting = post('sorting', 'int');
            
            if (! $mcode) {
                alert_back('内容模型不能为空！');
            }
            
            if (! $description) {
                alert_back('字段描述不能为空！');
            }
            
            // 构建数据
            $data = array(
                'mcode' => $mcode,
                'type' => $type,
                'value' => $value,
                'description' => $description,
                'sorting' => $sorting
            );
            
            // 执行修改
            $back = admin_back_target(url('/admin/ExtField/index'));
            if ($this->model->modExtField($id, $data)) {
                $this->log('修改扩展字段' . $id . '成功！');
                success('修改成功！', $back);
            } else {
                if ($this->model->getExtField($id)) {
                    success('修改成功！', $back);
                } else {
                    error('编辑的内容已经不存在！', url('/admin/ExtField/index'));
                }
            }
        } else {
            
            // 调取修改内容
            $this->assign('mod', true);
            if (! $result = $this->model->getExtField($id)) {
                error('编辑的内容已经不存在！', url('/admin/ExtField/index'));
            }
            
            // 内容模型
            $models = model('admin.content.Model');
            $this->assign('models', $models->getSelect());
            
            $this->assign('extfield', $result);
            $this->display('content/extfield.html');
        }
    }
}