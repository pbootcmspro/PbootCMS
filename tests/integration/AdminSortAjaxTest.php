<?php

/** @suite integration @covers 四个排序控制器的 Ajax 保存和空列表错误 */
if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    foreach (array('Content', 'ContentSort', 'Slide', 'Link') as $controller) {
        foreach (array('save', 'empty') as $mode) {
            $args = array(PHP_BINARY, TEST_ROOT . 'scripts/admin_sort_ajax_probe.php', $controller, $mode);
            $lines = array();
            exec(implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1', $lines, $status);
            TestAssert::same(0, $status, "$controller $mode exits successfully");
            $parts = explode("\nSORT_CALLS=", implode("\n", $lines), 2);
            TestAssert::same(2, count($parts), 'sorting calls captured');
            $response = json_decode($parts[0], true);
            $calls = json_decode(isset($parts[1]) ? $parts[1] : '', true);
            TestAssert::true(is_array($response), 'valid JSON response');
            TestAssert::same('', $response['tourl'], 'response has no navigation target');
            if ($mode === 'save') {
                TestAssert::same(1, $response['code'], "$controller Ajax sorting succeeds without mcode or backurl");
                TestAssert::same(array('12', 255, 255), $response['data']['sorting'], 'returns normalized sorting values');
                TestAssert::same(array(array('7', 'sorting=12'), array('8', 'sorting=255'), array('9', 'sorting=255')), $calls, 'writes the reported values');
            } else {
                TestAssert::same(0, $response['code'], 'empty list reports an error');
                TestAssert::same('排序失败，无任何内容！', $response['data'], 'empty list error message');
                TestAssert::same(array(), $calls, 'empty list performs no writes');
            }
        }
    }
});
