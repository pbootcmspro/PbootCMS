<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers write_file_atomic()
 *
 * fix #283 回归：编译文件、页面缓存和配置缓存并发重写时，读方只能读到完整的旧内容或新内容，
 * 不会读到空文件或半截文件；写入后不残留临时文件
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}
require_once CORE_PATH . '/function/file.php';

/** proc_open 在 PHP 7/8 下可能返回 resource 或 object */
function write_atomic_proc_ok($proc)
{
    return $proc !== false && (is_resource($proc) || is_object($proc));
}

function write_atomic_start_probe(string $file, int $times, int $size): array
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'write_file_atomic_probe.php';
    // Windows PHP 7.x：勿对 PHP 可执行文件 escapeshellarg，否则 proc_open 报路径语法错误
    $args = array($script, $file, (string) $times, (string) $size);
    $cmd = $php . ' ' . implode(' ', array_map('escapeshellarg', $args));
    $pipes = array();
    $proc = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    return array($proc, $pipes);
}

function write_atomic_tmp_dir(string $suffix): string
{
    $dir = sys_get_temp_dir() . '/pboot_write_atomic_' . $suffix . '_' . getmypid();
    path_delete($dir, true);
    mkdir($dir, 0777, true);
    return $dir;
}

return TestAssert::runSuite(function () {
    echo "=== concurrent rewrite: readers never see torn content ===\n";

    $size = 256 * 1024;
    $dir = write_atomic_tmp_dir('race');
    $file = $dir . '/target.php';
    $full = array(str_repeat('A', $size) => true, str_repeat('B', $size) => true);
    TestAssert::true(write_file_atomic($file, str_repeat('A', $size)), 'seed write succeeds');

    $runs = array();
    for ($i = 0; $i < 2; $i++) {
        $runs[] = write_atomic_start_probe($file, 2000, $size);
    }

    // 子进程写入期间持续读取，直到全部写入进程退出
    $reads = 0;
    $torn = 0;
    $running = true;
    while ($running) {
        $content = file_get_contents($file);
        $reads++;
        if (! isset($full[$content])) {
            $torn++;
        }
        $running = false;
        foreach ($runs as $run) {
            if (write_atomic_proc_ok($run[0]) && proc_get_status($run[0])['running']) {
                $running = true;
            }
        }
    }

    $writes = 0;
    foreach ($runs as $run) {
        list($proc, $pipes) = $run;
        if (! write_atomic_proc_ok($proc)) {
            TestAssert::true(false, 'writer probe started');
            continue;
        }
        $stdout = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        $result = json_decode(trim($stdout), true);
        TestAssert::true(is_array($result) && $result['ok'] === true, 'writer probe reports all writes ok (stdout: ' . var_export($stdout, true) . ')');
        $writes += is_array($result) ? (int) $result['writes'] : 0;
    }

    echo "reads=$reads writes=$writes\n";
    TestAssert::same(4000, $writes, 'writers performed all rewrites');
    TestAssert::true($reads > 20, 'reader performed enough reads during rewrites');
    TestAssert::same(0, $torn, 'no empty or truncated content observed');
    TestAssert::same(array(), glob($file . '.*.tmp'), 'no temp files left behind');
    path_delete($dir, true);
    @rmdir($dir);

    echo "=== overwrite replaces content of the target path ===\n";

    $dir = write_atomic_tmp_dir('overwrite');
    $file = $dir . '/cache.html';
    TestAssert::true(write_file_atomic($file, 'old'), 'first write succeeds');
    TestAssert::true(write_file_atomic($file, 'new'), 'overwrite succeeds');
    TestAssert::same('new', file_get_contents($file), 'overwrite replaces content');
    TestAssert::true(write_file_atomic($file, ''), 'empty content is written without error');
    TestAssert::same('', file_get_contents($file), 'empty content persisted');
    TestAssert::same(array(), glob($file . '.*.tmp'), 'no temp files left behind after overwrite');
    path_delete($dir, true);
    @rmdir($dir);

    echo "=== unwritable target directory: returns false, no temp residue ===\n";

    $dir = write_atomic_tmp_dir('missing');
    $file = $dir . '/not-exists/target.php';
    $level = error_reporting(0);
    $result = write_file_atomic($file, 'x');
    error_reporting($level);
    TestAssert::false($result, 'write into missing directory returns false');
    TestAssert::false(is_dir($dir . '/not-exists'), 'missing directory is not created');
    path_delete($dir, true);
    @rmdir($dir);
});
