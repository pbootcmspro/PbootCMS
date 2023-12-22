<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2019年07月12日
 *  内链链接控制器
 */
namespace app\admin\controller\content;

use core\basic\Controller;
use app\admin\model\content\TagsModel;

class TagsController extends Controller
{

    private $model;

    public function __construct()
    {
        $this->model = new TagsModel();
    }

    // 文章内链列表
    public function index()
    {
        if ((! ! $id = get('id', 'int')) && $result = $this->model->getTags($id)) {
            $this->assign('more', true);
            $this->assign('tags', $result);
        } else {
            $this->assign('list', true);
            if (! ! ($field = get('field', 'var')) && ! ! ($keyword = get('keyword', 'vars'))) {
                $result = $this->model->findTags($field, $keyword);
            } else {
                $result = $this->model->getList();
            }
            $this->assign('tags', $result);
        }
        $this->display('content/tags.html');
    }

    // 文章内链增加
    public function add()
    {
        if ($_POST) {
            // 获取数据
            $name = post('name');
            $link = post('link');
            
            if (! $name) {
                alert_back('名称不能为空！');
            }
            
            if (! $link) {
                alert_back('链接不能为空！');
            }
            
            // 构建数据
            $data = array(
                'acode' => session('acode'),
                'name' => $name,
                'link' => $link,
                'create_user' => session('username'),
                'update_user' => session('username')
            );
            
            // 执行添加
            if ($this->model->addTags($data)) {
                $this->log('新增文章内链成功！');
                if (! ! $backurl = get('backurl')) {
                    success('新增成功！', base64_decode($backurl));
                } else {
                    success('新增成功！', url('/admin/Tags/index'));
                }
            } else {
                $this->log('新增文章内链失败！');
                error('新增失败！', - 1);
            }
        }
    }

    // 文章内链删除
    public function del()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        
        if ($this->model->delTags($id)) {
            $this->log('删除文章内链' . $id . '成功！');
            success('删除成功！', - 1);
        } else {
            $this->log('删除文章内链' . $id . '失败！');
            error('删除失败！', - 1);
        }
    }

    // 文章内链修改
    public function mod()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        
        // 单独修改状态
        if (($field = get('field', 'var')) && ! is_null($value = get('value', 'var'))) {
            if ($this->model->modTags($id, "$field='$value',update_user='" . session('username') . "'")) {
                location(- 1);
            } else {
                alert_back('修改失败！');
            }
        }
        
        // 修改操作
        if ($_POST) {
            
            // 获取数据
            $name = post('name');
            $link = post('link');
            
            if (! $name) {
                alert_back('名称不能为空！');
            }
            
            if (! $link) {
                alert_back('链接不能为空！');
            }
            
            // 构建数据
            $data = array(
                'name' => $name,
                'link' => $link,
                'update_user' => session('username')
            );
            
            // 执行添加
            if ($this->model->modTags($id, $data)) {
                $this->log('修改文章内链' . $id . '成功！');
                if (! ! $backurl = get('backurl')) {
                    success('修改成功！', base64_decode($backurl));
                } else {
                    success('修改成功！', url('/admin/Tags/index'));
                }
            } else {
                location(- 1);
            }
        } else {
            // 调取修改内容
            $this->assign('mod', true);
            if (! $result = $this->model->getTags($id)) {
                error('编辑的内容已经不存在！', - 1);
            }
            $this->assign('tags', $result);
            $this->display('content/tags.html');
        }
    }
}