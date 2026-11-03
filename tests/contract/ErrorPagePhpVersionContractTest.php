<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers parse_info_tpl() 报错页 PHP/数据库环境占位符与 SERVER_SOFTWARE 安全读取
 *
 * Issue #175：错误页底部显示 PHP 版本；CLI 缺失 SERVER_SOFTWARE 时不再 Warning。
 * Issue #333：错误页增加数据库类型/驱动/版本；渲染阶段不访问数据库。
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

    echo "=== parse_info_tpl db placeholders via db_env_format only ===\n";

    TestAssert::contains($handle, 'function db_env_format()', 'db_env_format exists');
    TestAssert::contains($handle, 'function db_env_note(', 'db_env_note exists');
    TestAssert::contains($handle, "str_replace('{dbtype}', \$db_env['type'], \$tpl_content)", 'dbtype replaced from format');
    TestAssert::contains($handle, "str_replace('{dbdriver}', \$db_env['driver'], \$tpl_content)", 'dbdriver replaced from format');
    TestAssert::contains($handle, "str_replace('{dbversion}', \$db_env['version'], \$tpl_content)", 'dbversion replaced from format');

    $formatStart = strpos($handle, 'function db_env_format()');
    $formatEnd = strpos($handle, 'function db_env_config_driver()');
    TestAssert::true($formatStart !== false && $formatEnd !== false && $formatEnd > $formatStart, 'format body bounds');
    $formatBody = substr($handle, $formatStart, $formatEnd - $formatStart);
    TestAssert::notContains($formatBody, 'get_server_info', 'format no get_server_info');
    TestAssert::notContains($formatBody, 'getInstance', 'format no getInstance');
    TestAssert::notContains($formatBody, '->conn(', 'format no conn');
    TestAssert::notContains($formatBody, '->query(', 'format no query');
    TestAssert::notContains($formatBody, 'SELECT VERSION', 'format no SELECT VERSION');
    TestAssert::notContains($formatBody, 'sqlite_version', 'format no sqlite_version SQL');

    $parseStart = strpos($handle, 'function parse_info_tpl(');
    $parseEnd = strpos($handle, 'function filter_area_acode(');
    TestAssert::true($parseStart !== false && $parseEnd !== false && $parseEnd > $parseStart, 'parse_info_tpl body bounds');
    $parseBody = substr($handle, $parseStart, $parseEnd - $parseStart);
    TestAssert::contains($parseBody, 'db_env_format()', 'parse uses db_env_format');
    TestAssert::notContains($parseBody, 'get_server_info', 'parse no get_server_info');
    TestAssert::notContains($parseBody, 'getInstance', 'parse no getInstance');
    TestAssert::notContains($parseBody, '->conn(', 'parse no conn');
    TestAssert::notContains($parseBody, '->query(', 'parse no query');
    TestAssert::notContains($parseBody, 'SELECT VERSION', 'parse no SELECT VERSION');

    echo "=== error.html 底部展示程序/PHP/数据库环境 ===\n";

    $errorTpl = file_get_contents(CORE_PATH . '/template/error.html');
    TestAssert::false($errorTpl === false, 'error.html readable');
    TestAssert::contains($errorTpl, '程序版本：{appversion}', 'error.html keeps appversion');
    TestAssert::contains($errorTpl, 'PHP版本：{phpversion}', 'error.html shows phpversion');
    TestAssert::contains($errorTpl, '数据库：{dbtype}', 'error.html shows dbtype');
    TestAssert::contains($errorTpl, '驱动：{dbdriver}', 'error.html shows dbdriver');
    TestAssert::contains($errorTpl, '版本：{dbversion}', 'error.html shows dbversion');
    TestAssert::contains($errorTpl, '{phpversion}<br>', 'error.html db line after br');
    TestAssert::contains($errorTpl, 'word-break:break-word', 'error.html narrow-screen wrap');
    TestAssert::contains($errorTpl, 'max-width:90%', 'error.html max-width for narrow screen');

    echo "=== success.html 不展示诊断版本信息 ===\n";

    $successTpl = file_get_contents(CORE_PATH . '/template/success.html');
    TestAssert::false($successTpl === false, 'success.html readable');
    TestAssert::notContains($successTpl, '{phpversion}', 'success.html has no phpversion');
    TestAssert::notContains($successTpl, '{dbtype}', 'success.html has no dbtype');
    TestAssert::notContains($successTpl, '{dbdriver}', 'success.html has no dbdriver');
    TestAssert::notContains($successTpl, '{dbversion}', 'success.html has no dbversion');
    TestAssert::notContains($successTpl, '程序版本', 'success.html has no app version line');

    echo "=== drivers pin/block/note wiring ===\n";

    foreach (array('Sqlite.php', 'Mysqli.php', 'Pdo.php') as $file) {
        $src = file_get_contents(CORE_PATH . '/database/' . $file);
        TestAssert::contains($src, 'db_env_note(', $file . ' notes version after connect assign');
        TestAssert::contains($src, 'db_env_pin(', $file . ' pins role on SQL error');
        TestAssert::contains($src, 'db_env_block()', $file . ' blocks on connect failure');
    }

    echo "=== error_http_status_probe 不再预设 SERVER_SOFTWARE 兜底 ===\n";

    $probe = file_get_contents(TEST_ROOT . 'scripts/error_http_status_probe.php');
    TestAssert::false($probe === false, 'error_http_status_probe.php readable');
    TestAssert::notContains($probe, 'cli-probe', 'probe no longer seeds cli-probe SERVER_SOFTWARE');
});
