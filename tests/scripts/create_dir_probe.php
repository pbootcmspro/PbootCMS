<?php

declare(strict_types=1);

/**
 * 独立进程探测 create_dir() 并发递归建同一多级目录
 *
 * 用法: php tests/scripts/create_dir_probe.php <target> <sync_dir>
 * 先写 sync_dir/ready_<pid>，等待 sync_dir/go 后再建目录
 * 输出 JSON: {"ok":bool,"is_dir":bool}
 */

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

$target = $argv[1];
$syncDir = $argv[2];

require dirname(__DIR__, 2) . '/core/function/file.php';

file_put_contents($syncDir . DIRECTORY_SEPARATOR . 'ready_' . getmypid(), '1');
$goFile = $syncDir . DIRECTORY_SEPARATOR . 'go';
$deadline = microtime(true) + 30;
while (! is_file($goFile)) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, "create_dir_probe: barrier timeout\n");
        exit(2);
    }
    usleep(1000);
}

$ok = create_dir($target);
$isDir = is_dir($target);

echo json_encode(array('ok' => $ok, 'is_dir' => $isDir));
exit($ok && $isDir ? 0 : 1);
