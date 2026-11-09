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
            $tagsBack = admin_back_target(url('/admin/Tags/index'));
            if ($this->model->addTags($data)) {
                $this->log('新增文章内链成功！');
                success('新增成功！', $tagsBack);
            } else {
                $this->log('新增文章内链失败！');
                alert_back('新增失败！');
            }
        }
    }

    // 文章内链删除
    public function del()
    {
        $back = admin_stay_target(url('/admin/Tags/index'));
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请使用正确方式提交！', url('/admin/Tags/index'));
        }
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', url('/admin/Tags/index'));
        }
        
        if ($this->model->delTags($id)) {
            $this->log('删除文章内链' . $id . '成功！');
            success('删除成功！', url('/admin/Tags/index'));
        } else {
            $this->log('删除文章内链' . $id . '失败！');
            error('删除失败！', url('/admin/Tags/index'));
        }
    }

    // 文章内链修改
    public function mod()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', url('/admin/Tags/index'));
        }
        
        // 单独修改状态
        $posted = admin_posted_field(array('status'), url('/admin/Tags/index'));
        if ($posted) {
            if (admin_saved($this->model->modTags($id, $posted[0] . "='" . $posted[1] . "',update_user='" . session('username') . "'"), true)) {
                success('修改成功！', url('/admin/Tags/index'));
            } else {
                error('修改失败！', url('/admin/Tags/index'));
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
                success('修改成功！', admin_back_target(url('/admin/Tags/index')));
            } else {
                if ($this->model->getTags($id)) {
                    $this->log('修改文章内链' . $id . '成功！');
                    success('修改成功！', url('/admin/Tags/index'));
                } else {
                    error('编辑的内容已经不存在！', url('/admin/Tags/index'));
                }
            }
        } else {
            // 调取修改内容
            $this->assign('mod', true);
            if (! $result = $this->model->getTags($id)) {
                error('编辑的内容已经不存在！', url('/admin/Tags/index'));
            }
            $this->assign('tags', $result);
            $this->display('content/tags.html');
        }
    }
}