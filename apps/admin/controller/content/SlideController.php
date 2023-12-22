<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年3月1日
 *  轮播图控制器
 */
namespace app\admin\controller\content;

use core\basic\Controller;
use app\admin\model\content\SlideModel;

class SlideController extends Controller
{

    private $model;

    public function __construct()
    {
        $this->model = new SlideModel();
    }

    // 轮播图列表
    public function index()
    {
        if ((! ! $id = get('id', 'int')) && $result = $this->model->getSlide($id)) {
            $this->assign('more', true);
            $this->assign('slide', $result);
        } else {
            $this->assign('list', true);
            if (! ! ($field = get('field', 'var')) && ! ! ($keyword = get('keyword', 'vars'))) {
                $result = $this->model->findSlide($field, $keyword);
            } else {
                $result = $this->model->getList();
            }
            $this->assign('gids', $this->model->getGid());
            $this->assign('slides', $result);
        }
        $this->display('content/slide.html');
    }

    // 轮播图增加
    public function add()
    {
        if ($_POST) {
            // 获取数据
            $gid = post('gid', 'int');
            $pic = post('pic');
            $link = post('link');
            $title = post('title');
            $subtitle = post('subtitle');
            $sorting = post('sorting', 'int');
            
            if (! $gid) {
                $gid = $this->model->getMaxGid() + 1;
            }
            
            if (! $pic) {
                alert_back('图片不能为空！');
            }
            
            // 构建数据
            $data = array(
                'acode' => session('acode'),
                'gid' => $gid,
                'pic' => $pic,
                'link' => $link,
                'title' => $title,
                'subtitle' => $subtitle,
                'sorting' => $sorting,
                'create_user' => session('username'),
                'update_user' => session('username')
            );
            
            // 执行添加
            if ($this->model->addSlide($data)) {
                $this->log('新增轮播图成功！');
                if (! ! $backurl = get('backurl')) {
                    success('新增成功！', base64_decode($backurl));
                } else {
                    success('新增成功！', url('/admin/Slide/index'));
                }
            } else {
                $this->log('新增轮播图失败！');
                error('新增失败！', - 1);
            }
        }
    }

    // 轮播图删除
    public function del()
    {
        if (! $id = get('id', 'int')) {
            error('传递的参数值错误！', - 1);
        }
        
        if ($this->model->delSlide($id)) {
            $this->log('删除轮播图' . $id . '成功！');
            success('删除成功！', - 1);
        } else {
            $this->log('删除轮播图' . $id . '失败！');
            error('删除失败！', - 1);
        }
    }

    // 轮播图修改
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
                            $this->model->modSlide($value, "sorting=" . $sorting[$key]);
                        }
                        $this->log('批量修改轮播图排序成功！');
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
            if ($this->model->modSlide($id, "$field='$value',update_user='" . session('username') . "'")) {
                location(- 1);
            } else {
                alert_back('修改失败！');
            }
        }
        
        // 修改操作
        if ($_POST) {
            
            // 获取数据
            $gid = post('gid', 'int');
            $pic = post('pic');
            $link = post('link');
            $title = post('title');
            $subtitle = post('subtitle');
            $sorting = post('sorting', 'int');
            
            if (! $gid) {
                $gid = $this->model->getMaxGid() + 1;
            }
            
            if (! $pic) {
                alert_back('图片不能为空！');
            }
            
            // 构建数据
            $data = array(
                'gid' => $gid,
                'pic' => $pic,
                'link' => $link,
                'title' => $title,
                'subtitle' => $subtitle,
                'sorting' => $sorting,
                'update_user' => session('username')
            );
            
            // 执行添加
            if ($this->model->modSlide($id, $data)) {
                $this->log('修改轮播图' . $id . '成功！');
                if (! ! $backurl = get('backurl')) {
                    success('修改成功！', base64_decode($backurl));
                } else {
                    success('修改成功！', url('/admin/Slide/index'));
                }
            } else {
                location(- 1);
            }
        } else {
            // 调取修改内容
            $this->assign('mod', true);
            if (! $result = $this->model->getSlide($id)) {
                error('编辑的内容已经不存在！', - 1);
            }
            $this->assign('gids', $this->model->getGid());
            $this->assign('slide', $result);
            $this->display('content/slide.html');
        }
    }
}