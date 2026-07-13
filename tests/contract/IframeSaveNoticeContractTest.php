<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers ContentController / SingleController / LabelController 保存成功后调用白名单 sync notice
 *
 * 运行：
 *   php tests/run.php --suite=contract --filter=IframeSaveNotice
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . 'apps');
}

return TestAssert::runSuite(function () {
    echo "=== ContentController calls iframe_whitelist_sync_notice_many ===\n";

    $contentSrc = file_get_contents(APP_PATH . '/admin/controller/content/ContentController.php');
    TestAssert::false($contentSrc === false, 'ContentController readable');
    TestAssert::true(
        substr_count($contentSrc, 'iframe_whitelist_sync_notice_many') >= 2,
        'ContentController: notice_many on add + mod'
    );
    TestAssert::contains($contentSrc, "'新增成功！'", 'ContentController: add prefix');
    TestAssert::contains($contentSrc, "'修改成功！'", 'ContentController: mod prefix');

    echo "=== SingleController calls iframe_whitelist_sync_notice_many ===\n";

    $singleSrc = file_get_contents(APP_PATH . '/admin/controller/content/SingleController.php');
    TestAssert::false($singleSrc === false, 'SingleController readable');
    TestAssert::contains($singleSrc, 'iframe_whitelist_sync_notice_many', 'SingleController: notice_many');
    TestAssert::contains($singleSrc, "'修改成功！'", 'SingleController: mod prefix');

    echo "=== LabelController calls iframe_whitelist_sync_notice ===\n";

    $labelSrc = file_get_contents(APP_PATH . '/admin/controller/content/LabelController.php');
    TestAssert::false($labelSrc === false, 'LabelController readable');
    TestAssert::contains($labelSrc, 'iframe_whitelist_sync_notice(', 'LabelController: notice');
    TestAssert::contains($labelSrc, "'修改成功！'", 'LabelController: mod prefix');

    echo "=== ConfigModel clears RUN_PATH/config after successful append ===\n";

    $modelSrc = file_get_contents(APP_PATH . '/admin/model/system/ConfigModel.php');
    TestAssert::false($modelSrc === false, 'ConfigModel readable');
    $posAppend = strpos($modelSrc, 'function appendIframeWhitelistHosts');
    TestAssert::false($posAppend === false, 'appendIframeWhitelistHosts found');
    $posNext = strpos($modelSrc, 'function getAreaTheme', $posAppend);
    TestAssert::false($posNext === false, 'getAreaTheme found after append');
    $body = substr($modelSrc, $posAppend, $posNext - $posAppend);
    TestAssert::contains($body, "path_delete(RUN_PATH . '/config')", 'ConfigModel: path_delete config cache');
    TestAssert::contains($body, '白名单写入失败', 'ConfigModel: write-fail msg');
});
