<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers get_server_info() / get_server_soft() $_SERVER 缺键 nullsafe
 *
 * Issue #181：PHP 8+ 下后台服务器信息与伪静态保存不得因裸读 $_SERVER 产生告警。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * @return array{stdout:string,stderr:string,exit_code:int}
 */
function get_server_info_run_probe(): array
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'get_server_info_probe.php';
    $cmd = $php . ' ' . escapeshellarg($script);

    $descriptors = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $pipes = array();
    $proc = proc_open($cmd, $descriptors, $pipes);
    if ($proc === false || (! is_resource($proc) && ! is_object($proc))) {
        return array('stdout' => '', 'stderr' => '', 'exit_code' => -1);
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return array(
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
        'exit_code' => (int) proc_close($proc),
    );
}

return TestAssert::runSuite(function () {
    $handle = file_get_contents(CORE_PATH . '/function/handle.php');
    TestAssert::false($handle === false, 'handle.php readable');

    echo "=== get_server_info: \$_SERVER 键使用 ?? 兜底 ===\n";
    $nullsafeLines = array(
        "\$data['http_host'] = \$_SERVER['HTTP_HOST'] ?? '';",
        "\$data['server_name'] = \$_SERVER['SERVER_NAME'] ?? '';",
        "\$data['server_port'] = \$_SERVER['SERVER_PORT'] ?? '';",
        "\$data['server_addr'] = \$_SERVER['LOCAL_ADDR'] ?? \$_SERVER['SERVER_ADDR'] ?? '';",
        "\$data['server_software'] = \$_SERVER['SERVER_SOFTWARE'] ?? '';",
        "\$data['session_save_path'] = session_save_path() ?: (\$_SERVER['TMP'] ?? '');",
        '$soft = strtolower($_SERVER["SERVER_SOFTWARE"] ?? \'\');',
    );
    foreach ($nullsafeLines as $line) {
        TestAssert::contains($handle, $line, 'handle.php contains nullsafe: ' . $line);
    }

    $bareReads = array(
        "\$data['http_host'] = \$_SERVER['HTTP_HOST'];",
        "\$data['server_name'] = \$_SERVER['SERVER_NAME'];",
        "\$data['server_port'] = \$_SERVER['SERVER_PORT'];",
        "\$data['server_addr'] = isset(\$_SERVER['LOCAL_ADDR']) ? \$_SERVER['LOCAL_ADDR'] : \$_SERVER['SERVER_ADDR'];",
        "\$data['server_software'] = \$_SERVER['SERVER_SOFTWARE'];",
        "\$data['session_save_path'] = session_save_path() ?: \$_SERVER['TMP'];",
        '$soft = strtolower($_SERVER["SERVER_SOFTWARE"]);',
    );
    foreach ($bareReads as $line) {
        TestAssert::notContains($handle, $line, 'handle.php no bare read: ' . $line);
    }

    echo "=== CLI 缺键调用无 Warning / Deprecated ===\n";
    $result = get_server_info_run_probe();
    TestAssert::same('', trim($result['stderr']), 'probe stderr empty');
    TestAssert::same(0, $result['exit_code'], 'probe exit 0');

    $decoded = json_decode(trim($result['stdout']), true);
    TestAssert::true(is_array($decoded), 'probe JSON decoded');
    TestAssert::true(! empty($decoded['ok']), 'probe ok with no warnings');
    TestAssert::same(array(), $decoded['warnings'], 'probe warnings empty');
    TestAssert::same('', $decoded['server_addr'], 'server_addr empty when keys missing');
    TestAssert::same('', $decoded['server_software'], 'server_software empty when key missing');
    TestAssert::same('', $decoded['session_save_path'], 'session_save_path empty when TMP missing');
    TestAssert::same('other', $decoded['soft'], 'get_server_soft returns other for empty software');
});
