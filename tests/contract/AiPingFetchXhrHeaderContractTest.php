<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers 配置页 AI 连接测试 fetch 显式带 X-Requested-With，与 jQuery/is_ajax() 对齐
 *
 * 背景：原生 fetch 不会自动补 X-Requested-With；缺失时 is_ajax() 恒为 false，
 * 框架级失败（formcheck → alert_back 等）返回 HTML/script，前端 r.json() 抛错，
 * 用户只看到「请求异常」，丢失真实中文原因。
 *
 * 运行：
 *   php tests/run.php --suite=contract --filter=AiPingFetchXhrHeader
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . 'apps');
}

return TestAssert::runSuite(function () {
    echo "=== config.html AI ping fetch sends X-Requested-With ===\n";

    $cfg = file_get_contents(APP_PATH . '/admin/view/default/system/config.html');
    TestAssert::false($cfg === false, 'config.html readable');
    TestAssert::contains($cfg, "fetch('{url./admin/Ai/ping}'", 'AI ping uses fetch');
    TestAssert::contains($cfg, "'X-Requested-With': 'XMLHttpRequest'", 'fetch sets X-Requested-With for is_ajax()');

    echo "=== failure UI reads framework Response::json data string ===\n";

    TestAssert::contains(
        $cfg,
        "typeof r.data === 'string' ? r.data",
        'failure alert falls back to string data (alert_back/error JSON shape)'
    );

    echo "=== AdminController formcheck still uses alert_back (JSON when is_ajax) ===\n";

    $admin = file_get_contents(APP_PATH . '/common/AdminController.php');
    TestAssert::false($admin === false, 'AdminController readable');
    TestAssert::contains($admin, "alert_back('表单提交校验失败,请刷新后重试！');", 'formcheck failure path');

    $helper = file_get_contents(CORE_PATH . '/function/helper.php');
    TestAssert::false($helper === false, 'helper.php readable');
    $pos = strpos($helper, 'function alert_back(');
    TestAssert::false($pos === false, 'alert_back found');
    $chunk = substr($helper, $pos, 400);
    TestAssert::contains($chunk, 'is_ajax()', 'alert_back branches on is_ajax');
    TestAssert::contains($chunk, 'Response::json', 'alert_back returns JSON for XHR');
});
