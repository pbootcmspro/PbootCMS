<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/common/AdminController.php checkLevel public_path
 * @covers apps/admin/view/default/common/head.html cache entry
 *
 * 验收：clearCache / clearOnlySysCache / DeleCache 对已登录用户开放；
 * 后台头部向普通内容管理员展示清缓存入口。
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * 从 checkLevel 方法体中提取 $public_path 数组字面量内的路径字符串。
 */
function admin_cache_extract_public_paths(string $source): array
{
    if (!preg_match('/function checkLevel\(\)\s*\{([\s\S]*?)\n    \}/', $source, $m)) {
        return array();
    }
    $body = $m[1];
    if (!preg_match('/\$public_path\s*=\s*array\s*\(([\s\S]*?)\);/', $body, $m2)) {
        return array();
    }
    preg_match_all("/'(\\/admin\\/[^']+)'/", $m2[1], $paths);
    return $paths[1];
}

return TestAssert::runSuite(function () {
    $controller = APP_PATH . '/common/AdminController.php';
    $head = APP_PATH . '/admin/view/default/common/head.html';

    TestAssert::true(is_file($controller), 'AdminController.php exists');
    TestAssert::true(is_file($head), 'head.html exists');

    $source = file_get_contents($controller);
    $paths = admin_cache_extract_public_paths($source);

    echo "=== checkLevel public_path includes cache endpoints ===\n";

    $required = array(
        '/admin/Index/clearCache',
        '/admin/Index/clearOnlySysCache',
        '/admin/DeleCache',
        '/admin/DeleCache/index',
    );
    foreach ($required as $path) {
        TestAssert::true(in_array($path, $paths, true), $path . ' in public_path');
    }

    echo "=== head.html shows DeleCache entry for content admins ===\n";

    $headHtml = file_get_contents($head);
    $entryCount = substr_count($headHtml, '/admin/DeleCache/index');
    TestAssert::true($entryCount >= 2, 'desktop and mobile cache entries present');
    TestAssert::true(strpos($headHtml, '清理缓存') !== false, 'cache label present');
});
