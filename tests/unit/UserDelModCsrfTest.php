<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers UserController::del() / mod() 禁止裸 GET 写操作（CSRF）
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * @return array{stdout:string,stderr:string,exit_code:int}
 */
function user_del_mod_csrf_run_probe(string $mode): array
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'user_del_mod_csrf_probe.php';
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
    echo "=== 裸 GET /User/del 不得删除用户 ===\n";
    $r = user_del_mod_csrf_run_probe('del_get');
    TestAssert::false(strpos($r['stdout'], 'DEL_CALLED:') !== false, '裸 GET 不得触达 delUser()');
    TestAssert::true(strpos($r['stdout'], '请使用正确方式提交') !== false, '返回提交方式错误提示');

    echo "=== POST /User/del 允许删除 ===\n";
    $r = user_del_mod_csrf_run_probe('del_post');
    TestAssert::true(strpos($r['stdout'], 'DEL_CALLED:10002') !== false, 'POST 应触达 delUser()');

    echo "=== 裸 GET /User/mod field=status 不得改状态 ===\n";
    $r = user_del_mod_csrf_run_probe('status_get');
    TestAssert::false(strpos($r['stdout'], 'MOD_CALLED:') !== false, '裸 GET 不得触达 modUser()');
    TestAssert::true(strpos($r['stdout'], '请使用正确方式提交') !== false, '返回提交方式错误提示');

    echo "=== POST /User/mod field=status 允许改状态 ===\n";
    $r = user_del_mod_csrf_run_probe('status_post');
    TestAssert::true(strpos($r['stdout'], "MOD_CALLED:10002:status='0'") !== false, 'POST status 应触达 modUser()');
});
