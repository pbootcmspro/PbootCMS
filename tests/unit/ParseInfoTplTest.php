<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers parse_info_tpl() 错误页 PHP 版本替换与 SERVER_SOFTWARE 缺失兼容
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

defined('SITE_DIR') || define('SITE_DIR', '');
defined('CORE_DIR') || define('CORE_DIR', '/core');
defined('APP_VERSION') || define('APP_VERSION', '3.2.23');
defined('RELEASE_TIME') || define('RELEASE_TIME', '20260825');

return TestAssert::runSuite(function () {
    echo "=== parse_info_tpl replaces phpversion without SERVER_SOFTWARE warning ===\n";

    unset($_SERVER['SERVER_SOFTWARE']);

    $out = parse_info_tpl(CORE_PATH . '/template/error.html', 'probe error message');

    TestAssert::contains($out, 'PHP版本：' . PHP_VERSION, 'output contains replaced PHP version');
    TestAssert::contains($out, '程序版本：3.2.23-20260825', 'output contains app version');
    TestAssert::notContains($out, '{phpversion}', 'phpversion placeholder fully replaced');
    TestAssert::notContains($out, '{appversion}', 'appversion placeholder fully replaced');
    TestAssert::contains($out, 'probe error message', 'error message preserved');
});
