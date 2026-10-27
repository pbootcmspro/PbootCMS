<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers Issue #319 后台 UEditor 固定高度、编辑区内滚动
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== ueditor.config.js fixed height and no auto-grow ===\n";

    $cfg = file_get_contents(CORE_PATH . '/extend/ueditor/ueditor.config.js');
    TestAssert::false($cfg === false, 'ueditor.config.js readable');
    TestAssert::true(
        preg_match('/^\s*,initialFrameHeight\s*:\s*600\b/m', $cfg) === 1,
        'initialFrameHeight is 600'
    );
    TestAssert::true(
        preg_match('/^\s*,autoHeightEnabled\s*:\s*false\b/m', $cfg) === 1,
        'autoHeightEnabled is false'
    );
    TestAssert::true(
        preg_match('/^\s*,initialFrameHeight\s*:\s*500\b/m', $cfg) !== 1,
        'legacy initialFrameHeight 500 must not remain'
    );
    TestAssert::true(
        preg_match('/^\s*,autoHeightEnabled\s*:\s*true\b/m', $cfg) !== 1,
        'legacy autoHeightEnabled true must not remain'
    );

    echo "=== admin templates do not override height options ===\n";

    $files = array(
        APP_PATH . '/admin/view/default/common/ueditor.html',
        APP_PATH . '/admin/view/default/content/content.html',
        APP_PATH . '/admin/view/default/content/single.html',
        APP_PATH . '/admin/view/default/content/label.html',
    );
    foreach ($files as $path) {
        $html = file_get_contents($path);
        TestAssert::false($html === false, basename($path) . ' readable');
        TestAssert::true(strpos($html, 'autoHeightEnabled') === false, basename($path) . ' does not override autoHeightEnabled');
        TestAssert::true(strpos($html, 'initialFrameHeight') === false, basename($path) . ' does not override initialFrameHeight');
        TestAssert::true(strpos($html, 'setHeight') === false, basename($path) . ' does not call setHeight');
    }
});
