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
        $back = url('/admin/Site/index');
        if ($this->model->checkSite()) {
            if ($this->writeOk($this->model->modSite($data), true)) {
                $this->log('修改站点信息成功！');
                success('修改成功！', $back);
            } else {
                error('修改失败！', $back);
            }
        } else {
            $data['acode'] = session('acode');
            if ($this->writeOk($this->model->addSite($data), false)) {
                $this->log('修改站点信息成功！');
                success('修改成功！', $back);
            } else {
                error('修改失败！', $back);
            }
        }
    }

    // 服务器基础信息
    public function server()
    {
        $this->assign('server', get_server_info());
        $this->display('system/server.html');
    }

    // Imagick 深度安全探测（显式触发，非热路径）
    public function imagickProbe()
    {
        if (! $_POST) {
            return;
        }
        $back = url('/admin/Site/server');
        if (! function_exists('imagick_probe_deep_and_persist')) {
            error('Imagick 探测功能不可用！', $back);
        }
        $result = imagick_probe_deep_and_persist();
        if ($result === null) {
            error('Imagick 深度探测失败，请确认扩展已加载且数据库可写！', $back);
        }
        $runtime = empty($result['runtime_coders']) ? '无' : implode(',', $result['runtime_coders']);
        $this->log('Imagick 深度安全探测完成，runtime 可读 coder：' . $runtime);
        success('深度探测完成，runtime 可读 coder：' . $runtime, $back);
    }

    private function writeOk($result, $unchangedOk)
    {
        if ($result === true) {
            return true;
        }
        if (is_int($result) || is_float($result) || (is_string($result) && is_numeric($result))) {
            return $unchangedOk ? ($result >= 0) : ($result > 0);
        }
        return false;
    }
}

