<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/log/LogSpider.php
 * @covers apps/home/controller/SpiderController.php
 *
 * fix #22 / audit-13：蜘蛛日志与系统日志隔离，并发写入不锁 SQLite；
 * 首写不得经 check_file/create_file（fopen w 截断竞态）
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}
require_once CORE_PATH . '/function/file.php';

/** proc_open 在 PHP 7/8 下可能返回 resource 或 object */
function spider_log_proc_ok($proc)
{
    return $proc !== false && (is_resource($proc) || is_object($proc));
}

function spider_log_command(string $runPath, string $spiderlog, string $url, string $userAgent, string $syncDir = ''): string
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'spider_log_probe.php';
    $args = array($script, $runPath, $spiderlog, $url, $userAgent);
    if ($syncDir !== '') {
        $args[] = $syncDir;
    }
    return $php . ' ' . implode(' ', array_map('escapeshellarg', $args));
}

function spider_log_start_probe(string $runPath, string $spiderlog, string $url, string $userAgent, string $syncDir = ''): array
{
    $descriptors = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $pipes = array();
    $proc = proc_open(spider_log_command($runPath, $spiderlog, $url, $userAgent, $syncDir), $descriptors, $pipes);
    return array($proc, $pipes);
}

function spider_log_run(string $runPath, string $spiderlog, string $url, string $userAgent): array
{
    list($proc, $pipes) = spider_log_start_probe($runPath, $spiderlog, $url, $userAgent);
    if (! spider_log_proc_ok($proc)) {
        return array('stdout' => '', 'stderr' => '', 'exit_code' => -1);
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return array('stdout' => $stdout, 'stderr' => $stderr, 'exit_code' => proc_close($proc));
}

function spider_log_concurrent_run(array $urls, string $runPath, string $spiderlog, string $userAgent, string $syncDir = ''): array
{
    $runs = array();
    foreach ($urls as $url) {
        $runs[] = spider_log_start_probe($runPath, $spiderlog, $url, $userAgent, $syncDir);
    }

    $results = array();
    foreach ($runs as $run) {
        list($proc, $pipes) = $run;
        if (! spider_log_proc_ok($proc)) {
            $results[] = array('exit_code' => -1, 'stdout' => '', 'stderr' => '');
            continue;
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $results[] = array(
            'exit_code' => proc_close($proc),
            'stdout' => $stdout,
            'stderr' => $stderr,
        );
    }
    return $results;
}

/**
 * 首写屏障：全部子进程就绪后再放行，逼近「文件尚不存在」分支上的并发。
 *
 * @return array{0: array, 1: float} results + seconds waited for ready
 */
function spider_log_barrier_concurrent_run(array $urls, string $runPath, string $spiderlog, string $userAgent): array
{
    $syncDir = $runPath . DIRECTORY_SEPARATOR . 'sync';
    path_delete($syncDir);
    if (! mkdir($syncDir, 0777, true) && ! is_dir($syncDir)) {
        return array(array(), 0.0);
    }

    $expected = count($urls);
    $runs = array();
    foreach ($urls as $url) {
        $runs[] = spider_log_start_probe($runPath, $spiderlog, $url, $userAgent, $syncDir);
    }

    $deadline = microtime(true) + 30;
    $waited = 0.0;
    $t0 = microtime(true);
    while (true) {
        $ready = glob($syncDir . DIRECTORY_SEPARATOR . 'ready_*');
        $count = is_array($ready) ? count($ready) : 0;
        if ($count >= $expected) {
            break;
        }
        if (microtime(true) > $deadline) {
            break;
        }
        usleep(2000);
    }
    $waited = microtime(true) - $t0;
    file_put_contents($syncDir . DIRECTORY_SEPARATOR . 'go', '1');

    $results = array();
    foreach ($runs as $run) {
        list($proc, $pipes) = $run;
        if (! spider_log_proc_ok($proc)) {
            $results[] = array('exit_code' => -1, 'stdout' => '', 'stderr' => '');
            continue;
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $results[] = array(
            'exit_code' => proc_close($proc),
            'stdout' => $stdout,
            'stderr' => $stderr,
        );
    }
    return array($results, $waited);
}

function spider_log_tmp_dir(string $suffix): string
{
    $dir = sys_get_temp_dir() . '/pboot_spider_log_test_' . $suffix . '_' . getmypid();
    path_delete($dir);
    return $dir;
}

function spider_log_read_lines(string $runPath): array
{
    $logFile = $runPath . '/log/spider/' . date('Ymd') . '.log';
    if (! file_exists($logFile)) {
        return array();
    }
    $content = file_get_contents($logFile);
    if ($content === false || $content === '') {
        return array();
    }
    $lines = explode(PHP_EOL, trim($content));
    return array_values(array_filter($lines, function ($line) {
        return $line !== '';
    }));
}

/** @return array<string, mixed>|null */
function spider_log_decode_stdout(string $stdout, string $label)
{
    $decoded = json_decode(trim($stdout), true);
    TestAssert::true(
        is_array($decoded),
        $label . ' (stdout: ' . var_export($stdout, true) . ')'
    );
    return is_array($decoded) ? $decoded : null;
}

return TestAssert::runSuite(function () {
    echo "=== source contract: LogSpider must not create via check_file/create_file ===\n";

    $src = file_get_contents(CORE_PATH . '/log/LogSpider.php');
    TestAssert::false($src === false, 'LogSpider.php readable');
    TestAssert::notContains($src, 'check_file(', 'LogSpider must not call check_file()');
    TestAssert::notContains($src, 'create_file(', 'LogSpider must not call create_file()');
    TestAssert::contains($src, 'check_dir(', 'LogSpider ensures spider log directory exists');
    TestAssert::true(
        strpos($src, "fopen(\$path, 'ab')") !== false || strpos($src, 'fopen($path, "ab")') !== false,
        'LogSpider appends with fopen ab (create without truncate)'
    );

    echo "=== spiderlog=0: no spider log file written ===\n";

    $runPath = spider_log_tmp_dir('disabled');
    $run = spider_log_run($runPath, '0', 'https://example.com/page1', 'Mozilla/5.0 (compatible; Baiduspider/2.0)');
    TestAssert::same(0, $run['exit_code'], 'disabled probe exited 0');
    TestAssert::true(strpos($run['stderr'], 'Fatal error') === false, 'disabled probe: no fatal error');
    $decoded = spider_log_decode_stdout($run['stdout'], 'disabled probe returned JSON');
    if (is_array($decoded)) {
        TestAssert::false((bool) $decoded['written'], 'spiderlog=0: no log lines written');
    }
    TestAssert::false(file_exists($runPath . '/log/spider/' . date('Ymd') . '.log'), 'spiderlog=0: log file not created');

    path_delete($runPath);

    echo "=== spiderlog=1: URL and spider name are traceable in text log ===\n";

    $runPath = spider_log_tmp_dir('enabled');
    $testUrl = 'https://example.com/news/audit-13-test';
    $run = spider_log_run($runPath, '1', $testUrl, 'Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)');
    TestAssert::same(0, $run['exit_code'], 'enabled probe exited 0');
    $decoded = spider_log_decode_stdout($run['stdout'], 'enabled probe returned JSON');
    if (is_array($decoded)) {
        TestAssert::true((bool) $decoded['written'], 'spiderlog=1: log line written');
    }

    $lines = spider_log_read_lines($runPath);
    TestAssert::same(1, count($lines), 'spiderlog=1: exactly one log line');
    TestAssert::true(strpos($lines[0], 'Baidu') !== false, 'spiderlog=1: spider name present');
    TestAssert::true(strpos($lines[0], $testUrl) !== false, 'spiderlog=1: URL present');

    path_delete($runPath);

    echo "=== concurrent spider log writes: 50 distinct URLs, no data loss ===\n";

    $runPath = spider_log_tmp_dir('concurrency');
    $urls = array();
    for ($i = 1; $i <= 50; $i++) {
        $urls[] = 'https://example.com/spider/page-' . $i;
    }

    $results = spider_log_concurrent_run($urls, $runPath, '1', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
    foreach ($results as $idx => $result) {
        TestAssert::same(0, $result['exit_code'], 'concurrent probe #' . ($idx + 1) . ' exited 0');
        TestAssert::true(strpos($result['stderr'], 'database is locked') === false, 'concurrent probe #' . ($idx + 1) . ': no SQLite lock');
        TestAssert::true(strpos($result['stderr'], 'Fatal error') === false, 'concurrent probe #' . ($idx + 1) . ': no fatal error');
    }

    $lines = spider_log_read_lines($runPath);
    TestAssert::same(50, count($lines), 'concurrent: all 50 URLs persisted');

    $loggedUrls = array();
    foreach ($lines as $line) {
        TestAssert::true(strpos($line, 'Google') !== false, 'concurrent: spider name in each line');
        $parts = explode("\t", $line);
        TestAssert::true(count($parts) >= 3, 'concurrent: log line has tab-separated fields');
        $loggedUrls[] = $parts[2];
    }
    foreach ($urls as $url) {
        TestAssert::true(in_array($url, $loggedUrls, true), 'concurrent: URL logged: ' . $url);
    }

    path_delete($runPath);

    echo "=== fresh-file barrier: 30 processes start before first create, no truncation loss ===\n";

    $runPath = spider_log_tmp_dir('fresh_barrier');
    $urls = array();
    for ($i = 1; $i <= 30; $i++) {
        $urls[] = 'https://example.com/spider/fresh-' . $i;
    }

    TestAssert::false(
        file_exists($runPath . '/log/spider/' . date('Ymd') . '.log'),
        'fresh-file: log absent before barrier release'
    );

    list($results, $waited) = spider_log_barrier_concurrent_run(
        $urls,
        $runPath,
        '1',
        'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'
    );
    TestAssert::true($waited < 30.0, 'fresh-file: barrier ready within timeout');
    TestAssert::same(30, count($results), 'fresh-file: 30 probe results collected');

    foreach ($results as $idx => $result) {
        TestAssert::same(0, $result['exit_code'], 'fresh-file probe #' . ($idx + 1) . ' exited 0');
        TestAssert::true(strpos($result['stderr'], 'Fatal error') === false, 'fresh-file probe #' . ($idx + 1) . ': no fatal');
        TestAssert::true(strpos($result['stderr'], 'barrier timeout') === false, 'fresh-file probe #' . ($idx + 1) . ': no barrier timeout');
    }

    $lines = spider_log_read_lines($runPath);
    TestAssert::same(30, count($lines), 'fresh-file: all 30 URLs persisted (no w-truncate loss)');

    $loggedUrls = array();
    foreach ($lines as $line) {
        $parts = explode("\t", $line);
        TestAssert::true(count($parts) >= 3, 'fresh-file: tab-separated fields');
        $loggedUrls[] = $parts[2];
    }
    foreach ($urls as $url) {
        TestAssert::true(in_array($url, $loggedUrls, true), 'fresh-file: URL logged: ' . $url);
    }

    path_delete($runPath);

    echo "=== non-spider UA: no log written even when spiderlog=1 ===\n";

    $runPath = spider_log_tmp_dir('non_spider');
    $run = spider_log_run($runPath, '1', 'https://example.com/human', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0');
    TestAssert::same(0, $run['exit_code'], 'non-spider probe exited 0');
    $decoded = spider_log_decode_stdout($run['stdout'], 'non-spider probe returned JSON');
    if (is_array($decoded)) {
        TestAssert::false((bool) $decoded['written'], 'non-spider UA: no log written');
    }

    path_delete($runPath);

    echo "=== empty url: no log written even for spider UA ===\n";

    $runPath = spider_log_tmp_dir('empty_url');
    $run = spider_log_run($runPath, '1', '', 'Mozilla/5.0 (compatible; Baiduspider/2.0)');
    TestAssert::same(0, $run['exit_code'], 'empty url probe exited 0');
    $decoded = spider_log_decode_stdout($run['stdout'], 'empty url probe returned JSON');
    if (is_array($decoded)) {
        TestAssert::false((bool) $decoded['written'], 'empty url: no log written');
    }

    path_delete($runPath);

    echo "=== long url: truncated to 2000 chars ===\n";

    $runPath = spider_log_tmp_dir('long_url');
    $longUrl = 'https://example.com/' . str_repeat('a', 2100);
    $run = spider_log_run($runPath, '1', $longUrl, 'Mozilla/5.0 (compatible; Baiduspider/2.0)');
    TestAssert::same(0, $run['exit_code'], 'long url probe exited 0');
    $lines = spider_log_read_lines($runPath);
    TestAssert::same(1, count($lines), 'long url: one log line written');
    $parts = explode("\t", $lines[0]);
    TestAssert::true(isset($parts[2]), 'long url: URL field present');
    TestAssert::same(2000, mb_strlen($parts[2]), 'long url: stored URL truncated to 2000 chars');

    path_delete($runPath);
});
