<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers parse_info_tpl() 报错页 PHP 版本占位符与 SERVER_SOFTWARE 安全读取
 *
 * Issue #175：错误页底部显示 PHP 版本；CLI 缺失 SERVER_SOFTWARE 时不再 Warning。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== parse_info_tpl: {phpversion} 使用 PHP_VERSION 常量 ===\n";

    $handle = file_get_contents(CORE_PATH . '/function/handle.php');
    TestAssert::false($handle === false, 'handle.php readable');
    TestAssert::contains(
        $handle,
        "str_replace('{phpversion}', PHP_VERSION, \$tpl_content)",
        'phpversion placeholder uses PHP_VERSION'
    );
    TestAssert::contains(
        $handle,
        "str_replace('{serversoft}', \$_SERVER['SERVER_SOFTWARE'] ?? '', \$tpl_content)",
        'serversoft uses null coalesce default'
    );
    TestAssert::notContains(
        $handle,
        "str_replace('{serversoft}', \$_SERVER['SERVER_SOFTWARE'], \$tpl_content)",
        'serversoft is not a bare SERVER_SOFTWARE read'
    );

    echo "=== error.html 底部展示程序版本与 PHP 版本 ===\n";

    $errorTpl = file_get_contents(CORE_PATH . '/template/error.html');
    TestAssert::false($errorTpl === false, 'error.html readable');
    TestAssert::contains($errorTpl, '程序版本：{appversion}', 'error.html keeps appversion');
    TestAssert::contains($errorTpl, 'PHP版本：{phpversion}', 'error.html shows phpversion');
    TestAssert::contains($errorTpl, 'word-break:break-word', 'error.html narrow-screen wrap');

    echo "=== success.html 不展示诊断版本信息 ===\n";

    $successTpl = file_get_contents(CORE_PATH . '/template/success.html');
    TestAssert::false($successTpl === false, 'success.html readable');
    TestAssert::notContains($successTpl, '{phpversion}', 'success.html has no phpversion');
    TestAssert::notContains($successTpl, '程序版本', 'success.html has no app version line');

    echo "=== error_http_status_probe 不再预设 SERVER_SOFTWARE 兜底 ===\n";

    $probe = file_get_contents(TEST_ROOT . 'scripts/error_http_status_probe.php');
    TestAssert::false($probe === false, 'error_http_status_probe.php readable');
    TestAssert::notContains($probe, 'cli-probe', 'probe no longer seeds cli-probe SERVER_SOFTWARE');
});
