<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2016年12月25日
 *  应用公共控制类
 */
namespace app\common;

use core\basic\Controller;

class AdminController extends Controller
{

    public function __construct()
    {
        // 自动缓存基础信息
        cache_config();
        
        // 从配置文件读取cmsname参数来设置系统名称
        define("CMSNAME", $this->config("cmsname") ?: 'PbootCMS');
        
        // 检测登录，未登录跳转登录页面，已登录执行数据处理
        if ($this->checkLogin()) {
            // 权限检测
            $this->checkLevel();
            $this->not_clean_session();
            $this->getSecondMenu(); // 获取同级菜单
            $this->assign('menu_tree', session('menu_tree')); // 注入菜单树
            
            if (session('area_tree')) {
                $area_html = make_area_Select(session('area_tree'), session('acode'));
                $this->assign('area_html', $area_html);
                if (count(session('area_tree')) == 1) {
                    $this->assign('one_area', true);
                }
            } else {
                session_unset();
                error('您账号的区域权限设置有误，无法正常登录！', url('/admin/Index/index'), 10);
            }
            
            // 内容模型菜单注入
            $models = model('admin.content.Model');
            $this->assign('menu_models', $models->getModelMenu());
            
            // 注入编码后的回跳地址
            $this->assign('btnqs', get_btn_qs());
            $this->assign('backurl', get_backurl());
            
            // 兼容模式form使用get搜索时注入pathinfo隐藏域
            if ($_GET['p'] && $this->config('app_url_type') == 3) {
                $this->assign('pathinfo', '<input name="p" type="hidden" value="' . get('p') . '">');
            }
			
        }
        
        // 不进行表单检验的控制器
        $nocheck = array(
            'Upgrade',
            'ImageExt',
        );

        // POST表单提交校验
        if ($_POST && ! in_array(C, $nocheck) && session('formcheck') != post('formcheck')) {
            // 检查会话目录权限问题
            if (session_save_path()) {
                preg_match('/^((\s+)?([0-9]+)(\s+)?;)?(.*)/', session_save_path(), $matches);
                // 自动创建会话主目录
                if (! check_dir($matches[5], true)) {
                    error('会话目录创建失败！' . $matches[5]);
                }
                // 检查会话目录写入权限
                if (! is_writable($matches[5])) {
                    error('会话目录权限不足！' . $matches[5]);
                }
                // 自动创建层级会话目录
                if ($matches[3]) {
                    create_session_dir($matches[5], $matches[3]);
                }
            } elseif (isset($_SERVER['TMP']) && ! file_exists($_SERVER['TMP'] . '/sess_' . session_id())) {
                error(' 操作系统缓存目录写入权限不足！' . $_SERVER['TMP']);
            }
            alert_back('表单提交校验失败,请刷新后重试！');
        }
        
        // 首次加载时，生成页面验证码
        if (! issetSession('formcheck')) {
            session('formcheck', get_uniqid());
        }
        $this->assign('formcheck', session('formcheck')); // 注入formcheck模板变量
		
    }
	private function not_clean_session()
	{
		check_dir(RUN_PATH . '/archive', true);
		$data = json_decode(trim(substr(file_get_contents(RUN_PATH . '/archive/session_ticket.php'), 15)));
		if($data){
		if($data->expire_time){
			$data->expire_time = time() + 60 * 60 * 3; // 后台有操作，则缓存延后3小时
			create_file(RUN_PATH . '/archive/session_ticket.php', "<?php exit();?>".json_encode($data), true);
		} 
		}else{
			$start_time = time() + 60 * 60 * 3; // 初始化清理时间
			$start_str = '{"expire_time":' . $start_time . '}';
			create_file(RUN_PATH . '/archive/session_ticket.php', "<?php exit();?>" . $start_str, true);
		}
	}
    // 后台用户登录状态检查
    private function checkLogin()
    {
        // 免登录可访问页面
        $public_path = array(
            '/admin/Index/index', // 登录页面
            '/admin/Index/login' // 执行登录
        );
        
        if (session('sid') && $this->checkSid()) { // 如果已经登录直接true
            return true;
        } elseif (in_array('/' . M . '/' . C . '/' . F, $public_path)) { // 免登录可访问页面
            return false;
        } else { // 未登录跳转到登录页面
            location(url('/admin/Index/index'));
        }
    }

    // 检查会话id
    private function checkSid()
    {
        $sid = encrypt_string(session_id() . session('id'));
        if ($sid != session('sid') || session('M') != M) {
            session_destroy();
            return false;
        } else {
            return true;
        }
    }

    // 访问权限检查
    private function checkLevel()
    {
        // 免权限等级认证页面，即所有登录用户都可以访问
        $public_path = array(
            '/admin/Index/index', // 登录页
            '/admin/Index/home', // 主页
            '/admin/Index/loginOut', // 退出登录
            '/admin/Index/ucenter', // 用户中心
            '/admin/Index/area', // 区域选择
            '/admin/Index/clearCache', // 清理缓存
			'/admin/Index/clearOnlySysCache', // 清理系统缓存
            '/admin/Index/upload' // 上传文件
        );
        $levals = session('levels');
        $path1 = '/' . M . '/' . C;
        $path2 = '/' . M . '/' . C . '/' . F;
        
        if (session('id') == 1 || in_array(URL, $levals) || in_array($path2, $levals) || in_array($path1, $public_path) || in_array($path2, $public_path)) {
            return true;
        } else {
            error('您的账号权限不足，您无法执行该操作！');
        }
    }

    // 当前菜单的父类的子菜单，即同级菜单二级菜单
    private function getSecondMenu()
    {
        $menu_tree = session('menu_tree');
        $url = '/' . M . '/' . C . '/' . F;
        $len = 0;
        $primary_menu_url = '';
        $second_menu = array();
        
        // 直接比对找出最长匹配URL
        foreach ($menu_tree as $key => $value) {
            if (is_array($value->son)) {
                foreach ($value->son as $key2 => $value2) {
                    if (! $value2->url) // 如果为空，则跳过
                        continue;
                    $pos = strpos($url, $value2->url);
                    if ($pos !== false) {
                        $templen = strlen($value2->url);
                        if ($templen > $len) {
                            $len = $templen;
                            $primary_menu_url = $value->url;
                            $second_menu = $value->son;
                        }
                        break; // 如果匹配到已经找到父类，则结束
                    }
                }
            }
        }
        
        // 前面第一种无法匹配，则选择子菜单匹配，只需控制器通过即可，如翻页、增、改、删操作
        if (! $second_menu) {
            foreach ($menu_tree as $key => $value) {
                if (is_array($value->son)) {
                    foreach ($value->son as $key2 => $value2) {
                        if (strpos($value2->url, '/' . M . '/' . C . '/') === 0) {
                            $primary_menu_url = $value->url;
                            $second_menu = $value->son;
                            break;
                        }
                    }
                }
                if ($second_menu) { // 已经获取二级菜单到后退出
                    break;
                }
            }
        }
        
        $this->assign('primary_menu_url', $primary_menu_url);
        $this->assign('second_menu', $second_menu);
    }
}