<?php

/**
 * 尝试非阻塞独占锁。拿到锁输出 LOCKED，否则输出 BUSY。
 */

$path = isset($argv[1]) ? $argv[1] : '';
$fp = $path !== '' ? @fopen($path, 'c') : false;
if (! $fp || ! flock($fp, LOCK_EX | LOCK_NB)) {
    echo "BUSY\n";
    exit(0);
}
echo "LOCKED\n";
flock($fp, LOCK_UN);
fclose($fp);
