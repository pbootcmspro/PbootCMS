<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年3月21日
 *  站点设置控制器
 */
namespace app\admin\controller\content;

use core\basic\Controller;
use app\admin\model\content\SiteModel;

class SiteController extends Controller
{

    public function __construct()
    {
        $this->model = new SiteModel();
    }

    // 显示站点信息
    public function index()
    {
        // 获取主题列表
        $themes = dir_list(ROOT_PATH . current($this->config('tpl_dir')));
        $this->assign('themes', $themes);
        
        // 获取系统配置
        $this->assign('sites', $this->model->getList());
        
        // 显示
        $this->display('content/site.html');
    }

    // 修改站点信息
    public function mod()
    {
        if (! $_POST) {
            return;
        }
        
        $data = array(
            'title' => post('title'),
            'subtitle' => post('subtitle'),
            'domain' => post('domain'),
            'logo' => post('logo'),
            'keywords' => post('keywords'),
            'description' => post('description'),
            'icp' => post('icp'),
            'theme' => basename(post('theme')) ?: 'default',
            'statistical' => post('statistical'),
            'copyright' => post('copyright')
        );
        
        path_delete(RUN_PATH . '/config'); // 清理缓存的配置文件
        if ($this->model->checkSite()) {
            if ($this->model->modSite($data)) {
                $this->log('修改站点信息成功！');
                success('修改成功！', - 1);
            } else {
                location(- 1);
            }
        } else {
            $data['acode'] = session('acode');
            if ($this->model->addSite($data)) {
                $this->log('修改站点信息成功！');
                success('修改成功！', - 1);
            } else {
                location(- 1);
            }
        }
    }

    // 服务器基础信息
    public function server()
    {
        $this->assign('server', get_server_info());
        $this->display('system/server.html');
    }
}

