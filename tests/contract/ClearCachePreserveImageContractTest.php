<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers IndexController clearCache / clearOnlySysCache 保留 runtime/image
 *
 * 审计 #11：防止常规清缓存误删缩略图回归
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== purge_runtime_cache helper exists ===\n";

    $fileFn = file_get_contents(CORE_PATH . '/function/file.php');
    TestAssert::false($fileFn === false, 'file.php readable');
    TestAssert::contains($fileFn, 'function purge_runtime_cache(', 'purge_runtime_cache defined');
    TestAssert::contains($fileFn, "'complile'", 'whitelist includes complile');
    TestAssert::contains($fileFn, "'config'", 'whitelist includes config');
    TestAssert::contains($fileFn, "'upgrade'", 'whitelist includes upgrade');
    TestAssert::contains($fileFn, "'cache'", 'whitelist can include cache');
    // 白名单循环中不得出现 image，避免常规模式误删缩略图
    $posFn = strpos($fileFn, 'function purge_runtime_cache(');
    TestAssert::false($posFn === false, 'purge_runtime_cache position found');
    $posNext = strpos($fileFn, "\nfunction ", $posFn + 1);
    $body = $posNext === false ? substr($fileFn, $posFn) : substr($fileFn, $posFn, $posNext - $posFn);
    TestAssert::notContains($body, "'image'", 'helper body must not whitelist image');
    TestAssert::notContains($body, '"image"', 'helper body must not whitelist image dq');
    TestAssert::contains($body, 'if ($delall)', 'delall branch present');
    TestAssert::contains($body, 'path_delete($runPath)', 'delall deletes whole run path');

    echo "=== IndexController uses purge_runtime_cache ===\n";

    $src = file_get_contents(APP_PATH . '/admin/controller/IndexController.php');
    TestAssert::false($src === false, 'IndexController readable');

    $posClear = strpos($src, 'function clearCache(');
    $posSys = strpos($src, 'function clearOnlySysCache(');
    $posSession = strpos($src, 'function clearSession(');
    TestAssert::false($posClear === false, 'clearCache() found');
    TestAssert::false($posSys === false, 'clearOnlySysCache() found');
    TestAssert::true($posClear < $posSys, 'clearCache before clearOnlySysCache');

    $clearBody = substr($src, $posClear, $posSys - $posClear);
    $sysBody = substr(
        $src,
        $posSys,
        $posSession > $posSys ? $posSession - $posSys : strlen($src)
    );

    TestAssert::contains($clearBody, "purge_runtime_cache(RUN_PATH, 'all'", 'clearCache: mode all');
    TestAssert::contains($clearBody, "VisitsCounter::flushAll()", 'clearCache: flush visits before purge');
    TestAssert::contains($clearBody, '$delall', 'clearCache: delall variable');
    TestAssert::notContains($clearBody, 'path_delete(RUN_PATH . \'/image', 'clearCache: no direct image delete');
    TestAssert::notContains($clearBody, 'path_delete(RUN_PATH)', 'clearCache: no bare RUN_PATH delete outside helper');

    TestAssert::contains($sysBody, "purge_runtime_cache(RUN_PATH, 'sys'", 'clearOnlySysCache: mode sys');
    TestAssert::contains($sysBody, "VisitsCounter::flushAll()", 'sys: flush visits before purge');
    TestAssert::contains($sysBody, '$delall', 'sys: delall variable');
    TestAssert::notContains($sysBody, 'path_delete(RUN_PATH . \'/image', 'sys: no direct image delete');
    TestAssert::notContains($sysBody, 'path_delete(RUN_PATH)', 'sys: no bare RUN_PATH delete outside helper');

    echo "=== admin UI does not pass delall ===\n";

    $view = file_get_contents(APP_PATH . '/admin/view/default/content/delecache.html');
    TestAssert::false($view === false, 'delecache.html readable');
    TestAssert::contains($view, '{url./admin/Index/clearOnlySysCache}', 'UI links clearOnlySysCache');
    TestAssert::contains($view, '{url./admin/Index/clearCache}', 'UI links clearCache');
    TestAssert::notContains($view, 'delall', 'UI must not pass delall');
});
