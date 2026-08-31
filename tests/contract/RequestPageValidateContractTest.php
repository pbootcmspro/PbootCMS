<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers core\basic\Request
 * @covers app\common\HomeController page query validation hook
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== Request.php exists with raw query page rules ===\n";

    $requestSrc = file_get_contents(CORE_PATH . '/basic/Request.php');
    TestAssert::false($requestSrc === false, 'Request.php readable');
    TestAssert::contains($requestSrc, 'namespace core\\basic;', 'Request namespace');
    TestAssert::contains($requestSrc, 'function validate()', 'Request validate entry');
    TestAssert::contains($requestSrc, 'QUERY_STRING', 'Request uses raw QUERY_STRING');
    TestAssert::contains($requestSrc, 'splitQueryPairs', 'Request manual query split');
    TestAssert::contains($requestSrc, 'http_response_code(400)', 'Request rejects with HTTP 400');
    TestAssert::contains($requestSrc, "'Bad Request'", 'Request generic 400 body');
    TestAssert::notContains($requestSrc, 'parse_str(', 'Request does not call parse_str');

    echo "=== HomeController validates before other bootstrap logic ===\n";

    $homeSrc = file_get_contents(APP_PATH . '/common/HomeController.php');
    TestAssert::false($homeSrc === false, 'HomeController readable');
    TestAssert::contains($homeSrc, 'use core\\basic\\Request;', 'HomeController imports Request');
    TestAssert::contains($homeSrc, 'Request::validate();', 'HomeController calls Request::validate');
    $validatePos = strpos($homeSrc, 'Request::validate();');
    $cachePos = strpos($homeSrc, 'cache_config();');
    TestAssert::true($validatePos !== false && $cachePos !== false && $validatePos < $cachePos, 'validate runs before cache_config');

    echo "=== cache cleanup guidance mentions security upgrade ===\n";

    $delecache = file_get_contents(APP_PATH . '/admin/view/default/content/delecache.html');
    TestAssert::false($delecache === false, 'delecache.html readable');
    TestAssert::contains($delecache, '清理所有缓存', 'delecache: clear all cache link');
    TestAssert::contains($delecache, 'page', 'delecache: page param security note');
});
