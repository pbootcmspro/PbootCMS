<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers MemberController::mod() 快捷改值字段白名单
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * @return array{stdout:string,stderr:string,exit_code:int}
 */
function admin_member_quick_mod_run_probe($mode)
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'admin_member_quick_mod_probe.php';
    $cmd = $php . ' ' . implode(' ', array_map('escapeshellarg', array($script, $mode)));

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
        'exit_code' => (int) proc_close($proc)
    );
}

return TestAssert::runSuite(function () {
    echo "=== reject password/gid/score and invalid status ===\n";

    foreach (array('field_password', 'field_gid', 'field_score') as $mode) {
        $r = admin_member_quick_mod_run_probe($mode);
        TestAssert::false(strpos($r['stdout'], 'STATUS_CALLED:') !== false, $mode . ' no status call');
        TestAssert::false(strpos($r['stdout'], 'MOD_CALLED:') !== false, $mode . ' no mod call');
        TestAssert::true(strpos($r['stdout'], '不允许修改该字段') !== false, $mode . ' reject field');
    }

    $bad = admin_member_quick_mod_run_probe('value_invalid');
    TestAssert::false(strpos($bad['stdout'], 'STATUS_CALLED:') !== false, 'invalid value no status call');
    TestAssert::true(strpos($bad['stdout'], '传递的参数值错误') !== false, 'invalid value message');

    foreach (array(
        'id_array' => '传递的参数值错误',
        'field_array' => '不允许修改该字段',
        'value_array' => '传递的参数值错误',
        'value_array_without_field' => '不允许修改该字段'
    ) as $mode => $message) {
        $r = admin_member_quick_mod_run_probe($mode);
        TestAssert::same(0, $r['exit_code'], $mode . ' returns a handled validation error');
        TestAssert::true(strpos($r['stdout'], $message) !== false, $mode . ' validation message');
        TestAssert::false(strpos($r['stdout'], 'STATUS_CALLED:') !== false, $mode . ' no status call');
        TestAssert::false(strpos($r['stdout'], 'MOD_CALLED:') !== false, $mode . ' no mod call');
    }

    echo "=== status 0/1 allowed ===\n";

    $z = admin_member_quick_mod_run_probe('status_0');
    TestAssert::true(strpos($z['stdout'], 'STATUS_CALLED:12:0') !== false, 'status=0 reaches model');

    $o = admin_member_quick_mod_run_probe('status_1');
    TestAssert::true(strpos($o['stdout'], 'STATUS_CALLED:12:1') !== false, 'status=1 reaches model');
});
