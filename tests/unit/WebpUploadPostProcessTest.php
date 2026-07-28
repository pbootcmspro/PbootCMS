<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/file.php gd_can_post_process_image reencode_image upload_post_process_image
 */

if (! function_exists('gd_can_post_process_image')) {
    require dirname(__DIR__) . '/bootstrap_upload.php';
}

return TestAssert::runSuite(function () {
    $tmpdir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot_webp_test_' . mt_rand(10000, 99999);
    if (! mkdir($tmpdir) && ! is_dir($tmpdir)) {
        echo "SKIP: cannot create temp dir\n";
        return;
    }

    $writeWebpFixture = function (string $path): bool {
        if (gd_supports_webp()) {
            $img = @imagecreatetruecolor(4, 4);
            if (! $img) {
                return false;
            }
            $ok = @imagewebp($img, $path);
            gd_free_image($img);
            return $ok && is_file($path);
        }
        $webpBytes = base64_decode('UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoBAAEAAQAcJaQAA3AA/v56QAAA');
        return $webpBytes !== false && file_put_contents($path, $webpBytes) !== false;
    };

    $webpPath = $tmpdir . DIRECTORY_SEPARATOR . 'sample.webp';
    if (! $writeWebpFixture($webpPath)) {
        echo "SKIP: cannot write WebP fixture\n";
        return;
    }
    if (! is_image($webpPath)) {
        echo "SKIP: current PHP cannot identify WebP images\n";
        @unlink($webpPath);
        @rmdir($tmpdir);
        return;
    }

    $originalHash = md5_file($webpPath);
    $originalSize = filesize($webpPath);

    echo "=== WebP post-process when GD lacks WebP support ===\n";

    $GLOBALS['__test_gd_supports_webp'] = false;

    TestAssert::true(is_image($webpPath), 'is_image recognizes WebP fixture');

    list($can, $msg) = gd_can_post_process_image($webpPath);
    TestAssert::false($can, 'gd_can_post_process_image returns false without GD WebP');
    TestAssert::contains($msg, 'WebP', 'skip message mentions WebP');

    TestAssert::same(true, reencode_image($webpPath), 'reencode_image skips without error');
    TestAssert::same($originalHash, md5_file($webpPath), 'reencode_image preserves original file');

    TestAssert::same(true, upload_post_process_image($webpPath, false, true), 'upload_post_process_image succeeds');
    TestAssert::contains(upload_post_process_last_notice(), 'WebP', 'graceful skip sets notice');
    TestAssert::true(is_file($webpPath), 'WebP file retained after post-process skip');
    TestAssert::same($originalSize, filesize($webpPath), 'file size unchanged after skip');

    unset($GLOBALS['__test_gd_supports_webp']);

    echo "=== WebP post-process when GD supports WebP ===\n";

    $webpPath2 = $tmpdir . DIRECTORY_SEPARATOR . 'sample2.webp';
    if (! $writeWebpFixture($webpPath2)) {
        echo "SKIP: cannot write second WebP fixture\n";
        @unlink($webpPath);
        @rmdir($tmpdir);
        return;
    }

    if (! gd_supports_webp()) {
        echo "SKIP: environment lacks GD WebP support\n";
    } else {
        list($can2, $msg2) = gd_can_post_process_image($webpPath2);
        TestAssert::true($can2, 'gd_can_post_process_image returns true with GD WebP');
        TestAssert::same('', $msg2, 'no skip message when GD WebP available');

        TestAssert::same(true, reencode_image($webpPath2), 'reencode_image succeeds with GD WebP');
        TestAssert::same(true, upload_post_process_image($webpPath2, false, true), 'upload_post_process_image succeeds with GD WebP');
        TestAssert::same('', upload_post_process_last_notice(), 'no notice after full post-process');
        TestAssert::true(is_file($webpPath2), 'WebP file exists after GD post-process');
    }

    echo "=== PNG post-process unaffected by WebP GD flag ===\n";

    if (! function_exists('imagepng') || ! function_exists('imagecreatetruecolor')) {
        echo "SKIP: GD PNG unavailable\n";
    } else {
        $pngPath = $tmpdir . DIRECTORY_SEPARATOR . 'sample.png';
        $img = imagecreatetruecolor(2, 2);
        imagepng($img, $pngPath);
        gd_free_image($img);

        $GLOBALS['__test_gd_supports_webp'] = false;
        list($canPng, $msgPng) = gd_can_post_process_image($pngPath);
        TestAssert::true($canPng, 'PNG still eligible for post-process when WebP GD disabled');
        TestAssert::same('', $msgPng, 'PNG has no WebP skip message');
        unset($GLOBALS['__test_gd_supports_webp']);

        @unlink($pngPath);
    }

    @unlink($webpPath);
    @unlink($webpPath2 ?? '');
    @rmdir($tmpdir);
});
