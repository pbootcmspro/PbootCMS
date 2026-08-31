<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers curl_safe_close()
 * @covers get_url() 不在 PHP 8+ 触发 curl_close 废弃告警
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * @return array{deprecations:int,exit_code:int}
 */
function curl_safe_close_deprecation_probe(): array
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'curl_safe_close_probe.php';
    $cmd = $php . ' ' . escapeshellarg($script);

    $descriptors = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $pipes = array();
    $proc = proc_open($cmd, $descriptors, $pipes);
    if ($proc === false || (! is_resource($proc) && ! is_object($proc))) {
        return array('deprecations' => -1, 'exit_code' => -1);
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = (int) proc_close($proc);
    $out = is_string($stdout) ? $stdout : '';
    $dep = 0;
    if (preg_match('/^DEPRECATIONS=(\d+)/m', $out, $m)) {
        $dep = (int) $m[1];
    }
    if ($dep === 0 && is_string($stderr) && $stderr !== '') {
        // 兼容 probe 把计数写到 stderr 的情况
        if (preg_match('/DEPRECATIONS=(\d+)/', $stderr, $m2)) {
            $dep = (int) $m2[1];
        }
    }
    return array('deprecations' => $dep, 'exit_code' => $exit);
}

return TestAssert::runSuite(function () {
    echo "=== curl_safe_close 已定义 ===\n";
    TestAssert::true(function_exists('curl_safe_close'), 'curl_safe_close exists');

    echo "=== PHP 8+ CurlHandle 不触发 E_DEPRECATED ===\n";
    if (! function_exists('curl_init')) {
        echo "SKIP: curl extension missing\n";
    } else {
        $dep = 0;
        set_error_handler(static function ($errno) use (&$dep) {
            if ($errno === E_DEPRECATED) {
                $dep++;
            }
        });
        $ch = curl_init();
        curl_safe_close($ch);
        restore_error_handler();
        TestAssert::same(0, $dep, 'in-process curl_safe_close no E_DEPRECATED');

        $probe = curl_safe_close_deprecation_probe();
        TestAssert::same(0, $probe['exit_code'], 'probe exit 0');
        TestAssert::same(0, $probe['deprecations'], 'probe no E_DEPRECATED under debug-like reporting');
    }

    echo "=== get_url 源码使用 curl_safe_close ===\n";
    $handle = file_get_contents(CORE_PATH . '/function/handle.php');
    TestAssert::false($handle === false, 'handle.php readable');
    TestAssert::contains($handle, 'function curl_safe_close', 'curl_safe_close defined');
    TestAssert::contains($handle, 'curl_safe_close($ch)', 'get_url uses curl_safe_close');
});
