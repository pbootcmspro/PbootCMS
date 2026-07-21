<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/admin/controller/IndexController.php checkLoginBlack/setLoginBlack
 *
 * fix #17 回归：登录黑名单并发写入不丢失记录、文件损坏安全降级、满员淘汰最旧条目
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}
require_once CORE_PATH . '/function/file.php';

function login_black_command(string $ip, string $runPath, int $lockTime, int $lockCount, int $lockMax, string $mode): string
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'login_black_probe.php';
    $args = array($php, $script, $ip, $runPath, (string) $lockTime, (string) $lockCount, (string) $lockMax, $mode);
    return implode(' ', array_map('escapeshellarg', $args));
}

function login_black_start_probe(string $ip, string $runPath, int $lockTime, int $lockCount, int $lockMax, string $mode): array
{
    $descriptors = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $pipes = array();
    $proc = proc_open(login_black_command($ip, $runPath, $lockTime, $lockCount, $lockMax, $mode), $descriptors, $pipes);
    return array($proc, $pipes);
}

function login_black_run(string $ip, string $runPath, int $lockTime, int $lockCount, int $lockMax, string $mode): array
{
    list($proc, $pipes) = login_black_start_probe($ip, $runPath, $lockTime, $lockCount, $lockMax, $mode);
    if (! is_resource($proc)) {
        return array('stdout' => '', 'stderr' => '', 'exit_code' => -1);
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return array('stdout' => $stdout, 'stderr' => $stderr, 'exit_code' => proc_close($proc));
}

/** 并发启动 N 个真实 OS 子进程，各自用不同 IP 调用一次 setLoginBlack() */
function login_black_concurrent_run(array $ips, string $runPath, int $lockTime, int $lockCount, int $lockMax): array
{
    $runs = array();
    foreach ($ips as $ip) {
        $runs[] = login_black_start_probe($ip, $runPath, $lockTime, $lockCount, $lockMax, 'set');
    }

    $exitCodes = array();
    foreach ($runs as $run) {
        list($proc, $pipes) = $run;
        if (! is_resource($proc)) {
            $exitCodes[] = -1;
            continue;
        }
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCodes[] = proc_close($proc);
    }
    return $exitCodes;
}

function login_black_read_data(string $runPath): array
{
    $file = $runPath . '/data/' . md5('login_black') . '.php';
    if (! file_exists($file)) {
        return array();
    }
    $data = @include $file;
    return is_array($data) ? $data : array();
}

function login_black_tmp_dir(string $suffix): string
{
    $dir = sys_get_temp_dir() . '/pboot_login_black_test_' . $suffix . '_' . getmypid();
    path_delete($dir);
    return $dir;
}

return TestAssert::runSuite(function () {
    echo "=== concurrent setLoginBlack(): 60 distinct IPs, no data loss ===\n";

    $runPath = login_black_tmp_dir('concurrency');
    $ips = array();
    for ($i = 1; $i <= 60; $i++) {
        $ips[] = "10.0.0.$i";
    }

    $codes = login_black_concurrent_run($ips, $runPath, 900, 5, 1000);
    TestAssert::true(! in_array(false, array_map(function ($c) {
        return $c === 0;
    }, $codes), true), 'all 60 probe processes exited 0');

    $data = login_black_read_data($runPath);
    TestAssert::same(60, count($data), 'no data loss: all 60 concurrent IPs persisted');

    $missing = array();
    $wrongCount = array();
    foreach ($ips as $ip) {
        if (! isset($data[$ip])) {
            $missing[] = $ip;
        } elseif ($data[$ip]['count'] !== 1) {
            $wrongCount[] = $ip;
        }
    }
    TestAssert::same(array(), $missing, 'no IP missing after 60-way concurrent write');
    TestAssert::same(array(), $wrongCount, 'every IP recorded with count=1 (no double counting)');

    path_delete($runPath);

    echo "=== checkLoginBlack(): corrupted blacklist file degrades to no-lock instead of fatal ===\n";

    $runPath = login_black_tmp_dir('corrupt');
    $dataDir = $runPath . '/data';
    if (! is_dir($dataDir)) {
        mkdir($dataDir, 0777, true);
    }
    $file = $dataDir . '/' . md5('login_black') . '.php';
    file_put_contents($file, "<?php\nreturn array('1.2.3.4' => array('time' => 1, 'count'"); // 故意截断，模拟并发写入被打断

    list($proc, $pipes) = login_black_start_probe('9.9.9.9', $runPath, 900, 5, 1000, 'check');
    $out = '';
    if (is_resource($proc)) {
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
    }

    TestAssert::true(is_string($out) && $out !== '', 'corrupted-file probe produced output (no silent crash)');
    TestAssert::true(strpos((string) $out, 'Fatal error') === false, 'corrupted-file probe: no fatal error');
    $decoded = json_decode((string) $out, true);
    TestAssert::true(is_array($decoded), 'corrupted-file probe: valid JSON output despite corrupt data file');
    TestAssert::true(isset($decoded['ok']) && $decoded['ok'] === true, 'corrupted-file probe: check completed successfully');
    TestAssert::true(array_key_exists('result', $decoded) && $decoded['result'] === false, 'corrupted-file probe: corrupt data degrades to no-lock');

    path_delete($runPath);

    echo "=== malformed blacklist entries degrade safely and rebuild ===\n";

    $runPath = login_black_tmp_dir('malformed');
    $dataDir = $runPath . '/data';
    if (! is_dir($dataDir)) {
        mkdir($dataDir, 0777, true);
    }
    $file = $dataDir . '/' . md5('login_black') . '.php';
    $malformed = array(
        '9.9.9.9' => 'not-an-entry',
        '8.8.8.8' => array('time' => time()),
        '7.7.7.7' => array('time' => 'invalid', 'count' => 1)
    );
    file_put_contents($file, "<?php\nreturn " . var_export($malformed, true) . ";");

    foreach (array_keys($malformed) as $ip) {
        $checkRun = login_black_run($ip, $runPath, 900, 5, 1000, 'check');
        TestAssert::same(0, $checkRun['exit_code'], 'malformed entry check exited 0: ' . $ip);
        TestAssert::true(strpos($checkRun['stderr'], 'Fatal error') === false, 'malformed entry check has no fatal: ' . $ip);
        $checkResult = json_decode($checkRun['stdout'], true);
        TestAssert::true(is_array($checkResult), 'malformed entry check returned valid JSON: ' . $ip);
        TestAssert::true(array_key_exists('result', $checkResult) && $checkResult['result'] === false, 'malformed entry degrades to no-lock: ' . $ip);
    }

    $rebuildRun = login_black_run('9.9.9.9', $runPath, 900, 5, 1000, 'set');
    TestAssert::same(0, $rebuildRun['exit_code'], 'malformed blacklist rebuild exited 0');
    $rebuilt = login_black_read_data($runPath);
    TestAssert::same(1, count($rebuilt), 'malformed entries removed during rebuild');
    TestAssert::true(isset($rebuilt['9.9.9.9']) && is_array($rebuilt['9.9.9.9']), 'current IP rebuilt as a valid entry');
    TestAssert::same(1, $rebuilt['9.9.9.9']['count'], 'rebuilt entry starts at count=1');

    path_delete($runPath);

    echo "=== setLoginBlack(): at lock_max capacity, evicts oldest entry instead of rejecting new IP ===\n";

    $runPath = login_black_tmp_dir('eviction');

    // 先顺序写入 3 条，制造从旧到新的时间顺序
    login_black_concurrent_run(array('1.1.1.1'), $runPath, 900, 5, 3);
    sleep(1);
    login_black_concurrent_run(array('2.2.2.2'), $runPath, 900, 5, 3);
    sleep(1);
    login_black_concurrent_run(array('3.3.3.3'), $runPath, 900, 5, 3);

    $beforeEviction = login_black_read_data($runPath);
    TestAssert::same(3, count($beforeEviction), 'capacity setup: 3 entries at lock_max=3');

    // 第 4 个新 IP 到来，触发淘汰
    $evictionRun = login_black_run('4.4.4.4', $runPath, 900, 5, 3, 'set');
    TestAssert::same(0, $evictionRun['exit_code'], 'eviction probe exited 0');
    TestAssert::true($evictionRun['stdout'] !== '', 'eviction probe produced JSON output');
    TestAssert::true(strpos($evictionRun['stderr'], 'Fatal error') === false, 'eviction probe: real warning path does not Fatal');
    $evictionResult = json_decode($evictionRun['stdout'], true);
    TestAssert::true(is_array($evictionResult), 'eviction probe returned valid JSON');
    TestAssert::true(isset($evictionResult['ok']) && $evictionResult['ok'] === true, 'eviction probe persisted blacklist state');

    $afterEviction = login_black_read_data($runPath);
    TestAssert::true(count($afterEviction) <= 3, 'eviction: total entries stay within lock_max');
    TestAssert::false(isset($afterEviction['1.1.1.1']), 'eviction: oldest entry (1.1.1.1) evicted');
    TestAssert::true(isset($afterEviction['4.4.4.4']), 'eviction: newest IP (4.4.4.4) accepted, not rejected');
    TestAssert::true(isset($afterEviction['2.2.2.2']) && isset($afterEviction['3.3.3.3']), 'eviction: newer existing entries kept');

    $logFile = $runPath . '/log/' . date('Ymd') . '.log';
    TestAssert::true(file_exists($logFile), 'eviction: warning log is written under the temporary run path');
    $log = file_get_contents($logFile);
    TestAssert::true(strpos($log, 'warning 登录黑名单已满，淘汰最旧IP：1.1.1.1，腾出给：4.4.4.4') !== false, 'eviction: warning is written after blacklist persistence');

    path_delete($runPath);

    echo "=== PHP version note ===\n";
    TestAssert::true(PHP_VERSION_ID >= 70000, 'PHP >= 7.0 (got ' . PHP_VERSION . ')');
});
