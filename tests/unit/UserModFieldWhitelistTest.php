<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers UserController::mod() 快捷改值字段白名单与 POST 约束
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * @return array{stdout:string,stderr:string,exit_code:int}
 */
function user_mod_whitelist_run_probe(string $mode): array
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'user_mod_field_whitelist_probe.php';
    $cmd = $php . ' ' . implode(' ', array_map('escapeshellarg', array($script, $mode)));

    $descriptors = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $pipes = array();
    $proc = proc_open($cmd, $descriptors, $pipes);
    if ($proc === false || (!is_resource($proc) && !is_object($proc))) {
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
    echo "=== POST field=password 必须被拒绝，不得调用 modUser() ===\n";
    $r = user_mod_whitelist_run_probe('field_password');
    TestAssert::false(strpos($r['stdout'], 'MOD_CALLED:') !== false, 'password 字段不得触达 modUser()');
    TestAssert::true(strpos($r['stdout'], '不允许修改该字段') !== false, '返回字段不允许修改的错误信息');

    echo "=== POST field=status 正常放行，调用 modUser() ===\n";
    $r = user_mod_whitelist_run_probe('field_status');
    TestAssert::true(strpos($r['stdout'], "MOD_CALLED:10002:status='1'") !== false, 'status 字段正常触达 modUser()');

    echo "=== 裸 GET field=status 必须被拒绝，不得调用 modUser() ===\n";
    $r = user_mod_whitelist_run_probe('field_status_get');
    TestAssert::false(strpos($r['stdout'], 'MOD_CALLED:') !== false, '裸 GET 不得触达 modUser()');
    TestAssert::true(strpos($r['stdout'], '请使用正确方式提交') !== false, '返回提交方式错误提示');
});
