<?php

declare(strict_types=1);

/**
 * 独立进程按指定次数反复调用 write_file_atomic()，交替写入两份等长内容
 *
 * 用法:
 *   php tests/scripts/write_file_atomic_probe.php <file> <times> <size>
 * 输出 JSON: {"ok":bool,"writes":int}
 */

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED);

$file = $argv[1];
$times = (int) $argv[2];
$size = (int) $argv[3];

$sourcePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
define('CORE_PATH', $sourcePath . 'core');

require CORE_PATH . '/function/handle.php';
require CORE_PATH . '/function/file.php';

$contents = array(str_repeat('A', $size), str_repeat('B', $size));
$ok = true;
$writes = 0;
while ($writes < $times) {
    $ok = write_file_atomic($file, $contents[$writes % 2]) && $ok;
    $writes++;
}

echo json_encode(array('ok' => $ok, 'writes' => $writes));
