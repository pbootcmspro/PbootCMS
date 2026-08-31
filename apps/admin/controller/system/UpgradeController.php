<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2018年8月14日
 *  在线更新
 */
namespace app\admin\controller\system;

use core\basic\Controller;
use core\basic\Model;

class UpgradeController extends Controller
{

    // 服务器地址
    private $server = 'https://www.pbootcms.com';

    // 更新分支
    private $branch;

    // 强制同步文件
    private $force;

    // 修改版本
    private $revise;

    // 文件列表
    public $files = array();

    public function __construct()
    {
        // 不依赖 error_reporting(0) 掩盖 PHP 8 兼容问题；告警/错误应被修复或由框架处理
        $this->branch = $this->config('upgrade_branch') == '3.X.dev' ? '3.X.dev' : '3.X';
        $this->force = $this->config('upgrade_force') ?: 0;
        $this->revise = $this->config('revise_version') ?: 0;
    }

    public function index()
    {
        switch (get('action')) {
            case 'local':
                $upfile = $this->local();
                break;
            default:
                $upfile = array();
        }
        $this->assign('upfile', $upfile);
        $this->assign('branch', $this->branch);
        $this->assign('force', $this->force);
        $this->assign('revise', $this->revise);
        $this->assign('snuser', $this->config('sn_user') ?: 0);
        $this->assign('site', get_http_url());
        $this->assign('php_version', PHP_VERSION);
        $this->display('system/upgrade.html');
    }

    public function checkCache(){
        $now = time();
        $cache = isset($_SESSION['check_cache']) ? $_SESSION['check_cache'] : 0;
        if(!$cache){
            $_SESSION['check_cache'] = time();
            json(1,'');
        }else{
            $sub = $now - $cache;
            if($sub > 3600){
                $_SESSION['check_cache'] = time();
                json(1,'');
            }else{
                json(0,'');
            }
        }
    }

    // 检查更新
    public function check()
    {
        // 清理目录，检查下载目录及备份目录
        path_delete(RUN_PATH . '/upgrade', true);
        if (! check_dir(RUN_PATH . '/upgrade', true)) {
            json(0, '目录写入权限不足，无法正常升级：runtime/upgrade');
        }
        check_dir(DOC_PATH . DATA_DIR . '/backup/upgrade', true);
        
        $files = $this->getServerList();
        $db = get_db_type();
        $upfile = array();
        foreach ($files as $key => $value) {
            // 过滤掉相对路径
            $value->path = preg_replace_r('{\.\.(\/|\\\\)}', '', $value->path);
            $file = ROOT_PATH . $value->path;
            if (@md5_file($file) != $value->md5) {
                // 筛选数据库更新脚本
                if (preg_match('/([\w]+)-([\w\.]+)-update\.sql/i', $file, $matches)) {
                    if ($matches[1] != $db || ! $this->compareVersion($matches[2], APP_VERSION . '.' . RELEASE_TIME . '.' . $this->revise)) {
                        continue;
                    }
                }
                if (file_exists($file)) {
                    $files[$key]->type = '<span style="color:Red">覆盖</span>';
                    $files[$key]->ltime = date('Y-m-d H:i:s', filemtime($file));
                } else {
                    $files[$key]->type = '新增';
                    $files[$key]->ltime = '无';
                }
                $files[$key]->ctime = date('Y-m-d H:i:s', $files[$key]->ctime);
                $upfile[] = $files[$key];
            }
        }
        if (! $upfile) {
            json(1, '您的系统无任何文件需要更新！');
        } else {
            json(1, $upfile);
        }
    }

    // 执行下载
    public function down()
    {
        if (! ! $list = get('list')) {
            if (! is_array($list)) { // 单个文件转换为数组
                $list = array(
                    $list
                );
            }
            $len = count($list) ?: 0;
            foreach ($list as $value) {
                // 过滤掉相对路径
                $value = preg_replace_r('{\.\.(\/|\\\\)}', '', $value);
                // 本地存储路径
                $path = RUN_PATH . '/upgrade' . $value;
                // 自动创建目录
                if (! check_dir(dirname($path), true)) {
                    json(0, '目录写入权限不足，无法下载升级文件：runtime/upgrade' . dirname($value));
                }
                
                // 定义执行下载的类型
                $types = '.zip|.rar|.doc|.docx|.ppt|.pptx|.xls|.xlsx|.chm|.ttf|.otf|';
                $pathinfo = explode(".", basename($path));
                $ext = end($pathinfo); // 获取扩展
                if (preg_match('/\.' . $ext . '\|/i', $types)) {
                    $result = $this->getServerDown('/release/' . $this->branch . $value, $path);
                } else {
                    $result = $this->getServerFile($value, $path);
                }
            }
            if ($len == 1) {
                json(1, "更新文件 " . basename($value) . " 下载成功!");
            } else {
                json(1, "更新文件" . basename($value) . "等文件全部下载成功!");
            }
        } else {
            json(0, '请选择要下载的文件！');
        }
    }

    // 执行更新
    public function update()
    {
        if ($_POST) {
            if (! ! $list = post('list')) {
                $list = explode(',', $list);
                $backdir = date('YmdHis');
                $sqls = array();
                $files = array();
                $versionFiles = array();
                
                // 分离文件：普通文件与版本/发布状态文件
                foreach ($list as $value) {
                    // 过滤掉相对路径
                    $value = preg_replace_r('{\.\.(\/|\\\\)}', '', $value);
                    
                    if (stripos($value, '/script/') === 0 && preg_match('/\.sql$/i', $value)) {
                        $sqls[] = $value;
                    } else {
                        $path = RUN_PATH . '/upgrade' . $value;
                        $des_path = ROOT_PATH . $value;
                        $back_path = DOC_PATH . DATA_DIR . '/backup/upgrade/' . $backdir . $value;
                        if (! check_dir(dirname($des_path), true)) {
                            json(0, '目录写入权限不足，无法正常升级：' . dirname($value));
                        }
                        if (file_exists($des_path)) { // 文件存在时执行备份
                            check_dir(dirname($back_path), true);
                            copy($des_path, $back_path);
                        }
                        
                        // 如果后台入口文件修改过名字，则自动适配
                        if (stripos($path, 'admin.php') !== false && stripos($_SERVER['SCRIPT_FILENAME'], 'admin.php') === false) {
                            if (file_exists($_SERVER['SCRIPT_FILENAME'])) {
                                $des_path = $_SERVER['SCRIPT_FILENAME'];
                            }
                        }
                        
                        $item = array(
                            'sfile' => $path,
                            'dfile' => $des_path
                        );
                        // 版本号与发布状态最后写入，避免中途失败后页面已显示新版本
                        if ($this->isVersionStatusFile($value)) {
                            $versionFiles[] = $item;
                        } else {
                            $files[] = $item;
                        }
                    }
                }
                
                // 更新数据库
                if ($sqls) {
                    $db = new DatabaseController();
                    switch (get_db_type()) {
                        case 'sqlite':
                            $db->backupSqliteDB(date('YmdHis') . '_' . basename($this->config('database.dbname')), '/backup/upgrade/');
                            break;
                        case 'mysql':
                            $db->backupDB();
                            break;
                    }
                    sort($sqls); // 排序
                    foreach ($sqls as $value) {
                        $path = RUN_PATH . '/upgrade' . $value;
                        if (file_exists($path)) {
                            $sql = file_get_contents($path);
                            if ($sql === false || ! $this->upsql($sql)) {
                                $this->log("数据库 $value 更新失败!");
                                json(0, '数据库脚本 ' . $value . ' 更新失败！');
                            }
                        } else {
                            json(0, '数据库文件 ' . $value . ' 不存在！');
                        }
                    }
                }
                
                // 先替换普通文件
                foreach ($files as $value) {
                    if (! copy($value['sfile'], $value['dfile'])) {
                        $this->log("文件 " . $value['dfile'] . " 更新失败!");
                        json(0, '文件 ' . str_replace(ROOT_PATH, '', $value['dfile']) . ' 更新失败，请重试!');
                    }
                }

                // 全部 SQL 与普通文件成功后再更新版本号/发布状态
                foreach ($versionFiles as $value) {
                    if (! copy($value['sfile'], $value['dfile'])) {
                        $this->log("文件 " . $value['dfile'] . " 更新失败!");
                        json(0, '文件 ' . str_replace(ROOT_PATH, '', $value['dfile']) . ' 更新失败，请重试!');
                    }
                }
                
                // 清理缓存
                path_delete(RUN_PATH . '/upgrade', true);
                path_delete(RUN_PATH . '/cache');
                path_delete(RUN_PATH . '/complile');
                path_delete(RUN_PATH . '/config');

                // 补齐上传目录安全规则（升级包可能只带 rewrite 模板）
                if (function_exists('upload_ensure_htaccess')) {
                    upload_ensure_htaccess(true);
                }
                
                $this->log("系统更新成功!");
                json(1, '系统更新成功！');
            } else {
                json(0, '请选择要更新的文件！');
            }
        }
    }

    // 缓存文件
    private function local()
    {
        $files = $this->getLoaclList(RUN_PATH . '/upgrade');
        $files = json_decode(json_encode($files));
        $upfile = array();
        foreach ($files as $key => $value) {
            $file = ROOT_PATH . $value->path;
            if (file_exists($file)) {
                $files[$key]->type = '<span style="color:Red">覆盖</span>';
                $files[$key]->ltime = date('Y-m-d H:i:s', filemtime($file));
            } else {
                $files[$key]->type = '新增';
                $files[$key]->ltime = '无';
            }
            $files[$key]->ctime = date('Y-m-d H:i:s', $files[$key]->ctime);
            $upfile[] = $files[$key];
        }
        return $upfile;
    }

    // 执行更新数据库
    private function upsql($sql)
    {
        $sql = explode(';', $sql);
        $model = new Model();
        foreach ($sql as $value) {
            $value = trim($value);
            if ($value) {
                $model->amd($value);
            }
        }
        return true;
    }

    /**
     * 是否为版本号/发布状态文件（须在全部其它文件替换成功后再写入）
     */
    private function isVersionStatusFile($relPath)
    {
        $norm = str_replace('\\', '/', (string) $relPath);
        return (bool) preg_match('#(^|/)apps/common/version\.php$#i', $norm);
    }

    // 获取列表
    private function getServerList()
    {
        $param = array(
            'version' => APP_VERSION . '.' . RELEASE_TIME . '.' . $this->revise,
            'branch' => $this->branch,
            'force' => $this->force,
            'site' => get_http_url(),
            'snuser' => $this->config('sn_user'),
            'php' => PHP_VERSION
        );
        $url = $this->server . '/index.php?p=/upgrade/getlist&' . http_build_query($param);
        $raw = get_url($url, '', '', true);
        if (! is_string($raw) || $raw === '') {
            json(0, '连接更新服务器发生错误，请稍后再试！');
        }
        $rs = json_decode($raw);
        // 兼容未上报 PHP 版本的旧客户端协议：data 仅接受数组（文件列表）或字符串（提示文案）
        if (! is_object($rs) || ! isset($rs->code)) {
            json(0, '连接更新服务器发生错误：响应无法解析');
        }
        if ($rs->code) {
            if (is_array($rs->data)) {
                return $rs->data;
            }
            $msg = is_string($rs->data) ? $rs->data : '服务器返回了无法处理的数据格式';
            json(1, $msg);
        } else {
            $msg = is_string($rs->data) ? $rs->data : '检查更新失败';
            json(0, $msg);
        }
    }

    // 获取文件
    private function getServerFile($source, $des)
    {
        $url = $this->server . '/index.php?p=/upgrade/getFile&branch=' . $this->branch;
        $data['path'] = $source;
        $file = str_replace(RUN_PATH . '/upgrade', '', $des); // 站点相对路径，便于定位具体文件
        $raw = get_url($url, $data, '', true);
        if (! is_string($raw) || $raw === '') {
            json(0, "更新文件 " . $file . " 获取失败!");
        }
        $rs = json_decode($raw);
        if (! is_object($rs) || ! isset($rs->code)) {
            json(0, "更新文件 " . $file . " 获取失败：响应无法解析");
        }
        if ($rs->code) {
            if (! is_string($rs->data)) {
                json(0, "更新文件 " . $file . " 获取失败：数据格式错误");
            }
            $bin = base64_decode($rs->data, true);
            if ($bin === false) {
                $bin = base64_decode($rs->data);
            }
            if ($bin === false) {
                json(0, "更新文件 " . $file . " 下载失败：内容解码失败");
            }
            if (file_put_contents($des, $bin) === false) {
                json(0, "更新文件 " . $file . " 下载失败!");
            }
            return true;
        }
        $msg = is_string($rs->data) ? $rs->data : ("更新文件 " . $file . " 获取失败!");
        json(0, $msg);
    }

    // 获取非文本文件
    private function getServerDown($source, $des)
    {
        $url = $this->server . $source;
        $file = str_replace(RUN_PATH . '/upgrade', '', $des); // 站点相对路径，便于定位具体文件
        if (($sfile = fopen($url, "rb")) && ($dfile = fopen($des, "wb"))) {
            while (! feof($sfile)) {
                $chunk = fread($sfile, 1024 * 8);
                if ($chunk === false) {
                    json(0, "更新文件 " . $file . " 下载失败!");
                }
                if ($chunk === '') {
                    break;
                }
                $fwrite = fwrite($dfile, $chunk);
                if ($fwrite === false) {
                    json(0, "更新文件 " . $file . " 下载失败!");
                }
            }
            if ($sfile) {
                fclose($sfile);
            }
            if ($dfile) {
                fclose($dfile);
            }
            return true;
        } else {
            json(0, "更新文件 " . $file . " 获取失败!");
        }
    }

    // 获取文件列表
    private function getLoaclList($path)
    {
        $files = scandir($path);
        foreach ($files as $value) {
            if ($value != '.' && $value != '..') {
                if (is_dir($path . '/' . $value)) {
                    $this->getLoaclList($path . '/' . $value);
                } else {
                    $file = $path . '/' . $value;
                    
                    // 避免中文乱码
                    if (! mb_check_encoding($file, 'utf-8')) {
                        $out_path = mb_convert_encoding($file, 'UTF-8', 'GBK');
                    } else {
                        $out_path = $file;
                    }
                    
                    $out_path = str_replace(RUN_PATH . '/upgrade', '', $out_path);
                    
                    $this->files[] = array(
                        'path' => $out_path,
                        'md5' => md5_file($file),
                        'ctime' => filemtime($file)
                    );
                }
            }
        }
        return $this->files;
    }

    // 比较程序本号
    private function compareVersion($sv, $cv)
    {
        if (empty($sv) || $sv == $cv) {
            return 0;
        }
        $sv = explode('.', $sv);
        $cv = explode('.', $cv);
        $len = count($sv) > count($cv) ? count($sv) : count($cv);
        for ($i = 0; $i < $len; $i ++) {
            $n1 = isset($sv[$i]) ? $sv[$i] : 0;
            $n2 = isset($cv[$i]) ? $cv[$i] : 0;
            if ($n1 > $n2) {
                return 1;
            } elseif ($n1 < $n2) {
                return 0;
            }
        }
        return 0;
    }
}
