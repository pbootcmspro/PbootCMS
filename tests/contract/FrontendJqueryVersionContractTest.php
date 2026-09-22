<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers 前台默认模板 jQuery 3.7.1 引用与文件一致性
 *
 *   php tests/run.php --suite=contract --filter=FrontendJqueryVersion
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== frontend default template jQuery 3.7.1 ===\n";

    $jsDir = ROOT_PATH . 'template/default/js/';
    $head = ROOT_PATH . 'template/default/html/comm/head.html';
    $upload = ROOT_PATH . 'template/default/html/comm/upload.html';

    TestAssert::true(is_file($jsDir . 'jquery-3.7.1.min.js'), 'jquery-3.7.1.min.js exists');
    TestAssert::false(is_file($jsDir . 'jquery-1.12.4.min.js'), 'jquery-1.12.4.min.js removed');

    $headSrc = file_get_contents($head);
    TestAssert::false($headSrc === false, 'head.html readable');
    TestAssert::contains($headSrc, 'jquery-3.7.1.min.js', 'head.html references jquery-3.7.1.min.js');
    TestAssert::notContains($headSrc, 'jquery-1.12.4.min.js', 'head.html does not reference jquery-1.12.4.min.js');

    $uploadSrc = file_get_contents($upload);
    TestAssert::false($uploadSrc === false, 'upload.html readable');
    TestAssert::notContains($uploadSrc, "$(document).on('ready'", 'upload.html does not use removed on(ready)');
    TestAssert::notContains($uploadSrc, 'kartik-file-errors', 'upload.html does not share one error container id');
    TestAssert::contains($uploadSrc, 'closest(".modal")', 'upload.html scopes uploadSubmit to its own modal');
});
