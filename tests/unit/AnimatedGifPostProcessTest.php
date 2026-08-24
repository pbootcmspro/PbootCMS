<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/file.php gif_scan_bytes gif_scan_file is_animated_gif gif_structure_is_safe
 *         reencode_image upload_post_process_image resize_img cut_img watermark_img gd_can_post_process_image
 */

if (! function_exists('is_animated_gif')) {
    require dirname(__DIR__) . '/bootstrap_upload.php';
}

if (! class_exists('ConfigStub', false)) {
    require dirname(__DIR__) . '/support/ConfigStub.php';
}

exit(TestAssert::runSuite(function () {
    $tmpdir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot_anim_gif_' . mt_rand(10000, 99999);
    if (! mkdir($tmpdir) && ! is_dir($tmpdir)) {
        echo "SKIP: cannot create temp dir\n";
        return;
    }

    $frameCount = function ($path) {
        $scan = gif_scan_file($path);
        return $scan === false ? 0 : (int) $scan['frames'];
    };

    // 双帧 1x1 GIF89a（Netscape 循环 + 2 个 GCE + 2 个 Image Descriptor）
    $animBytes = hex2bin(
        '474946383961' .
        '01000100' .
        '00' .
        '00' .
        '00' .
        '21ff0b4e45545343415045322e300301000000' .
        '21f904000a000000' .
        '2c00000000010001000002014c00' .
        '21f904000a000000' .
        '2c00000000010001000002014c00' .
        '3b'
    );
    if ($animBytes === false) {
        echo "SKIP: cannot build animated GIF fixture\n";
        @rmdir($tmpdir);
        return;
    }

    $animPath = $tmpdir . DIRECTORY_SEPARATOR . 'anim.gif';
    file_put_contents($animPath, $animBytes);
    $info = @getimagesize($animPath);
    if (! $info || (int) $info[2] !== IMAGETYPE_GIF) {
        echo "SKIP: environment cannot identify animated GIF fixture\n";
        @unlink($animPath);
        @rmdir($tmpdir);
        return;
    }
    if (! is_animated_gif($animPath)) {
        echo "SKIP: fixture not detected as animated (frames=" . $frameCount($animPath) . ")\n";
        @unlink($animPath);
        @rmdir($tmpdir);
        return;
    }

    $framesBefore = $frameCount($animPath);
    $hashBefore = md5_file($animPath);

    echo "=== animated GIF detection & structure ===\n";
    TestAssert::true(is_animated_gif($animPath), 'is_animated_gif detects multi-frame GIF');
    TestAssert::true(gif_structure_is_safe($animPath), 'gif_structure_is_safe accepts clean animated GIF');
    TestAssert::true($framesBefore > 1, 'fixture has more than one image descriptor');

    $scanAnim = gif_scan_bytes($animBytes);
    TestAssert::true(is_array($scanAnim), 'gif_scan_bytes parses animated fixture');
    TestAssert::same(2, $scanAnim['frames'], 'scan reports 2 frames');
    TestAssert::true($scanAnim['safe'], 'scan marks clean animated GIF safe');

    $GLOBALS['__test_imagick_can_animated_gif'] = false;
    list($canAnim, $msgAnim) = gd_can_post_process_image($animPath);
    TestAssert::false($canAnim, 'gd_can_post_process_image skips animated GIF without Imagick');
    TestAssert::contains($msgAnim, 'GIF', 'skip message mentions GIF');
    unset($GLOBALS['__test_imagick_can_animated_gif']);

    echo "=== reencode / post-process preserve frames (GD-only path) ===\n";
    $GLOBALS['__test_imagick_can_animated_gif'] = false;
    TestAssert::same(true, reencode_image($animPath), 'reencode_image skips animated GIF');
    TestAssert::same($hashBefore, md5_file($animPath), 'reencode_image leaves file unchanged');
    TestAssert::same($framesBefore, $frameCount($animPath), 'frame count unchanged after reencode skip');

    TestAssert::same(true, upload_post_process_image($animPath, false, true), 'upload_post_process_image succeeds');
    TestAssert::contains(upload_post_process_last_notice(), 'GIF', 'post-process notice for animated GIF');
    TestAssert::same($hashBefore, md5_file($animPath), 'post-process leaves animated file unchanged');
    TestAssert::same($framesBefore, $frameCount($animPath), 'frame count unchanged after post-process');

    echo "=== resize / cut / watermark preserve frames (GD-only path) ===\n";
    $resized = $tmpdir . DIRECTORY_SEPARATOR . 'anim_resized.gif';
    TestAssert::same(true, resize_img($animPath, $resized, 8, 8), 'resize_img copies animated GIF');
    TestAssert::same($hashBefore, md5_file($resized), 'resize output matches original bytes');
    TestAssert::same($framesBefore, $frameCount($resized), 'resize keeps frames');

    $cut = $tmpdir . DIRECTORY_SEPARATOR . 'anim_cut.gif';
    TestAssert::same(true, cut_img($animPath, $cut, 8, 8), 'cut_img copies animated GIF');
    TestAssert::same($hashBefore, md5_file($cut), 'cut output matches original bytes');
    TestAssert::same($framesBefore, $frameCount($cut), 'cut keeps frames');
    unset($GLOBALS['__test_imagick_can_animated_gif']);

    ConfigStub::set(array('watermark_open' => 1, 'watermark_text' => 'T', 'watermark_pic' => ''));
    unset($GLOBALS['_upload_post_process_notice']);
    $wmOut = $tmpdir . DIRECTORY_SEPARATOR . 'anim_wm.gif';
    TestAssert::same(true, watermark_img($animPath, $wmOut, 1), 'watermark_img skips animated GIF');
    TestAssert::same($hashBefore, md5_file($wmOut), 'watermark output matches original bytes');
    TestAssert::contains(upload_post_process_last_notice(), '水印', 'watermark skip sets notice');
    ConfigStub::reset();

    echo "=== multi-frame without GCE still animated ===\n";
    $noGceBytes = hex2bin(
        '47494638396101000100000000' .
        '2c00000000010001000002014c00' .
        '2c00000000010001000002014c00' .
        '3b'
    );
    $noGcePath = $tmpdir . DIRECTORY_SEPARATOR . 'anim_nogce.gif';
    file_put_contents($noGcePath, $noGceBytes);
    if (@getimagesize($noGcePath)) {
        TestAssert::true(is_animated_gif($noGcePath), 'two image descriptors without GCE still animated');
        TestAssert::true(gif_structure_is_safe($noGcePath), 'no-GCE animated GIF structure ok');
    } else {
        echo "SKIP: getimagesize rejects no-GCE multi-frame fixture\n";
    }

    echo "=== comment-embedded fake GCE does not count as animated ===\n";
    // 注释块数据里故意放入两段 \x21\xF9\x04（旧 preg 会误判；块遍历应只见 1 帧）
    $fakeGceBytes = hex2bin(
        '47494638396101000100000000' .
        '21fe08' . '21f9040021f90400' . '00' . // Comment containing two fake GCE signatures
        '2c00000000010001000002014c00' .
        '3b'
    );
    $fakeGcePath = $tmpdir . DIRECTORY_SEPARATOR . 'fake_gce.gif';
    file_put_contents($fakeGcePath, $fakeGceBytes);
    $fakeScan = gif_scan_bytes($fakeGceBytes);
    TestAssert::true(is_array($fakeScan), 'fake-GCE fixture parses');
    TestAssert::same(1, $fakeScan['frames'], 'comment bytes do not create image frames');
    TestAssert::same(0, $fakeScan['gce'], 'comment-embedded bytes are not real GCE blocks');
    if (@getimagesize($fakeGcePath)) {
        TestAssert::false(is_animated_gif($fakeGcePath), 'static GIF with fake GCE bytes is not animated');
        TestAssert::true(gif_structure_is_safe($fakeGcePath), 'fake-GCE static GIF structure ok');
        list($canFake, $msgFake) = gd_can_post_process_image($fakeGcePath);
        TestAssert::true($canFake, 'fake-GCE static GIF still eligible for GD post-process');
        TestAssert::same('', $msgFake, 'fake-GCE static GIF has no skip message');
    } else {
        echo "SKIP: getimagesize rejects fake-GCE fixture\n";
    }

    echo "=== static GIF still re-encodable ===\n";
    if (! function_exists('imagegif') || ! function_exists('imagecreatetruecolor')) {
        echo "SKIP: GD GIF unavailable for static fixture\n";
    } else {
        $staticPath = $tmpdir . DIRECTORY_SEPARATOR . 'static.gif';
        $img = imagecreatetruecolor(4, 4);
        $ok = imagegif($img, $staticPath);
        gd_free_image($img);
        if (! $ok || ! is_file($staticPath)) {
            echo "SKIP: cannot write static GIF\n";
        } else {
            TestAssert::false(is_animated_gif($staticPath), 'static GIF is not animated');
            TestAssert::true(gif_structure_is_safe($staticPath), 'static GIF structure ok');
            list($canStatic, $msgStatic) = gd_can_post_process_image($staticPath);
            TestAssert::true($canStatic, 'static GIF still eligible for GD post-process');
            TestAssert::same('', $msgStatic, 'static GIF has no skip message');
            TestAssert::same(true, reencode_image($staticPath), 'static GIF reencode succeeds');
            TestAssert::same(true, upload_post_process_image($staticPath, false, true), 'static GIF post-process succeeds');
            TestAssert::same('', upload_post_process_last_notice(), 'static GIF has no animated notice');
            @unlink($staticPath);
        }
    }

    echo "=== unsafe trailer payload rejected ===\n";
    $unsafePath = $tmpdir . DIRECTORY_SEPARATOR . 'unsafe.gif';
    file_put_contents($unsafePath, $animBytes . str_repeat('A', 64));
    TestAssert::true(is_animated_gif($unsafePath), 'payload-appended file still detected as animated');
    TestAssert::false(gif_structure_is_safe($unsafePath), 'gif_structure_is_safe rejects large trailer junk');
    TestAssert::same('GIF文件结构不合法！', reencode_image($unsafePath), 'reencode_image rejects unsafe animated GIF');
    TestAssert::same('GIF文件结构不合法！', upload_post_process_image($unsafePath, false, true), 'post-process rejects unsafe animated GIF');

    // 旧 strrpos 会被末尾 ";" 骗过；块边界 trailer 仍应判定不安全
    echo "=== GIFAR-style trailer bypass rejected ===\n";
    $gifarPath = $tmpdir . DIRECTORY_SEPARATOR . 'gifar.gif';
    $gifarBytes = $animBytes . str_repeat('A', 64) . ';';
    file_put_contents($gifarPath, $gifarBytes);
    $gifarScan = gif_scan_bytes($gifarBytes);
    TestAssert::true(is_array($gifarScan), 'GIFAR-style fixture still parses as GIF');
    TestAssert::false($gifarScan['safe'], 'block-boundary trailer rejects junk ending with semicolon');
    TestAssert::false(gif_structure_is_safe($gifarPath), 'gif_structure_is_safe rejects GIFAR-style payload');
    TestAssert::same('GIF文件结构不合法！', reencode_image($gifarPath), 'reencode_image rejects GIFAR-style animated GIF');
    TestAssert::same('GIF文件结构不合法！', upload_post_process_image($gifarPath, false, true), 'post-process rejects GIFAR-style animated GIF');

    echo "=== upload notice helpers ===\n";
    unset($GLOBALS['_upload_post_process_notice']);
    upload_append_post_process_notice('提示一');
    upload_append_post_process_notice('提示二');
    TestAssert::contains(upload_post_process_last_notice(), '提示一', 'append preserves first notice');
    TestAssert::contains(upload_post_process_last_notice(), '提示二', 'append adds second notice');
    $packed = upload_build_success_result(array('/a.gif', '/b.gif'));
    TestAssert::same('/a.gif', $packed[0], 'build result keeps first path at index 0');
    TestAssert::same('/b.gif', $packed[1], 'build result keeps second path at index 1');
    TestAssert::same('提示一；提示二', $packed['notice'], 'build result attaches notice key without breaking paths');
    unset($GLOBALS['_upload_post_process_notice']);
    $plain = upload_build_success_result(array('/only.jpg'));
    TestAssert::same(array('/only.jpg'), $plain, 'build result plain array when no notice');
    unset($GLOBALS['_upload_post_process_notice']);
    upload_append_post_process_notice(str_repeat('长', 300));
    upload_append_post_process_notice(str_repeat('文', 300));
    $truncated = upload_post_process_last_notice();
    TestAssert::contains($truncated, '长', 'append truncates instead of dropping when over limit');
    TestAssert::true((function_exists('mb_strlen') ? mb_strlen($truncated) : strlen($truncated)) <= 512, 'append result stays within limit');
    TestAssert::same(upload_truncate_notice(str_repeat('x', 600)), upload_truncate_notice(str_repeat('x', 600)), 'truncate helper is stable');

    @unlink($animPath);
    @unlink($resized);
    @unlink($cut);
    @unlink($wmOut);
    @unlink($noGcePath);
    @unlink($fakeGcePath);
    @unlink($unsafePath);
    @unlink($gifarPath);
    @rmdir($tmpdir);
}));
