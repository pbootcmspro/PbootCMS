<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年01月03日
 *  应用配置控制器
 */
namespace app\admin\controller\system;

use core\basic\Controller;
use app\admin\model\system\ConfigModel;
use core\basic\Config;

class ConfigController extends Controller
{

    private $model;

    public function __construct()
    {
        $this->model = new ConfigModel();
    }

    // 应用配置列表
    public function index()
    {
        if (! ! $action = get('action')) {
            switch ($action) {
                case 'sendemail':
                    $rs = sendmail($this->config(), get('to'), '【' . CMSNAME . '】测试邮件', '欢迎您使用' . CMSNAME . '网站开发管理系统！');
                    if ($rs === true) {
                        alert_back('测试邮件发送成功！');
                    } else {
                        error('发送失败：' . $rs);
                    }
                    break;
            }
        }
        
        // 修改参数配置
        if ($_POST) {
            unset($_POST['upload']); // 去除上传组件
            if (isset($_POST['sn'])) {
                $_POST['licensecode'] = base64_encode(post('sn') . '/' . post('sn_user')) . substr(post('sn'), 1, 1);
            }
            foreach ($_POST as $key => $value) {
                if (! preg_match('/^[\w\-]+$/', $key)) {
                    continue;
                }
                $config = array(
                    'debug',
                    // 'sn',
                    // 'sn_user',
                    'pagenum',
                    'tpl_html_cache',
                    'tpl_html_cache_time',
                    'session_in_sitepath'
                );
                if (in_array($key, $config)) {
                    if ($key == 'tpl_html_cache_time' && ! $value) {
                        $value = 900;
                    } else {
                        $value = post($key);
                    }
                    $this->modConfig($key, $value);
                } else {
                    $this->modDbConfig($key);
                }
            }
            
            $this->log('修改参数配置成功！');
            path_delete(RUN_PATH . '/config'); // 清理缓存的配置文件
            
            switch (post('submit')) {
                case 'email':
                    success('修改成功！', url('/admin/Config/index' . get_tab('t2'), false));
                    break;
                case 'baidu':
                    success('修改成功！', url('/admin/Config/index' . get_tab('t3'), false));
                    break;
                case 'api':
                    success('修改成功！', url('/admin/Config/index' . get_tab('t4'), false));
                    break;
                case 'watermark':
                    success('修改成功！', url('/admin/Config/index' . get_tab('t5'), false));
                    break;
                case 'security':
                    success('修改成功！', url('/admin/Config/index' . get_tab('t6'), false));
                    break;
                case 'urlrule':
                    success('修改成功！', url('/admin/Config/index' . get_tab('t7'), false));
                    break;
                case 'pagetitle':
                    success('修改成功！', url('/admin/Config/index' . get_tab('t8'), false));
                    break;
                case 'member':
                    success('修改成功！', url('/admin/Config/index' . get_tab('t9'), false));
                    break;
                case 'upgrade':
                    success('修改成功！', url('/admin/Upgrade/index' . get_tab('t2'), false));
                    break;
                default:
                    success('修改成功！', url('/admin/Config/index', false));
            }
        }
        $configs = $this->model->getList();
        $configs['debug']['value'] = $this->config('debug');
        if (! $configs['sn']['value']) {
            $configs['sn']['value'] = $this->config('sn');
            $configs['sn_user']['value'] = $this->config('sn_user');
        }
        $configs['session_in_sitepath']['value'] = $this->config('session_in_sitepath');
        $configs['pagenum']['value'] = $this->config('pagenum');
        $configs['url_type']['value'] = $this->config('url_type');
        $configs['tpl_html_cache']['value'] = $this->config('tpl_html_cache');
        $configs['tpl_html_cache_time']['value'] = $this->config('tpl_html_cache_time');
        $this->assign('configs', $configs);
        
        $this->assign('groups', model('admin.member.MemberGroup')->getSelect());
        
        $this->display('system/config.html');
    }

    // 修改配置文件
    private function modConfig($key, $value)
    {
        $value = str_replace(' ', '', $value); // 去除空格
        $value = str_replace('，', ',', $value); // 转换可能输入的中文逗号
        if (! preg_match('/^[\w\s\,\-]+$/', $value)) {
            return;
        }
        
        $config = file_get_contents(CONF_PATH . '/config.php');
        if (preg_match("'$key'", $config)) {
            if (preg_match('/^[0-9]+$/', $value)) {
                $config = preg_replace('/(\'' . $key . '\'([\s]+)?=>([\s]+)?)[\w\'\"\s,]+,/', '${1}' . $value . ',', $config);
            } else {
                $config = preg_replace('/(\'' . $key . '\'([\s]+)?=>([\s]+)?)[\w\'\"\s,]+,/', '${1}\'' . $value . '\',', $config);
            }
        } else {
            $config = preg_replace('/(return array\()/', "$1\r\n\r\n\t'$key' => '$value',", $config); // 自动新增配置
        }
        return file_put_contents(CONF_PATH . '/config.php', $config);
    }

    // 修改数据库配置
    private function modDbConfig($key)
    {
        $value = post($key);
        
        // 如果开启伪静态时自动拷贝文件
        if ($key == 'url_rule_type' && $value == 2) {
            $soft = get_server_soft();
            if ($soft == 'iis') {
                if (! file_exists(ROOT_PATH . '/web.config')) {
                    copy(ROOT_PATH . '/rewrite/web.config', ROOT_PATH . '/web.config');
                }
            } elseif ($soft == 'apache') {
                if (! file_exists(ROOT_PATH . '/web.config')) {
                    copy(ROOT_PATH . '/rewrite/.htaccess', ROOT_PATH . '/.htaccess');
                }
            }
        }
        
        // 模板目录修改
        if (($key == 'tpl_html_dir') && $value) {
            
            // 不允许特殊字符
            if (! preg_match('/^\w+$/', $value)) {
                return;
            }
            
            $value = basename($value);
            $htmldir = $this->config('tpl_html_dir');
            $tpl_path = ROOT_PATH . current($this->config('tpl_dir')) . '/' . model('admin.content.ContentSort')->getTheme();
            
            if (! $htmldir || ! file_exists($tpl_path . '/' . $htmldir)) {
                if (! check_dir($tpl_path . '/' . $value, true)) {
                    return;
                } // 原来没有目录时只创建目录，创建失败时直接不修改
            } else {
                if ($value != $htmldir) {
                    if (file_exists($tpl_path . '/' . $value)) {
                        if (dir_copy($tpl_path . '/' . $htmldir, $tpl_path . '/' . $value)) {
                            path_delete($tpl_path . '/' . $htmldir, true); // 删除原来的
                        } else {
                            return; // 修改失败
                        }
                    } else {
                        if (! rename($tpl_path . '/' . $htmldir, $tpl_path . '/' . $value)) {
                            return; // 修改失败
                        }
                    }
                }
            }
        }
        
        if ($key == 'home_upload_ext') {
            // 不允许特殊扩展
            if (preg_match('/(php|jsp|asp|exe|sh|cmd|vb|vbs|phtml)/i', $value)) {
                return;
            }
        }
        
        // 数据分割处理
        $hander = array(
            'content_keyword_replace',
            'ip_deny',
            'ip_allow'
        );
        if (in_array($key, $hander) && $value) {
            $value = str_replace("\r\n", ",", $value); // 替换回车
            $value = str_replace("，", ",", $value); // 替换中文逗号分割符
        }
        
        if ($this->model->checkConfig("name='$key'")) {
            $this->model->modValue($key, $value);
        } elseif ($key != 'submit' && $key != 'formcheck') {
            // 自动新增配置项
            $data = array(
                'name' => $key,
                'value' => $value,
                'type' => 2,
                'sorting' => 255,
                'description' => ''
            );
            return $this->model->addConfig($data);
        }
    }
}