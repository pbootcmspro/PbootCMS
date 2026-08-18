<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/file.php upload_image_content_type upload_security_headers_map
 *
 * 说明：实际上传文件由 Apache/Nginx 按 rewrite 配置直出；
 * MIME/nosniff 以纯函数 map 断言，与 rewrite 模板对照。
 */

if (! function_exists('upload_security_headers_map')) {
    require dirname(__DIR__) . '/bootstrap_upload.php';
}

return TestAssert::runSuite(function () {
    echo "=== upload_image_content_type MIME map ===\n";

    TestAssert::same('image/svg+xml', upload_image_content_type('svg'), 'svg mime');
    TestAssert::same('image/svg+xml', upload_image_content_type('.svgz'), 'svgz mime with dot');
    TestAssert::same('image/avif', upload_image_content_type('AVIF'), 'avif mime case-insensitive');
    TestAssert::same('image/webp', upload_image_content_type('webp'), 'webp mime');
    TestAssert::same('image/jpeg', upload_image_content_type('jpg'), 'jpg mime');
    TestAssert::same('', upload_image_content_type('php'), 'php has no image mime');
    TestAssert::same('', upload_image_content_type(''), 'empty ext has no mime');

    echo "=== upload_security_headers_map nosniff + content-type ===\n";

    $svgMap = upload_security_headers_map('svg');
    TestAssert::same('image/svg+xml', $svgMap['Content-Type'] ?? null, 'svg map Content-Type');
    TestAssert::same('nosniff', $svgMap['X-Content-Type-Options'] ?? null, 'svg map nosniff');
    TestAssert::true(! isset($svgMap['Content-Encoding']), 'svg omits Content-Encoding');

    $svgzMap = upload_security_headers_map('svgz');
    TestAssert::same('image/svg+xml', $svgzMap['Content-Type'] ?? null, 'svgz map Content-Type');
    TestAssert::same('gzip', $svgzMap['Content-Encoding'] ?? null, 'svgz map Content-Encoding gzip');
    TestAssert::same('nosniff', $svgzMap['X-Content-Type-Options'] ?? null, 'svgz map nosniff');

    $avifMap = upload_security_headers_map('avif');
    TestAssert::same('image/avif', $avifMap['Content-Type'] ?? null, 'avif map Content-Type');
    TestAssert::same('nosniff', $avifMap['X-Content-Type-Options'] ?? null, 'avif map nosniff');

    $unknownMap = upload_security_headers_map('exe');
    TestAssert::true(! isset($unknownMap['Content-Type']), 'unknown ext omits Content-Type');
    TestAssert::same('nosniff', $unknownMap['X-Content-Type-Options'] ?? null, 'unknown ext still nosniff');
});
