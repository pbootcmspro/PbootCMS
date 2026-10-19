<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author XingMeng
 * @email hnxsh@foxmail.com
 * @date 2017年3月13日
 *  默认主页
 */
namespace app\admin\controller;

use core\basic\Controller;
use app\admin\model\IndexModel;
use app\common\VisitsCounter;

class IndexController extends Controller
{

    private $model;

    public function __construct()
    {
        $this->model = new IndexModel();
    }

    // 登录页面
    public function index()
    {
        if (session('sid')) {
            location(url('admin/Index/home'));
        }
        $this->assign('admin_check_code', $this->config('admin_check_code'));
        $this->display('index.html');
    }

    // 主页面
    public function home()
    {
        // 手动修改数据名称
        if (get('action') == 'moddb') {
            if ($this->modDB()) {
                alert_back('修改成功！');
            } else {
                alert_back('修改失败！');
            }
        }

        // 删除修改后老数据库（上一步无法直接修改删除）
        if (issetSession('deldb')) {
            @unlink(ROOT_PATH . session('deldb'));
            unset($_SESSION['deldb']);
        }

        $dbsecurity = true;
        // 如果是sqlite数据库，并且路径为默认的，则标记为不安全
        if (get_db_type() == 'sqlite') {
            // 数据库配置含有默认名字则进行修改
            if (strpos($this->config('database.dbname'), 'pbootcms') !== false) {
                if (get_user_ip() != '127.0.0.1' && $this->modDB()) { // 非本地测试时尝试自动修改数据库名称
                    $dbsecurity = true;
                } else {
                    $dbsecurity = false;
                }
            }
        }

        $this->assign('dbsecurity', $dbsecurity);

        if (!session('pwsecurity')) {
            location(url('/admin/Index/ucenter'));
        }

        $this->assign('server', get_server_info());
        $this->assign('branch', $this->config('upgrade_branch') == '3.X.dev' ? '3.X.dev' : '3.X');
        $this->assign('revise', $this->config('revise_version') ?: '0');
        $this->assign('snuser', $this->config('sn_user') ?: '0');
        $this->assign('site', get_http_url());

        $this->assign('user_info', $this->model->getUserInfo(session('ucode')));

        $this->assign('sum_msg', model('admin.content.Message')->getCount());

        // 内容模型菜单
        $model = model('admin.content.Model');
        $models = $model->getModelMenu();
        foreach ($models as $key => $value) {
            $models[$key]->count = $model->getModelCount($value->mcode)->count;
        }

        $this->assign('model_msg', $models);
        $this->display('system/home.html');
    }

    // 异步登录验证
    public function login()
    {
        if (!$_POST) {
            return;
        }

        // 在安装了gd库时才执行验证码验证
        if (extension_loaded("gd") && $this->config('admin_check_code') && strtolower(post('checkcode', 'var')) != session('checkcode')) {
            json(0, '验证码错误！');
        }

        // 就收数据
        $username = post('username');
        $password = post('password');
        $checkcode = post('checkcode');
        $formcheck = post('formcheck');

        if (!preg_match('/^[\x{4e00}-\x{9fa5}\w\-\.@]+$/u', $username)) {
            json(0, '用户名含有不允许的特殊字符！');
        }

        if (!$username) {
            json(0, '用户名不能为空！');
        }

        if (!$password) {
            json(0, '密码不能为空！');
        }

        if ($this->config('admin_check_code') &&
            !$checkcode
        ) {
            json(0, '验证码不能为空！');
        }

        if (!$formcheck) {
            json(0, '表单提交校验失败,请刷新后重试！');
        }

        if (!!$time = $this->checkLoginBlack()) {
            $this->log('登录锁定!');
            json(0, '您登录失败次数太多已被锁定，请' . $time . '秒后再试！');
        }

        // 执行用户登录
        $where = array(
            'username' => $username,
            'password' => encrypt_string($password)
        );

        // 判断数据库写入权限
        if ((get_db_type() == 'sqlite') && !is_writable(ROOT_PATH . $this->config('database.dbname'))) {
            json(0, '数据库目录写入权限不足！');
        }

        if (!!$login = $this->model->login($where)) {

            session_regenerate_id(true);
            session('sid', encrypt_string(session_id() . $login->id)); // 会话标识
            session('M', M);

            session('id', $login->id); // 用户id
            session('ucode', $login->ucode); // 用户编码
            session('username', $login->username); // 用户名
            session('realname', $login->realname); // 真实名字

            if ($where['password'] != '14e1b600b1fd579f47433b88e8d85291') {
                session('pwsecurity', true);
            }

            session('acodes', $login->acodes); // 用户管理区域
            if ($login->acodes) { // 当前显示区域
                session('acode', $login->acodes[0]);
            } else {
                session('acode', '');
            }

            session('rcodes', $login->rcodes); // 用户角色代码表
            session('levels', $login->levels); // 用户权限URL列表
            session('menu_tree', $login->menus); // 菜单树
            session('area_map', $login->area_map); // 区域代码名称映射表
            session('area_tree', $login->area_tree); // 用户区域树

            $this->log('登录成功!');
            json(1, url('admin/Index/home'));
        } else {
            $this->setLoginBlack();
            $this->log('登录失败!');
            session('checkcode', mt_rand(10000, 99999)); // 登录失败，随机打乱原有验证码
            json(0, '用户名或密码错误！');
        }
    }

    // 退出登录
    public function loginOut()
    {
        session_unset();
        location(url('/admin/Index/index'));
    }

    // 用户中心，修改密码
    public function ucenter()
    {
        $init = $this->isInitMode();
        if ($_POST) {
            $username = post('username'); // 用户名
            $realname = post('realname'); // 真实姓名
            $cpassword = post('cpassword'); // 现在密码
            $password = post('password'); // 新密码
            $rpassword = post('rpassword'); // 确认密码

            if (!$username) {
                alert_back('用户名不能为空！');
            }
            if (!$cpassword) {
                alert_back('当前密码不能为空！');
            }

            if (!preg_match('/^[\x{4e00}-\x{9fa5}\w\-\.@]+$/u', $username)) {
                alert_back('用户名含有不允许的特殊字符！');
            }

            $data = array(
                'username' => $username,
                'realname' => $realname,
                'update_user' => $username
            );

            // 如果有修改密码，则添加数据
            if ($password) {
                if ($password != $rpassword) {
                    alert_back('确认密码不正确！');
                }
                $data['password'] = encrypt_string($password);
            }

            // 检查现有密码
            if ($this->model->checkUserPwd(encrypt_string($cpassword))) {
                if ($init && post('db_type') == 'mysql') {
                    $this->switchMysql($data);
                }
                if ($this->model->modUserInfo($data)) {
                    if (isset($data['password'])) {
                        session('pwsecurity', $data['password'] != '14e1b600b1fd579f47433b88e8d85291');
                    }
                    session('username', post('username'));
                    session('realname', post('realname'));
                    $this->log('用户资料成功！');
                    success('用户资料修改成功！', -1);
                }
            } else {
                $this->log('用户资料修改时当前密码错误！');
                alert_location('当前密码错误！', -1);
            }
        }
        $this->assign('init', $init);
        if ($init) {
            $env = $this->initEnv();
            $this->assign('init_env', $env['items']);
            $this->assign('init_mysql', $env['mysql']);
            $this->assign('init_mysql_note', $env['mysql_note']);
        }
        $this->display('system/ucenter.html');
    }

    // 切换显示的数据区域
    public function area()
    {
        if ($_POST) {
            $acode = post('acode');
            if (in_array($acode, session('acodes'))) {
                session('acode', $acode);
                cookie('lg', $acode); // 同步切换前台语言
            }
            location(url('admin/Index/home'));
        }
    }

    // 清理缓存（常规保留 runtime/image 缩略图；仅 delall 全量清理）
    public function clearCache()
    {
        $delall = get('delall');
        // delall 会删除 runtime/data，归库失败必须中止，避免待刷访问量被抹掉
        if ($delall && ! VisitsCounter::flushAll()) {
            $this->log('清理缓存前归库访问量失败，已中止全量清理！');
            alert_back('清理缓存前归库访问量失败，已中止全量清理以避免丢失计数！', 0);
        }
        $rs = purge_runtime_cache(RUN_PATH, 'all', $delall);
        cache_config(); // 清理缓存后立即生成新的配置
        if ($rs) {
            if (extension_loaded('Zend OPcache')) {
                opcache_reset(); // 在启用了OPcache加速器时同时清理
            }
            $this->log('清理缓存成功！');
            alert_back('清理缓存成功！', 1);
        } else {
            $this->log('清理缓存失败！');
            alert_back('清理缓存失败！', 0);
        }
    }

    // 清理系统缓存（常规保留 runtime/image 与 cache；仅 delall 全量清理）
    public function clearOnlySysCache()
    {
        $delall = get('delall');
        // delall 会删除 runtime/data，归库失败必须中止，避免待刷访问量被抹掉
        if ($delall && ! VisitsCounter::flushAll()) {
            $this->log('清理缓存前归库访问量失败，已中止全量清理！');
            alert_back('清理缓存前归库访问量失败，已中止全量清理以避免丢失计数！', 0);
        }
        $rs = purge_runtime_cache(RUN_PATH, 'sys', $delall);
        cache_config(); // 清理缓存后立即生成新的配置
        if ($rs) {
            if (extension_loaded('Zend OPcache')) {
                opcache_reset(); // 在启用了OPcache加速器时同时清理
            }
            $this->log('清理缓存成功！');
            alert_back('清理缓存成功！', 1);
        } else {
            $this->log('清理缓存失败！');
            alert_back('清理缓存失败！', 0);
        }
    }
	
    // 清理会话
    public function clearSession()
    {
        ignore_user_abort(true); // 后台运行
        set_time_limit(7200);
        ob_start();
        $output['code'] = 1;
        $output['data'] = '执行成功，后台自动清理中!';
        $output['tourl'] = '';
        echo json_encode($output);
        ob_end_flush();
        flush();
        $rs = path_delete(RUN_PATH . '/session', false, array(
            'sess_' . session_id()
        ));
    }

    // 文件上传方法
    public function upload()
    {
        $upload = upload('upload');
        if (is_array($upload)) {
            json(1, $upload);
        } else {
            json(0, $upload);
        }
    }

    // 黑名单文件路径
    private function getLoginBlackFile()
    {
        return RUN_PATH . '/data/' . md5('login_black') . '.php';
    }

    // 安全读取黑名单，文件损坏时降级为空数组以便重建
    private function loadLoginBlack($ip_black)
    {
        if (! file_exists($ip_black)) {
            return array();
        }
        try {
            $data = @include $ip_black; // 数据文件被截断时会抛出 ParseError
        } catch (\Throwable $e) {
            $data = false;
        }
        if (! is_array($data)) {
            return array();
        }
        foreach ($data as $ip => $item) {
            if (! is_array($item) || ! isset($item['time']) || ! isset($item['count']) || ! is_numeric($item['time']) || ! is_numeric($item['count'])) {
                unset($data[$ip]);
            }
        }
        return $data;
    }

    // 原子写入黑名单：临时文件 + rename 替换，避免并发截断
    private function writeLoginBlack($ip_black, array $data)
    {
        check_dir(dirname($ip_black), true);
        $content = "<?php\nreturn " . var_export($data, true) . ";";
        $tmp = $ip_black . '.' . \process_instance_id() . '.' . mt_rand() . '.tmp';
        if (file_put_contents($tmp, $content, LOCK_EX) === false) {
            return false;
        }
        if (! @rename($tmp, $ip_black)) { // 同目录 rename 为原子替换
            @unlink($tmp);
            return false;
        }
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($ip_black, true); // 防止 opcache 读到旧内容
        }
        return true;
    }

    // 检查是否在黑名单
    private function checkLoginBlack()
    {
        // 读取黑名单
        $ip_black = $this->getLoginBlackFile();
        $data = $this->loadLoginBlack($ip_black);
        if ($data) {
            $user_ip = get_user_ip();
            $lock_time = $this->config('lock_time') ?: 900;
            $lock_count = $this->config('lock_count') ?: 5;
            if (isset($data[$user_ip]) && $data[$user_ip]['count'] >= $lock_count && time() - $data[$user_ip]['time'] < $lock_time) {
                return $lock_time - (time() - $data[$user_ip]['time']); // 返回剩余秒数
            }
        }
        return false;
    }

    // 添加登录黑名单
    private function setLoginBlack()
    {
        $ip_black = $this->getLoginBlackFile();
        check_dir(dirname($ip_black), true);
        $lock_time = $this->config('lock_time') ?: 900;
        $lock_max = $this->config('lock_max') ?: 1000;
        $user_ip = get_user_ip();

        // 通过锁文件串行化整个「读-改-写」过程，防止并发丢失有效记录
        $fp = @fopen($ip_black . '.lock', 'c');
        if ($fp === false || ! flock($fp, LOCK_EX)) {
            if ($fp) {
                fclose($fp);
            }
            return false;
        }

        $ok = false;
        $evictedIps = array();
        try {
            $data = $this->loadLoginBlack($ip_black);
            $now = time();

            // 写入前清理过期记录
            foreach ($data as $ip => $item) {
                if (! isset($item['time']) || ! isset($item['count']) || $now - $item['time'] >= $lock_time) {
                    unset($data[$ip]);
                }
            }

            if (isset($data[$user_ip])) {
                $data[$user_ip] = array(
                    'time' => $now,
                    'count' => $data[$user_ip]['count'] + 1
                );
            } else {
                // 满员时按 time 淘汰最旧条目（近似 LRU），为新 IP 腾出空位
                while (count($data) >= $lock_max) {
                    $oldestIp = null;
                    $oldestTime = null;
                    foreach ($data as $ip => $item) {
                        if ($oldestTime === null || $item['time'] < $oldestTime) {
                            $oldestTime = $item['time'];
                            $oldestIp = $ip;
                        }
                    }
                    if ($oldestIp === null) {
                        break;
                    }
                    unset($data[$oldestIp]);
                    $evictedIps[] = $oldestIp;
                }
                $data[$user_ip] = array(
                    'time' => $now,
                    'count' => 1
                );
            }

            $ok = $this->writeLoginBlack($ip_black, $data);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        if ($ok) {
            foreach ($evictedIps as $oldestIp) {
                $this->log('登录黑名单已满，淘汰最旧IP：' . $oldestIp . '，腾出给：' . $user_ip, 'warning');
            }
        }

        return $ok;
    }

    // 修改数据库名称
    private function modDB()
    {
        $file = CONF_PATH . '/database.php';
        $sname = $this->config('database.dbname');
        $dname = '/data/' . get_uniqid() . '.db';
        $sconfig = file_get_contents($file);
        $dconfig = str_replace($sname, $dname, $sconfig);
        if (file_put_contents($file, $dconfig)) {
            if (!copy(ROOT_PATH . $sname, ROOT_PATH . $dname)) {
                file_put_contents($file, $sconfig); // 回滚配置
            } else {
                session('deldb', $sname);
                return true;
            }
        }
        return false;
    }

    private function isInitMode()
    {
        return ! session('pwsecurity') && session('id') == 1 && $this->isDefaultDb($this->config('database'));
    }

    private function isDefaultDb($db)
    {
        if (! is_array($db)) {
            return false;
        }
        $type = isset($db['type']) ? $db['type'] : '';
        $name = isset($db['dbname']) ? $db['dbname'] : '';
        return ($type == 'sqlite' || $type == 'pdo_sqlite') && strpos($name, 'pbootcms') !== false;
    }

    private function initEnv()
    {
        $phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');
        $sqliteOk = extension_loaded('sqlite3') || extension_loaded('pdo_sqlite');
        $mysqliOk = extension_loaded('mysqli');
        $file = CONF_PATH . '/database.php';
        $configOk = is_writable($file) || is_writable(CONF_PATH);
        $dataOk = is_dir(ROOT_PATH . '/data') && is_writable(ROOT_PATH . '/data');
        $runtimeOk = is_dir(RUN_PATH) && is_writable(RUN_PATH);
        $upload = ROOT_PATH . '/static/upload';
        $uploadOk = is_dir($upload) && is_writable($upload);
        $mysqlNote = '';
        if (! $mysqliOk && ! $configOk) {
            $mysqlNote = '未启用 mysqli 扩展，且数据库配置不可写，不能切换到 MySQL。';
        } elseif (! $mysqliOk) {
            $mysqlNote = '未启用 mysqli 扩展，不能切换到 MySQL。';
        } elseif (! $configOk) {
            $mysqlNote = '数据库配置不可写，不能切换到 MySQL。';
        }
        return array(
            'mysql' => $mysqliOk && $configOk,
            'mysql_note' => $mysqlNote,
            'items' => array(
                (object) array(
                    'name' => 'PHP 版本',
                    'note' => $phpOk ? ('当前 ' . PHP_VERSION) : ('当前 ' . PHP_VERSION . '，建议不低于 7.4。'),
                    'ok' => $phpOk
                ),
                (object) array(
                    'name' => 'SQLite 扩展',
                    'note' => $sqliteOk ? '已启用 sqlite3 或 pdo_sqlite。' : '未启用 sqlite3 或 pdo_sqlite。',
                    'ok' => $sqliteOk
                ),
                (object) array(
                    'name' => 'mysqli 扩展',
                    'note' => $mysqliOk ? '已启用，可以切换到 MySQL。' : '未启用，不能切换到 MySQL。',
                    'ok' => $mysqliOk
                ),
                (object) array(
                    'name' => '数据库配置可写',
                    'note' => $configOk ? 'config/database.php 可以写入。' : 'config/database.php 不可写，不能切换到 MySQL。',
                    'ok' => $configOk
                ),
                (object) array(
                    'name' => 'data 目录可写',
                    'note' => $dataOk ? 'data/ 可以写入。' : 'data/ 不可写，SQLite 写入和随机改名会失败。',
                    'ok' => $dataOk
                ),
                (object) array(
                    'name' => 'runtime 目录可写',
                    'note' => $runtimeOk ? 'runtime/ 可以写入。' : 'runtime/ 不可写，缓存和会话会失败。',
                    'ok' => $runtimeOk
                ),
                (object) array(
                    'name' => '上传目录可写',
                    'note' => $uploadOk ? 'static/upload 可以写入。' : 'static/upload 不可写，上传文件会失败。',
                    'ok' => $uploadOk
                )
            )
        );
    }

    private function checkMysqlInput($db)
    {
        if (! is_array($db)) {
            return '数据库连接信息不正确。';
        }
        $host = isset($db['host']) ? $db['host'] : '';
        if (! is_string($host) || ! preg_match('/^[A-Za-z0-9.\-:]{1,255}$/', $host)) {
            return '数据库服务器地址不正确。';
        }
        $port = isset($db['port']) ? $db['port'] : 0;
        if (! is_int($port) && ! (is_string($port) && preg_match('/^[0-9]+$/', $port))) {
            return '数据库端口不正确。';
        }
        $port = (int) $port;
        if ($port < 1 || $port > 65535) {
            return '数据库端口不正确。';
        }
        $user = isset($db['user']) ? $db['user'] : '';
        if (! is_string($user) || ! preg_match('/^[A-Za-z0-9_.\-@]{1,32}$/', $user)) {
            return '数据库用户名不正确。';
        }
        $dbname = isset($db['dbname']) ? $db['dbname'] : '';
        if (! is_string($dbname) || ! preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $dbname)) {
            return '数据库名只能包含字母、数字、下划线和短横线。';
        }
        $passwd = isset($db['passwd']) ? $db['passwd'] : '';
        if (! is_string($passwd)) {
            return '数据库密码不正确。';
        }
        if (strlen($passwd) > 128) {
            return '数据库密码长度不能超过 128 字节。';
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $passwd)) {
            return '数据库密码含有不允许的控制字符。';
        }
        return '';
    }

    private function switchMysql($data)
    {
        if (! $this->isInitMode()) {
            alert_back('请刷新页面后重试。');
        }
        if (! is_array($data) || ! isset($data['password']) || $data['password'] === '14e1b600b1fd579f47433b88e8d85291') {
            alert_back('切换到 MySQL 时必须设置新密码，且不能使用默认密码。');
        }
        $username = isset($data['username']) ? (string) $data['username'] : '';
        $realname = isset($data['realname']) ? (string) $data['realname'] : '';
        $fieldError = $this->adminFieldError($username, $realname);
        if ($fieldError !== '') {
            alert_back($fieldError);
        }
        if (! extension_loaded('mysqli')) {
            alert_back('未启用 mysqli 扩展，无法切换到 MySQL。');
        }
        $file = CONF_PATH . '/database.php';
        if (! is_writable($file) && ! is_writable(CONF_PATH)) {
            alert_back('数据库配置不可写，无法切换到 MySQL。');
        }
        $port = post('db_port');
        if ($port === null || $port === '') {
            $port = 3306;
        }
        // 数据库密码必须原样使用。post() 会先转义 HTML 再加反斜杠，会把密码改坏。
        // 该值只作为 mysqli 连接参数和 var_export 的输入，不进入 HTML、SQL 或日志。
        $passwd = '';
        if (isset($_POST['db_passwd']) && is_string($_POST['db_passwd'])) {
            $passwd = $_POST['db_passwd'];
        }
        $db = array(
            'host' => post('db_host'),
            'port' => $port,
            'user' => post('db_user'),
            'passwd' => $passwd,
            'dbname' => post('db_name')
        );
        $error = $this->checkMysqlInput($db);
        if ($error !== '') {
            alert_back($error);
        }
        $db['port'] = (int) $db['port'];

        $fp = @fopen(RUN_PATH . '/archive/init_db.lock', 'c');
        if (! $fp || ! flock($fp, LOCK_EX | LOCK_NB)) {
            if ($fp) {
                fclose($fp);
            }
            alert_back('正在切换，请稍后重试。');
        }
        $cfg = require $file;
        $current = (is_array($cfg) && isset($cfg['database'])) ? $cfg['database'] : array();
        if (! $this->isDefaultDb($current)) {
            $this->abortMysql(null, $fp, '请刷新页面后重试。', false);
        }

        $conn = \core\database\Mysqli::getInstance()->conn(array(
            'host' => $db['host'],
            'user' => $db['user'],
            'passwd' => $db['passwd'],
            'port' => $db['port'],
            'dbname' => $db['dbname']
        ));
        $tables = $this->ayTables($conn);
        if ($tables === false || $tables) {
            $this->abortMysql($conn, $fp, '目标库已存在 ay_ 数据表，已取消切换。', false);
        }
        if (! $this->importSql($conn, ROOT_PATH . '/static/backup/sql/pbootcms.sql')) {
            error_log('init mysql import failed: ' . $conn->errno . ' ' . $conn->error);
            $this->abortMysql($conn, $fp, '导入初始数据失败。', true);
        }

        $password = (string) $data['password'];
        $updateUser = $username;
        $stmt = $conn->prepare("UPDATE ay_user SET username=?, realname=?, password=?, update_user=? WHERE ucode='10001'");
        if (! $stmt) {
            error_log('init mysql admin prepare failed: ' . $conn->errno . ' ' . $conn->error);
            $this->abortMysql($conn, $fp, '写入管理员账号失败。', true);
        }
        $stmt->bind_param('ssss', $username, $realname, $password, $updateUser);
        $saved = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if (! $saved || $affected < 1) {
            error_log('init mysql admin write failed: ' . $conn->errno . ' ' . $conn->error);
            $this->abortMysql($conn, $fp, '写入管理员账号失败。', true);
        }

        $content = $this->buildDbConfig($cfg, $db);
        if (! write_file_atomic($file, $content)) {
            $this->abortMysql($conn, $fp, '写入数据库配置失败。', true);
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
        $conn->close();
        flock($fp, LOCK_UN);
        fclose($fp);
        path_delete(RUN_PATH . '/config');
        $logUser = session('username');
        session_unset();
        $this->writeSwitchLog($logUser);
        success('数据库已切换为 MySQL，请使用新账号重新登录！', url('/admin/Index/index'));
    }

    private function adminFieldError($username, $realname)
    {
        $usernameLen = mb_strlen((string) $username, 'UTF-8');
        if ($usernameLen === false || $usernameLen > 30) {
            return '用户名不能超过 30 个字符。';
        }
        $realnameLen = mb_strlen((string) $realname, 'UTF-8');
        if ($realnameLen === false || $realnameLen > 30) {
            return '真实姓名不能超过 30 个字符。';
        }
        return '';
    }

    private function writeSwitchLog($username)
    {
        if ($this->config('database.type') == 'pdo_sqlite') {
            $driver = '\core\database\Pdo';
        } else {
            $driver = '\core\database\Sqlite';
        }
        $driver::setFailSoft(true);
        \core\basic\Log::write('数据库已切换为 MySQL。', 'info', $username);
        $failed = $driver::hadFailSoftError();
        $driver::setFailSoft(false);
        if ($failed) {
            error_log('init mysql syslog failed');
        }
    }

    private function abortMysql($conn, $fp, $message, $drop)
    {
        if ($drop && $conn) {
            $this->dropAyTables($conn);
        }
        if ($conn) {
            $conn->close();
        }
        if ($fp) {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        alert_back($message);
    }

    private function importSql($conn, $file)
    {
        $sql = file_get_contents($file);
        if ($sql === false || ! $conn->multi_query($sql)) {
            return false;
        }
        do {
            if ($res = $conn->store_result()) {
                $res->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        return $conn->errno === 0;
    }

    private function ayTables($conn)
    {
        $result = $conn->query("SHOW TABLES LIKE 'ay\\_%'");
        if ($result === false) {
            return false;
        }
        $names = array();
        while ($row = $result->fetch_row()) {
            $names[] = $row[0];
        }
        $result->free();
        return $names;
    }

    private function dropAyTables($conn)
    {
        $names = $this->ayTables($conn);
        if (! is_array($names)) {
            return false;
        }
        foreach ($names as $name) {
            if (! preg_match('/^ay_[A-Za-z0-9_]+$/', $name)) {
                continue;
            }
            if ($conn->query('DROP TABLE IF EXISTS `' . $name . '`') === false) {
                return false;
            }
        }
        return true;
    }

    private function buildDbConfig(array $cfg, array $db)
    {
        if (! isset($cfg['database']) || ! is_array($cfg['database'])) {
            $cfg['database'] = array();
        }
        $cfg['database'] = array_merge($cfg['database'], array(
            'type' => 'mysqli',
            'host' => $db['host'],
            'user' => $db['user'],
            'passwd' => $db['passwd'],
            'port' => (string) $db['port'],
            'dbname' => $db['dbname']
        ));
        return "<?php\nreturn " . var_export($cfg, true) . ";\n";
    }
}
