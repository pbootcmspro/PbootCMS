<?php

declare(strict_types=1);

/**
 * 独立进程探测 SpiderController + LogSpider 的真实并发行为
 *
 * 用法: php tests/scripts/spider_log_probe.php <run_path> <spiderlog> <url> <user_agent> [sync_dir]
 * 若提供 sync_dir：先写 ready_<pid>，等待 sync_dir/go 后再写入（首写并发屏障）
 * 输出 JSON: {"ok":bool,"written":bool,"log_file":string,"lines":int}
 */

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

$runPath = $argv[1];
$spiderlog = $argv[2];
$url = $argv[3];
$userAgent = $argv[4];
$syncDir = isset($argv[5]) ? $argv[5] : '';

$sourcePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
define('ROOT_PATH', $runPath);
define('APP_PATH', $sourcePath . 'apps');
define('CORE_PATH', $sourcePath . 'core');
define('RUN_PATH', $runPath);
define('DOC_PATH', $sourcePath);
define('CONF_PATH', $sourcePath . 'config');
define('SITE_DIR', '');

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = $userAgent;
unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CLIENT_IP']);

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/function/file.php';
require CORE_PATH . '/function/helper.php';
require CORE_PATH . '/basic/Config.php';
require CORE_PATH . '/log/LogSpider.php';
require CORE_PATH . '/basic/Controller.php';
require APP_PATH . '/home/controller/SpiderController.php';

if ($syncDir !== '') {
    if (! is_dir($syncDir)) {
        @mkdir($syncDir, 0777, true);
    }
    $readyFile = $syncDir . DIRECTORY_SEPARATOR . 'ready_' . getmypid();
    file_put_contents($readyFile, '1');
    $goFile = $syncDir . DIRECTORY_SEPARATOR . 'go';
    $deadline = microtime(true) + 30;
    while (! is_file($goFile)) {
        if (microtime(true) > $deadline) {
            fwrite(STDERR, "spider_log_probe: barrier timeout\n");
            exit(2);
        }
        usleep(1000);
    }
}

$ref = new ReflectionClass('core\basic\Config');
$prop = $ref->getProperty('configs');
if (PHP_VERSION_ID < 80100) {
    $prop->setAccessible(true);
}
$prop->setValue(null, array(
    'spiderlog' => $spiderlog,
));

$spider = new \app\home\controller\SpiderController($url);
$spider->index();

$logFile = $runPath . '/log/spider/' . date('Ymd') . '.log';
$lines = 0;
if (file_exists($logFile)) {
    $content = file_get_contents($logFile);
    if ($content !== false && $content !== '') {
        $lines = substr_count($content, PHP_EOL);
    }
}

echo json_encode(array(
    'ok' => true,
    'written' => $lines > 0,
    'log_file' => $logFile,
    'lines' => $lines,
));
exit(0);
