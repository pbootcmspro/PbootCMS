<?php

declare(strict_types=1);

/**
 * 独立进程探测 IndexController::setLoginBlack()/checkLoginBlack() 的真实并发行为
 * （必须用真实 OS 子进程才能触发 flock 竞争，同进程 include 无法复现）
 *
 * 用法: php tests/scripts/login_black_probe.php <ip> <run_path> <lock_time> <lock_count> <lock_max> [mode]
 *   mode=set（默认）: 调用一次 setLoginBlack()
 *   mode=check: 调用一次 checkLoginBlack()，输出剩余秒数或空
 * 输出 JSON: {"ok":bool,"result":mixed}
 */

$ip = $argv[1];
$runPath = $argv[2];
$lockTime = (int) $argv[3];
$lockCount = (int) $argv[4];
$lockMax = (int) $argv[5];
$mode = isset($argv[6]) ? $argv[6] : 'set';

$sourcePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
define('ROOT_PATH', $runPath);
define('APP_PATH', $sourcePath . 'apps');
define('CORE_PATH', $sourcePath . 'core');
define('RUN_PATH', $runPath);
define('DOC_PATH', $sourcePath);
define('CONF_PATH', $sourcePath . 'config');
define('SITE_DIR', '');

$_SERVER['REMOTE_ADDR'] = $ip;
$_SERVER['HTTP_USER_AGENT'] = 'probe';
unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CLIENT_IP']);

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/function/file.php';
require CORE_PATH . '/function/helper.php';
require CORE_PATH . '/basic/Config.php';
require CORE_PATH . '/log/Builder.php';
require CORE_PATH . '/log/LogText.php';
require CORE_PATH . '/basic/Log.php';
require CORE_PATH . '/basic/Controller.php';
require APP_PATH . '/admin/controller/IndexController.php';

$ref = new ReflectionClass('core\basic\Config');
$prop = $ref->getProperty('configs');
if (PHP_VERSION_ID < 80100) {
    $prop->setAccessible(true);
}
$prop->setValue(null, array(
    'lock_time' => $lockTime,
    'lock_count' => $lockCount,
    'lock_max' => $lockMax,
    'log_record_type' => 'text',
));

$ctrlRef = new ReflectionClass('app\admin\controller\IndexController');
$ctrl = $ctrlRef->newInstanceWithoutConstructor();

$method = $ctrlRef->getMethod($mode === 'check' ? 'checkLoginBlack' : 'setLoginBlack');
if (PHP_VERSION_ID < 80100) {
    $method->setAccessible(true);
}
$result = $method->invoke($ctrl);

echo json_encode(array('ok' => (bool) $result || $result === false && $mode === 'check', 'result' => $result));
exit(0);
