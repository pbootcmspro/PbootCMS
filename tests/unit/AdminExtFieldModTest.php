<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers ExtFieldController::mod() 表单带 value 无 field 时走整单保存
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * @return array{stdout:string,stderr:string,exit_code:int}
 */
function admin_extfield_mod_run_probe($mode)
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'admin_extfield_mod_probe.php';
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
    echo "=== form with value and without field saves ===\n";

    foreach (array('form_value_without_field', 'form_empty_value_without_field') as $mode) {
        $r = admin_extfield_mod_run_probe($mode);
        TestAssert::same(0, $r['exit_code'], $mode . ' exits cleanly');
        TestAssert::false(strpos($r['stdout'], '不允许修改该字段') !== false, $mode . ' not rejected as quick field');
        TestAssert::true(strpos($r['stdout'], 'MOD_CALLED:2:') !== false, $mode . ' reaches modExtField');
        TestAssert::true(strpos($r['stdout'], '修改成功') !== false, $mode . ' success message');
    }

    $form = admin_extfield_mod_run_probe('form_value_without_field');
    TestAssert::true(strpos($form['stdout'], '基础版,专业版,旗舰版') !== false, 'option value reaches model');

    echo "=== illegal quick field still rejected ===\n";

    $bad = admin_extfield_mod_run_probe('quick_illegal_field');
    TestAssert::same(0, $bad['exit_code'], 'illegal quick field returns handled error');
    TestAssert::true(strpos($bad['stdout'], '不允许修改该字段') !== false, 'illegal quick field rejected');
    TestAssert::false(strpos($bad['stdout'], 'MOD_CALLED:') !== false, 'illegal quick field no model call');
});
