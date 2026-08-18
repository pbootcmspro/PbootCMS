<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/file.php gd_can_post_process_image reencode_image upload_post_process_image avif
 */

if (! function_exists('gd_can_post_process_image')) {
    require dirname(__DIR__) . '/bootstrap_upload.php';
}

if (! class_exists('ImagickSecurityPayloads', false)) {
    require dirname(__DIR__) . '/fixtures/security/ImagickSecurityPayloads.php';
}

return TestAssert::runSuite(function () {
    $tmpdir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot_avif_test_' . mt_rand(10000, 99999);
    if (! mkdir($tmpdir) && ! is_dir($tmpdir)) {
        echo "SKIP: cannot create temp dir\n";
        return;
    }

    $writeAvifFixture = function (string $path): bool {
        if (gd_supports_avif()) {
            $img = @imagecreatetruecolor(4, 4);
            if (! $img) {
                return false;
            }
            $ok = @imageavif($img, $path);
            gd_free_image($img);
            return $ok && is_file($path);
        }
        // 最小 ftyp/avif 头（非完整可解码图，仅供魔数与跳过路径）
        $bytes = "\x00\x00\x00\x1cftypavif\x00\x00\x00\x00avifmif1miaf";
        return file_put_contents($path, $bytes) !== false;
    };

    $avifPath = $tmpdir . DIRECTORY_SEPARATOR . 'sample.avif';
    if (! $writeAvifFixture($avifPath)) {
        echo "SKIP: cannot write AVIF fixture\n";
        @rmdir($tmpdir);
        return;
    }

    TestAssert::true(avif_file_looks_valid($avifPath), 'avif_file_looks_valid accepts fixture');

    echo "=== AVIF post-process when GD lacks AVIF ===\n";

    $GLOBALS['__test_gd_supports_avif'] = false;
    $GLOBALS['__test_image_backend_for'] = 'gd';
    $originalSize = filesize($avifPath);
    $originalHash = md5_file($avifPath);
    $reject = avif_unsupported_error();

    if (is_image($avifPath)) {
        list($can, $msg) = gd_can_post_process_image($avifPath);
        TestAssert::false($can, 'gd_can_post_process_image false without GD AVIF');
        TestAssert::contains($msg, 'AVIF', 'skip message mentions AVIF');

        TestAssert::same(true, reencode_image($avifPath), 'reencode_image skips without error');
        TestAssert::same($originalHash, md5_file($avifPath), 'reencode preserves file');

        TestAssert::same($reject, upload_post_process_image($avifPath, false, true), 'post-process rejects without AVIF backend');
        TestAssert::same('', upload_post_process_last_notice(), 'reject does not set keep-original notice');
        TestAssert::true(is_file($avifPath), 'file left for caller to unlink');
        TestAssert::same($originalSize, filesize($avifPath), 'size unchanged after reject');
    } else {
        TestAssert::same($reject, upload_post_process_image($avifPath, false, true), 'post-process rejects when not recognized as raster');
        TestAssert::same('', upload_post_process_last_notice(), 'unrecognized avif reject has no notice');
        TestAssert::true(is_file($avifPath), 'file left for caller to unlink');
    }

    unset($GLOBALS['__test_gd_supports_avif'], $GLOBALS['__test_image_backend_for']);

    echo "=== AVIF post-process when GD supports AVIF ===\n";

    if (! gd_supports_avif()) {
        echo "SKIP: environment lacks GD AVIF support\n";
    } else {
        $avifPath2 = $tmpdir . DIRECTORY_SEPARATOR . 'sample2.avif';
        if (! $writeAvifFixture($avifPath2)) {
            echo "SKIP: cannot write second AVIF fixture\n";
        } else {
            list($can2, $msg2) = gd_can_post_process_image($avifPath2);
            TestAssert::true($can2, 'gd_can_post_process_image true with GD AVIF');
            TestAssert::same('', $msg2, 'no skip message');
            TestAssert::same(true, upload_post_process_image($avifPath2, false, true), 'full post-process ok');
            TestAssert::same('', upload_post_process_last_notice(), 'no notice after full process');
            @unlink($avifPath2);
        }
    }

    // 伪装文件应判定无效
    $fake = $tmpdir . DIRECTORY_SEPARATOR . 'fake.avif';
    file_put_contents($fake, '<?php echo 1;');
    TestAssert::false(avif_file_looks_valid($fake), 'php shell not valid avif');

    $heicAsAvif = $tmpdir . DIRECTORY_SEPARATOR . 'heic.avif';
    file_put_contents($heicAsAvif, ImagickSecurityPayloads::heicHead());
    TestAssert::same('HEIC', imagick_magic_identify(ImagickSecurityPayloads::heicHead()), 'heic ftyp identifies as HEIC');
    TestAssert::false(avif_file_looks_valid($heicAsAvif), 'HEIC brand is not valid AVIF');

    echo "=== AVIF post-process rejects fake via extension path ===\n";

    TestAssert::same('不是有效的AVIF图片！', upload_post_process_image($fake, false, true), 'post-process rejects fake avif');
    TestAssert::true(is_file($fake), 'fake avif file left on disk for caller to unlink');

    @unlink($avifPath);
    @unlink($fake);
    @unlink($heicAsAvif);
    @rmdir($tmpdir);
});
