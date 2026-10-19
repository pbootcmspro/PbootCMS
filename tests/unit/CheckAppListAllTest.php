<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core\basic\Check::checkApp 一次列出全部缺失项
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

function check_app_probe(string $ini): array
{
    $php = (defined('PHP_BINARY') && PHP_BINARY !== '') ? PHP_BINARY : 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'check_app_probe.php';
    $cmd = $php . ($ini !== '' ? ' ' . $ini : '') . ' ' . escapeshellarg($script);
    $pipes = array();
    $proc = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    TestAssert::true(is_resource($proc) || is_object($proc), 'check_app probe started');
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    return array((string) $out, (string) $err);
}

return TestAssert::runSuite(function () {
    echo "=== 正常环境不报错 ===\n";
    list($out, $err) = check_app_probe('');
    TestAssert::contains($out, "OK\n", 'checkApp passes when requirements are met');
    TestAssert::notContains($out, '不满足运行要求', 'checkApp does not list missing items');
    TestAssert::same('', trim($err), 'checkApp probe stderr empty');

    echo "=== 禁用 scandir 时清单包含该项 ===\n";
    list($out, $err) = check_app_probe('-d disable_functions=scandir');
    TestAssert::contains($out, 'scandir', 'missing scandir is listed');
    TestAssert::contains($out, '不满足运行要求', 'missing items are reported together');
    TestAssert::notContains($out, "OK\n", 'checkApp stops when scandir is disabled');
});
