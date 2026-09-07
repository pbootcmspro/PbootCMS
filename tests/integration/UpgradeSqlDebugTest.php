<?php

declare(strict_types=1);

/**
 * @suite integration
 * @covers core/basic/Model.php insertGetId PHP 8.4+ 隐式 nullable
 * @covers UpgradeController::upsql() debug 下 Model 加载与 SQL 执行路径
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * @return array{stdout:string,stderr:string,exit_code:int}
 */
function upgrade_sql_model_probe(string $mode): array
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'upgrade_sql_model_probe.php';
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
        'exit_code' => (int) proc_close($proc),
    );
}

return TestAssert::runSuite(function () {
    echo "=== Model 源码：无 insertGetId 隐式 nullable array 参数 ===\n";

    $modelSrc = file_get_contents(CORE_PATH . '/basic/Model.php');
    TestAssert::false($modelSrc === false, 'Model.php readable');
    TestAssert::notContains(
        $modelSrc,
        'function insertGetId(array $data = null',
        'insertGetId has no implicit nullable array param'
    );
    TestAssert::contains(
        $modelSrc,
        'function insertGetId($data = null, $batch = true)',
        'insertGetId uses untyped nullable default'
    );

    echo "=== debug 错误处理下 new Model() 不中止 ===\n";

    $load = upgrade_sql_model_probe('load');
    TestAssert::same(0, $load['exit_code'], 'load probe exit 0');
    TestAssert::contains($load['stdout'], 'LOAD_OK', 'load probe succeeded');
    TestAssert::notContains($load['stderr'], 'Implicitly marking parameter', 'load probe no implicit nullable stderr');
    TestAssert::notContains($load['stdout'], 'UNKNOW:', 'load probe no fatal error page');

    echo "=== debug 错误处理下 upsql 路径可执行 amd() ===\n";

    if (! extension_loaded('sqlite3')) {
        echo "SKIP: sqlite3 extension unavailable\n";
        return;
    }

    $upsql = upgrade_sql_model_probe('upsql');
    TestAssert::same(0, $upsql['exit_code'], 'upsql probe exit 0');
    TestAssert::contains($upsql['stdout'], 'UPSQL_OK', 'upsql probe succeeded');
    TestAssert::notContains($upsql['stderr'], 'Implicitly marking parameter', 'upsql probe no implicit nullable stderr');
    TestAssert::notContains($upsql['stdout'], 'UNKNOW:', 'upsql probe no fatal error page');

    echo "=== upsql() 成功 DDL 批次不被误判为失败（#204）===\n";

    $ddl = upgrade_sql_model_probe('upsql_ddl');
    TestAssert::same(0, $ddl['exit_code'], 'upsql_ddl probe exit 0');
    TestAssert::contains($ddl['stdout'], 'UPSQL_DDL_OK', 'CREATE/ALTER 影响行数为 0 时 upsql() 仍返回 true');
    TestAssert::notContains($ddl['stdout'], 'UNKNOW:', 'upsql_ddl probe no fatal error page');

    echo "=== upsql() 检出失败语句并返回 false，不 error()+exit（#204）===\n";

    $fail = upgrade_sql_model_probe('upsql_fail');
    TestAssert::same(0, $fail['exit_code'], 'upsql_fail probe exit 0');
    TestAssert::contains($fail['stdout'], 'UPSQL_FAIL_DETECTED', 'upsql() 遇失败语句返回 false');
    TestAssert::notContains($fail['stdout'], 'UNKNOW:', 'upsql_fail probe no error()+exit 错误页');
    TestAssert::notContains($fail['stdout'], 'UPSQL_FAIL_BAD', 'upsql() 未把失败当成功');

    echo "=== upsql() 跳过纯注释/空白片段，不发给驱动（#204）===\n";

    $blank = upgrade_sql_model_probe('blank');
    TestAssert::same(0, $blank['exit_code'], 'blank probe exit 0');
    TestAssert::contains($blank['stdout'], 'BLANK_OK', 'isSqlBlank() 正确识别注释/空白片段');
});
