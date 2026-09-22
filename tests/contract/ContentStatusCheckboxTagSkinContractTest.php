<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers 内容新增/编辑页状态复选框 lay-skin="tag"（Layui 2.13 视觉回归）
 *
 * Layui 2.13 缺省 lay-skin 为 primary（小方框）；置顶/推荐/头条需显式 tag 以保持块状外观。
 *
 *   php tests/run.php --suite=contract --filter=ContentStatusCheckboxTagSkin
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== content.html status checkboxes use lay-skin=tag ===\n";

    $path = APP_PATH . '/admin/view/default/content/content.html';
    $src = file_get_contents($path);
    TestAssert::false($src === false, 'content.html readable');

    $names = array('istop', 'isrecommend', 'isheadline');
    $total = 0;

    foreach ($names as $name) {
        $pattern = '/<input\b[^>]*\bname="' . preg_quote($name, '/') . '"[^>]*>/i';
        $count = preg_match_all($pattern, $src, $matches);
        TestAssert::same(2, $count, 'name="' . $name . '" checkbox count is 2 (add+edit)');
        foreach ($matches[0] as $i => $tag) {
            $total++;
            TestAssert::contains($tag, 'type="checkbox"', 'name="' . $name . '" #' . ($i + 1) . ' is checkbox');
            TestAssert::contains($tag, 'lay-skin="tag"', 'name="' . $name . '" #' . ($i + 1) . ' has lay-skin="tag"');
        }
    }

    TestAssert::same(6, $total, 'exactly 6 status checkboxes locked to tag skin');
});
