<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年3月1日
 *  友情链接控制器
 */
namespace app\admin\controller\content;

use core\basic\Controller;
use app\admin\model\content\LinkModel;

class LinkController extends Controller
{

    private $model;

    public function __construct()
    {
        $this->model = new LinkModel();
    }

    // 友情链接列表
    public function index()
    {
        if ((! ! $id = get('id', 'int')) && $result = $this->model->getLink($id)) {
            $this->assign('more', true);
            $this->assign('link', $result);
        } else {
            $this->assign('list', true);
            if (! ! ($field = get('field', 'var')) && ! ! ($keyword = get('keyword', 'vars'))) {
                $result = $this->model->findLink($field, $keyword);
            } else {
                $result = $this->model->getList();
            }
            $this->assign('gids', $this->model->getGid());
            $this->assign('links', $result);
        }
        $this->display('content/link.html');
    }

    // 友情链接增加
    public function add()
    {
        if ($_POST) {
            // 获取数据
            $gid = post('gid', 'int');
            $name = post('name');
            $link = post('link');
            $logo = post('logo');
            $sorting = post('sorting');
            
            if (! $gid) {
                $gid = $this->model->getMaxGid() + 1;
            }
            
            if (! $name) {
                alert_back('名称不能为空！');
            }
            
            if (! $link) {
                alert_back('链接不能为空！');
            }
            
            if (! $sorting) {
                $sorting = 255;
            }
            
            // logo图缩放
            if ($logo) {
                resize_img(ROOT_PATH . $logo, '', $this->config('ico.max_width'), $this->config('ico.max_height'));
            }
            
            // 构建数据
            $data = array(
                'acode' => session('acode'),
                'gid' => $gid,
                'name' => $name,
                'link' => $link,
                'logo' => $logo,
                'sorting' => $sorting,
                'create_user' => session('username'),
                'update_user' => session('username')
            );
            
            // 执行添加
            if ($this->model->addLink($data)) {
                $this->log('新增友情链接成功！');
                if (! ! $backurl = get('backurl')) {
                    success('新增成功！', base64_decode($backurl));
                } else {
                    success('新增成功！', url('/admin/Link/index'));
                }
            } else {
                $this->log('新增友情链接失败！');
                error('新增失败！', - 1);
            }
        }
    }

    // 友情链接删除
    public function del()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        
        if ($this->model->delLink($id)) {
            $this->log('删除友情链接' . $id . '成功！');
            success('删除成功！', - 1);
        } else {
            $this->log('删除友情链接' . $id . '失败！');
            error('删除失败！', - 1);
        }
    }

    // 友情链接修改
    public function mod()
    {
        if (! ! $submit = post('submit')) {
            switch ($submit) {
                case 'sorting': // 修改列表排序
                    $listall = post('listall');
                    if ($listall) {
                        $sorting = post('sorting');
                        foreach ($listall as $key => $value) {
                            if ($sorting[$key] === '' || ! is_numeric($sorting[$key]))
                                $sorting[$key] = 255;
                            $this->model->modLink($value, "sorting=" . $sorting[$key]);
                        }
                        $this->log('批量修改链接排序成功！');
                        success('修改成功！', - 1);
                    } else {
                        alert_back('排序失败，无任何内容！');
                    }
                    break;
            }
        }
        
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        
        // 单独修改状态
        if (($field = get('field', 'var')) && ! is_null($value = get('value', 'var'))) {
            if ($this->model->modLink($id, "$field='$value',update_user='" . session('username') . "'")) {
                location(- 1);
            } else {
                alert_back('修改失败！');
            }
        }
        
        // 修改操作
        if ($_POST) {
            
            // 获取数据
            $gid = post('gid', 'int');
            $name = post('name');
            $link = post('link');
            $logo = post('logo');
            $sorting = post('sorting');
            
            if (! $gid) {
                $gid = $this->model->getMaxGid() + 1;
            }
            
            if (! $name) {
                alert_back('名称不能为空！');
            }
            
            if (! $link) {
                alert_back('链接不能为空！');
            }
            
            // logo图缩放
            if ($logo) {
                resize_img(ROOT_PATH . $logo, '', $this->config('ico.max_width'), $this->config('ico.max_height'));
            }
            
            // 构建数据
            $data = array(
                'gid' => $gid,
                'name' => $name,
                'link' => $link,
                'logo' => $logo,
                'sorting' => $sorting,
                'update_user' => session('username')
            );
            
            // 执行添加
            if ($this->model->modLink($id, $data)) {
                $this->log('修改友情链接' . $id . '成功！');
                if (! ! $backurl = get('backurl')) {
                    success('修改成功！', base64_decode($backurl));
                } else {
                    success('修改成功！', url('/admin/Link/index'));
                }
            } else {
                location(- 1);
            }
        } else {
            // 调取修改内容
            $this->assign('mod', true);
            if (! $result = $this->model->getLink($id)) {
                error('编辑的内容已经不存在！', - 1);
            }
            $this->assign('gids', $this->model->getGid());
            $this->assign('link', $result);
            $this->display('content/link.html');
        }
    }
}