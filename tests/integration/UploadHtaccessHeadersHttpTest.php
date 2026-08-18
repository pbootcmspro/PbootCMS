<?php

declare(strict_types=1);

/**
 * @suite integration
 * @covers rewrite/static-upload.htaccess 经真实 HTTP 响应头生效
 *
 * 门禁：仅当环境变量 PBOOT_TEST_BASE_URL 非空时执行（需本机 Web 可访问站点）。
 *
 * 运行：
 *   set PBOOT_TEST_BASE_URL=http://127.0.0.1/PbootCMS-dev
 *   php tests/run.php --suite=integration --filter=UploadHtaccessHeadersHttp
 *
 * 无门禁时 SKIP（exit 0），不拖垮默认 CI。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}
if (! function_exists('upload_ensure_htaccess')) {
    require dirname(__DIR__) . '/bootstrap_upload.php';
}

return TestAssert::runSuite(function () {
    $base = getenv('PBOOT_TEST_BASE_URL');
    if (! is_string($base) || trim($base) === '') {
        echo "SKIP: set PBOOT_TEST_BASE_URL to enable HTTP header probe\n";
        return;
    }
    if (! function_exists('curl_init')) {
        echo "SKIP: curl extension required for HTTP header probe\n";
        return;
    }

    $base = rtrim(trim($base), '/');

    echo "=== ensure deploy + write probe SVG ===\n";

    TestAssert::true(upload_ensure_htaccess() === true, 'upload_ensure_htaccess succeeds');
    $destHt = upload_htaccess_deploy_path();
    TestAssert::true(is_file($destHt), 'static/upload/.htaccess present');

    $dir = ROOT_PATH . 'static/upload/image/_http_hdr_it';
    if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
        TestAssert::true(false, 'probe dir creatable');
        return;
    }

    $rel = '/static/upload/image/_http_hdr_it/probe.svg';
    $abs = ROOT_PATH . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $rel), DIRECTORY_SEPARATOR);
    $svgBody = '<svg xmlns="http://www.w3.org/2000/svg"><circle r="2"/></svg>';
    TestAssert::true(file_put_contents($abs, $svgBody) !== false, 'probe svg written');

    $url = $base . $rel;
    echo "URL: {$url}\n";

    echo "=== HEAD expects svg+xml + nosniff ===\n";

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
    ));
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    TestAssert::true(is_string($raw) && $raw !== '', 'HEAD returned headers' . ($err !== '' ? " ($err)" : ''));
    TestAssert::true($code >= 200 && $code < 400, "HTTP status ok (got {$code})");

    $raw = is_string($raw) ? $raw : '';
    TestAssert::true(
        (bool) preg_match('/^Content-Type:\\s*image\\/svg\\+xml/im', $raw),
        'Content-Type image/svg+xml'
    );
    TestAssert::true(
        (bool) preg_match('/^X-Content-Type-Options:\\s*nosniff/im', $raw),
        'X-Content-Type-Options nosniff'
    );

    // 保留探针文件便于手工复查；目录内仅此文件时可清理
    @unlink($abs);
    @rmdir($dir);
});
