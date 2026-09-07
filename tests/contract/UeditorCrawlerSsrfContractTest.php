<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers Issue #200 UEditor 远程抓图 SSRF 加固契约
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== remote_fetch.php exposes SSRF helpers ===\n";

    $remote = file_get_contents(CORE_PATH . '/function/remote_fetch.php');
    TestAssert::false($remote === false, 'remote_fetch.php readable');
    TestAssert::contains($remote, 'function remote_fetch_is_blocked_ip(', 'is_blocked_ip defined');
    TestAssert::contains($remote, 'function remote_fetch_validate_image_url(', 'validate_image_url defined');
    TestAssert::contains($remote, 'function remote_fetch_http_get_pinned(', 'http_get_pinned defined');
    TestAssert::contains($remote, 'function remote_fetch_verify_image_body(', 'verify_image_body defined');
    TestAssert::contains($remote, 'function remote_fetch_image(', 'remote_fetch_image defined');
    TestAssert::contains($remote, 'CURLOPT_RESOLVE', 'DNS pinned via CURLOPT_RESOLVE');
    TestAssert::contains($remote, 'CURLOPT_FOLLOWLOCATION', 'follow location option set');
    TestAssert::contains($remote, 'getimagesizefromstring', 'raster verify via getimagesizefromstring');
    TestAssert::contains($remote, '169.254', 'link-local range blocked');
    TestAssert::contains($remote, '100.64.0.0/10', 'CGNAT range blocked');
    TestAssert::contains($remote, 'svgz_decompress_limited_string', 'svgz uses limited decompress');
    TestAssert::contains($remote, 'CURLOPT_SSL_VERIFYPEER, true', 'HTTPS peer verification enabled');
    TestAssert::contains($remote, 'CURLOPT_SSL_VERIFYHOST, 2', 'HTTPS hostname verification enabled');
    TestAssert::contains($remote, 'CORE_PATH . \'/cacert.pem\'', 'uses project CA bundle');
    TestAssert::true(strpos($remote, 'CURLOPT_SSL_VERIFYHOST, 0') === false, 'must not disable TLS hostname check');
    TestAssert::true(strpos($remote, 'CURLOPT_SSL_VERIFYPEER, false') === false, 'must not disable TLS peer check');

    echo "=== init.php does not globally load remote_fetch ===\n";

    $init = file_get_contents(CORE_PATH . '/init.php');
    TestAssert::true(strpos($init, '/function/remote_fetch.php') === false, 'init must not require remote_fetch');

    echo "=== Uploader.saveRemote uses remote_fetch_image ===\n";

    $uploader = file_get_contents(CORE_PATH . '/extend/ueditor/php/Uploader.class.php');
    TestAssert::contains($uploader, 'remote_fetch_image(', 'saveRemote calls remote_fetch_image');
    TestAssert::contains($uploader, 'mapRemoteFetchError', 'error mapping present');
    TestAssert::contains($uploader, 'function/remote_fetch.php', 'Uploader lazy-loads remote_fetch');
    TestAssert::true(strpos($uploader, 'gethostbyname(') === false, 'no gethostbyname in Uploader');
    TestAssert::true(strpos($uploader, 'get_headers(') === false, 'no get_headers in Uploader');
    TestAssert::true(strpos($uploader, 'readfile($imgUrl') === false, 'no readfile remote fetch in Uploader');

    echo "=== action_crawler bounded time limit ===\n";

    $crawler = file_get_contents(CORE_PATH . '/extend/ueditor/php/action_crawler.php');
    TestAssert::true(strpos($crawler, 'set_time_limit(0)') === false, 'no unlimited set_time_limit(0)');
    TestAssert::contains($crawler, 'set_time_limit(30)', 'bounded set_time_limit(30)');
    TestAssert::contains($crawler, 'upload_should_post_process_image($full_path)', 'post-process still wired');
});
