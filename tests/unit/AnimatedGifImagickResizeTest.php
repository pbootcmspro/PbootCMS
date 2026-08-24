<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers imagick_can_animated_gif imagick_resize_animated_gif imagick_cut_animated_gif imagick_write_animated_gif imagick_guard_before_coalesce imagick_notice_from_create_error upload_build_success_result upload_append_post_process_notice resize_img cut_img gd_can_post_process_image watermark_img upload_post_process_image upload_append_post_process_notice
 */

if (! function_exists('is_animated_gif')) {
    require dirname(__DIR__) . '/bootstrap_upload.php';
}

if (! class_exists('ConfigStub', false)) {
    require dirname(__DIR__) . '/support/ConfigStub.php';
}

exit(TestAssert::runSuite(function () {
    $tmpdir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot_imgk_gif_' . mt_rand(10000, 99999);
    if (! mkdir($tmpdir) && ! is_dir($tmpdir)) {
        echo "SKIP: cannot create temp dir\n";
        return;
    }
    $cleanup = [];

    // 双帧须颜色不同：缩到极小尺寸时仍应保持两帧（optimizeImageLayers 不去重）
    $animPath = $tmpdir . DIRECTORY_SEPARATOR . 'anim.gif';
    $info = false;
    $fixtureFromImagick = false;
    if (imagick_is_usable() && class_exists('Imagick', false)) {
        $anim = new \Imagick();
        foreach (array('black', 'white') as $color) {
            $frame = new \Imagick();
            $frame->newImage(10, 10, new \ImagickPixel($color));
            $frame->setImageFormat('gif');
            $frame->setImageDelay(10);
            $anim->addImage($frame);
            $frame->clear();
            $frame->destroy();
        }
        $anim->setImageIterations(0);
        $anim->writeImages($animPath, true);
        $anim->clear();
        $anim->destroy();
        $info = @getimagesize($animPath);
        $fixtureFromImagick = is_array($info) && is_animated_gif($animPath);
    }
    if (! $info || (int) $info[2] !== IMAGETYPE_GIF || ! is_animated_gif($animPath)) {
        // 无 Imagick 时回退 hex（仅覆盖 GD copy 路径；两帧内容相同不影响 copy 断言）
        $animBytes = hex2bin(
            '474946383961' .
            '01000100' . '00' . '00' . '00' .
            '21ff0b4e45545343415045322e300301000000' .
            '21f904000a000000' .
            '2c00000000010001000002014c00' .
            '21f904000a000000' .
            '2c00000000010001000002014c00' .
            '3b'
        );
        file_put_contents($animPath, $animBytes);
        $info = @getimagesize($animPath);
        if (! $info || ! is_animated_gif($animPath)) {
            echo "SKIP: cannot build animated GIF fixture\n";
            @unlink($animPath);
            @rmdir($tmpdir);
            return;
        }
    }
    $cleanup[] = $animPath;
    $origW = (int) $info[0];
    $origH = (int) $info[1];
    $framesBefore = gif_scan_file($animPath)['frames'];
    $hashBefore = md5_file($animPath);

    // ---------------------------------------------------------------
    echo "=== imagick_can_animated_gif capability probe ===\n";

    // 强制禁用 → 应返回 false
    $GLOBALS['__test_imagick_can_animated_gif'] = false;
    TestAssert::false(imagick_can_animated_gif(), 'forced disabled returns false');

    // 强制启用 → 应返回 true
    $GLOBALS['__test_imagick_can_animated_gif'] = true;
    TestAssert::true(imagick_can_animated_gif(), 'forced enabled returns true');

    // ---------------------------------------------------------------
    echo "=== resize_img with Imagick disabled (GD fallback copy) ===\n";

    $GLOBALS['__test_imagick_can_animated_gif'] = false;
    $outGd = $tmpdir . DIRECTORY_SEPARATOR . 'gd_fallback.gif';
    $cleanup[] = $outGd;
    TestAssert::same(true, resize_img($animPath, $outGd, 1, 1), 'resize_img succeeds with GD fallback');
    TestAssert::same($hashBefore, md5_file($outGd), 'GD fallback preserves original bytes');

    $scanGd = gif_scan_file($outGd);
    TestAssert::true(is_array($scanGd), 'GD fallback output is valid GIF');
    TestAssert::same($framesBefore, $scanGd['frames'], 'GD fallback preserves frame count');

    // ---------------------------------------------------------------
    echo "=== resize_img with Imagick enabled ===\n";

    if (! imagick_is_usable()) {
        echo "SKIP: Imagick extension required for animated GIF resize tests\n";
    } else {
        $GLOBALS['__test_imagick_can_animated_gif'] = true;
        $outIm = $tmpdir . DIRECTORY_SEPARATOR . 'im_resize.gif';
        $cleanup[] = $outIm;
        $result = resize_img($animPath, $outIm, 1, 1);
        TestAssert::same(true, $result, 'resize_img succeeds');
        TestAssert::true(is_file($outIm), 'output file created');

        $outInfo = @getimagesize($outIm);
        TestAssert::true(is_array($outInfo), 'output is valid image');
        TestAssert::same(IMAGETYPE_GIF, (int) $outInfo[2], 'output is GIF format');
        TestAssert::true((int) $outInfo[0] <= 1, 'Imagick resize respects max width');
        TestAssert::true((int) $outInfo[1] <= 1, 'Imagick resize respects max height');

        $scanOut = gif_scan_file($outIm);
        TestAssert::true(is_array($scanOut), 'Imagick output is parseable GIF');
        TestAssert::same($framesBefore, $scanOut['frames'], 'Imagick resize preserves frame count');
        TestAssert::true(is_animated_gif($outIm), 'Imagick output is still animated');

        if ($fixtureFromImagick && class_exists('Imagick', false)) {
            $srcMeta = new \Imagick($animPath);
            $outMeta = new \Imagick($outIm);
            TestAssert::same((int) $srcMeta->getNumberImages(), (int) $outMeta->getNumberImages(), 'Imagick resize preserves frame count (metadata)');
            TestAssert::same((int) $srcMeta->getImageIterations(), (int) $outMeta->getImageIterations(), 'Imagick resize preserves loop iterations');
            $srcMeta->setFirstIterator();
            $outMeta->setFirstIterator();
            do {
                TestAssert::same((int) $srcMeta->getImageDelay(), (int) $outMeta->getImageDelay(), 'Imagick resize preserves frame delay');
            } while ($srcMeta->nextImage() && $outMeta->nextImage());
            $srcMeta->clear();
            $srcMeta->destroy();
            $outMeta->clear();
            $outMeta->destroy();
        }
    }

    // ---------------------------------------------------------------
    echo "=== resize preserves duplicate consecutive frames ===\n";

    if (! imagick_is_usable() || ! class_exists('Imagick', false)) {
        echo "SKIP: Imagick required for duplicate-frame resize test\n";
    } else {
        $dupPath = $tmpdir . DIRECTORY_SEPARATOR . 'dup_frames.gif';
        $cleanup[] = $dupPath;
        $dupAnim = new \Imagick();
        foreach (array('black', 'white', 'white') as $color) {
            $frame = new \Imagick();
            $frame->newImage(10, 10, new \ImagickPixel($color));
            $frame->setImageFormat('gif');
            $frame->setImageDelay(10);
            $dupAnim->addImage($frame);
            $frame->clear();
            $frame->destroy();
        }
        $dupAnim->setImageIterations(3);
        $dupAnim->writeImages($dupPath, true);
        $dupAnim->clear();
        $dupAnim->destroy();

        TestAssert::true(is_animated_gif($dupPath), 'duplicate-frame fixture is animated');
        $srcMeta = new \Imagick($dupPath);
        $srcFrames = (int) $srcMeta->getNumberImages();
        $srcIter = (int) $srcMeta->getImageIterations();
        $srcDelaySum = 0;
        $srcMeta->setFirstIterator();
        do {
            $srcDelaySum += (int) $srcMeta->getImageDelay();
        } while ($srcMeta->nextImage());
        $srcMeta->clear();
        $srcMeta->destroy();
        TestAssert::same(3, $srcFrames, 'duplicate-frame fixture has 3 frames');
        TestAssert::same(30, $srcDelaySum, 'duplicate-frame fixture total delay is 30cs');

        $GLOBALS['__test_imagick_can_animated_gif'] = true;
        $outDup = $tmpdir . DIRECTORY_SEPARATOR . 'dup_resized.gif';
        $cleanup[] = $outDup;
        TestAssert::same(true, resize_img($dupPath, $outDup, 5, 5), 'resize duplicate-frame GIF succeeds');

        $outMeta = new \Imagick($outDup);
        TestAssert::same(3, (int) $outMeta->getNumberImages(), 'resize preserves 3 frames after re-read');
        TestAssert::same($srcIter, (int) $outMeta->getImageIterations(), 'resize preserves loop iterations after re-read');
        $outDelaySum = 0;
        $outMeta->setFirstIterator();
        do {
            $outDelaySum += (int) $outMeta->getImageDelay();
        } while ($outMeta->nextImage());
        $outMeta->clear();
        $outMeta->destroy();
        TestAssert::same(30, $outDelaySum, 'resize preserves total delay after re-read');
        TestAssert::same(3, gif_scan_file($outDup)['frames'], 'resize preserves frame count in GIF scan');
        TestAssert::true(is_animated_gif($outDup), 'resized duplicate-frame output still animated');
    }

    // ---------------------------------------------------------------
    echo "=== resize uses logical canvas not first-frame geometry ===\n";

    if (! imagick_is_usable() || ! class_exists('Imagick', false)) {
        echo "SKIP: Imagick required for logical-canvas resize test\n";
    } else {
        $canvasPath = $tmpdir . DIRECTORY_SEPARATOR . 'small_frame_large_canvas.gif';
        $cleanup[] = $canvasPath;
        $canvasAnim = new \Imagick();
        foreach (array('black', 'white') as $color) {
            $patch = new \Imagick();
            $patch->newImage(10, 10, new \ImagickPixel($color));
            $patch->setImageFormat('gif');
            $patch->setImageDelay(10);
            // 逻辑画布 100×100，帧像素仅 10×10（优化 GIF 常见布局）
            $patch->setImagePage(100, 100, 0, 0);
            $canvasAnim->addImage($patch);
            $patch->clear();
            $patch->destroy();
        }
        $canvasAnim->setImageIterations(0);
        $canvasAnim->writeImages($canvasPath, true);
        $canvasAnim->clear();
        $canvasAnim->destroy();

        $canvasInfo = @getimagesize($canvasPath);
        TestAssert::true(is_array($canvasInfo), 'logical-canvas fixture is readable');
        TestAssert::same(100, (int) $canvasInfo[0], 'logical-canvas fixture width is 100');
        TestAssert::same(100, (int) $canvasInfo[1], 'logical-canvas fixture height is 100');
        TestAssert::true(is_animated_gif($canvasPath), 'logical-canvas fixture is animated');

        $probe = new \Imagick($canvasPath);
        $probe->setIteratorIndex(0);
        $geo = $probe->getImageGeometry();
        $probe->clear();
        $probe->destroy();
        TestAssert::true((int) $geo['width'] < 100 || (int) $geo['height'] < 100,
            'first frame geometry is smaller than logical canvas');

        $GLOBALS['__test_imagick_can_animated_gif'] = true;
        $outCanvas = $tmpdir . DIRECTORY_SEPARATOR . 'canvas_resized.gif';
        $cleanup[] = $outCanvas;
        TestAssert::same(true, resize_img($canvasPath, $outCanvas, 50, 50), 'resize logical-canvas GIF succeeds');

        $outCanvasInfo = @getimagesize($outCanvas);
        TestAssert::true(is_array($outCanvasInfo), 'resized logical-canvas output is readable');
        TestAssert::same(50, (int) $outCanvasInfo[0], 'resize scales by logical canvas width');
        TestAssert::same(50, (int) $outCanvasInfo[1], 'resize scales by logical canvas height');
        TestAssert::true(is_animated_gif($outCanvas), 'resized logical-canvas output still animated');
        TestAssert::same(2, gif_scan_file($outCanvas)['frames'], 'resize preserves frame count for logical-canvas GIF');
    }

    // ---------------------------------------------------------------
    echo "=== in-place resize write failure preserves source ===\n";

    if (! imagick_is_usable()) {
        echo "SKIP: Imagick required for in-place write failure test\n";
    } else {
        $inplaceWritePath = $tmpdir . DIRECTORY_SEPARATOR . 'inplace_write_fail.gif';
        copy($animPath, $inplaceWritePath);
        $cleanup[] = $inplaceWritePath;
        $hashInplace = md5_file($inplaceWritePath);

        $GLOBALS['__test_imagick_can_animated_gif'] = true;
        $GLOBALS['__test_imagick_gif_force_write_fail'] = true;
        $writeFail = resize_img($inplaceWritePath, $inplaceWritePath, 1, 1);
        unset($GLOBALS['__test_imagick_gif_force_write_fail']);

        TestAssert::true(is_string($writeFail), 'in-place write failure returns error string');
        TestAssert::contains($writeFail, '写出失败', 'write failure message mentions write error');
        TestAssert::same($hashInplace, md5_file($inplaceWritePath), 'in-place write failure preserves source bytes');
        TestAssert::true(is_animated_gif($inplaceWritePath), 'in-place write failure source still animated');
        TestAssert::same($framesBefore, gif_scan_file($inplaceWritePath)['frames'], 'in-place write failure preserves frame count');
    }

    // ---------------------------------------------------------------
    echo "=== resize_img no-op when already small enough ===\n";

    $GLOBALS['__test_imagick_can_animated_gif'] = true;
    $outNoop = $tmpdir . DIRECTORY_SEPARATOR . 'im_noop.gif';
    $cleanup[] = $outNoop;
    $result = resize_img($animPath, $outNoop, 99999, 99999);
    TestAssert::same(true, $result, 'resize noop succeeds');
    // 不管走 Imagick 还是 copy，帧数不变
    $scanNoop = gif_scan_file($outNoop);
    TestAssert::same($framesBefore, $scanNoop['frames'], 'noop preserves frame count');

    // ---------------------------------------------------------------
    echo "=== cut_img animated GIF ===\n";

    $GLOBALS['__test_imagick_can_animated_gif'] = false;
    $outCutGd = $tmpdir . DIRECTORY_SEPARATOR . 'gd_cut.gif';
    $cleanup[] = $outCutGd;
    $result = cut_img($animPath, $outCutGd, 1, 1);
    TestAssert::same(true, $result, 'cut_img succeeds with GD fallback');
    TestAssert::true(is_file($outCutGd), 'GD cut output file created');
    TestAssert::same($hashBefore, md5_file($outCutGd), 'cut_img copies animated GIF when Imagick unavailable');
    TestAssert::same($framesBefore, gif_scan_file($outCutGd)['frames'], 'GD cut preserves frame count');

    if (! imagick_is_usable()) {
        echo "SKIP: Imagick required for animated GIF crop test\n";
    } else {
        $GLOBALS['__test_imagick_can_animated_gif'] = true;
        $outCutIm = $tmpdir . DIRECTORY_SEPARATOR . 'im_cut.gif';
        $cleanup[] = $outCutIm;
        $result = cut_img($animPath, $outCutIm, 5, 5);
        TestAssert::same(true, $result, 'cut_img succeeds with Imagick');
        TestAssert::true(is_file($outCutIm), 'Imagick cut output file created');
        $cutInfo = @getimagesize($outCutIm);
        TestAssert::true(is_array($cutInfo), 'Imagick cut output is valid image');
        TestAssert::same(5, (int) $cutInfo[0], 'Imagick cut respects target width');
        TestAssert::same(5, (int) $cutInfo[1], 'Imagick cut respects target height');
        TestAssert::same($framesBefore, gif_scan_file($outCutIm)['frames'], 'Imagick cut preserves frame count');
        TestAssert::true(is_animated_gif($outCutIm), 'Imagick cut output still animated');
    }

    // ---------------------------------------------------------------
    echo "=== gd_can_post_process_image with Imagick ===\n";

    $GLOBALS['__test_imagick_can_animated_gif'] = true;
    list($canIm, $msgIm) = gd_can_post_process_image($animPath);
    TestAssert::true($canIm, 'animated GIF eligible when Imagick available');
    TestAssert::same('', $msgIm, 'no skip message when Imagick available');

    $GLOBALS['__test_imagick_can_animated_gif'] = false;
    list($canGd, $msgGd) = gd_can_post_process_image($animPath);
    TestAssert::false($canGd, 'animated GIF skipped when Imagick unavailable');
    TestAssert::contains($msgGd, 'GIF', 'skip message mentions GIF');

    // ---------------------------------------------------------------
    echo "=== guard rejects excessive frame count ===\n";

    $GLOBALS['__test_imagick_max_gif_frames'] = 1;
    $GLOBALS['__test_imagick_can_animated_gif'] = true;
    if (imagick_is_usable()) {
        list($im, $imErr) = imagick_create($animPath, 'GIF');
        if ($im !== null) {
            $guard = imagick_guard_before_coalesce($im);
            TestAssert::true(is_string($guard), 'guard rejects when frames > limit');
            TestAssert::contains($guard, '帧数', 'guard message mentions frame count');
            $im->clear();
            $im->destroy();
        } else {
            echo "SKIP: imagick_create failed: $imErr\n";
        }
    } else {
        echo "SKIP: real Imagick unavailable for guard test\n";
    }

    // ---------------------------------------------------------------
    echo "=== guard over-limit degrades via resize_img (copy, not error) ===\n";

    // 阈值压到 1：多帧动图触发 guard；须降级 copy 成功，不得把错误字符串上抛
    unset($GLOBALS['_upload_post_process_notice']);
    $outGuard = $tmpdir . DIRECTORY_SEPARATOR . 'guard_degrade.gif';
    $cleanup[] = $outGuard;
    $guardResize = resize_img($animPath, $outGuard, 1, 1);
    TestAssert::same(true, $guardResize, 'resize_img returns true when guard over-limit');
    TestAssert::true(is_file($outGuard), 'guard-degrade output exists');
    $scanGuard = gif_scan_file($outGuard);
    TestAssert::true(is_array($scanGuard), 'guard-degrade output is valid GIF');
    TestAssert::same($framesBefore, $scanGuard['frames'], 'guard-degrade preserves frame count');
    TestAssert::true(is_animated_gif($outGuard), 'guard-degrade output still animated');
    TestAssert::same($hashBefore, md5_file($outGuard), 'guard-degrade copies original bytes');

    // 原地缩放（上传路径 src===out）：同样不得失败删文件
    $inplacePath = $tmpdir . DIRECTORY_SEPARATOR . 'guard_inplace.gif';
    copy($animPath, $inplacePath);
    $cleanup[] = $inplacePath;
    unset($GLOBALS['_upload_post_process_notice']);
    TestAssert::same(true, resize_img($inplacePath, $inplacePath, 1, 1), 'in-place resize_img succeeds on guard over-limit');
    TestAssert::true(is_file($inplacePath), 'in-place file not deleted');
    TestAssert::same($framesBefore, gif_scan_file($inplacePath)['frames'], 'in-place preserves frames');
    TestAssert::same($hashBefore, md5_file($inplacePath), 'in-place keeps original bytes');

    if (imagick_is_usable()) {
        $notice = upload_post_process_last_notice();
        TestAssert::contains($notice, '保留原图', 'notice set when Imagick guard degrades');
        TestAssert::contains($notice, '帧数', 'notice includes guard reason');
    }

    unset($GLOBALS['__test_imagick_max_gif_frames']);
    unset($GLOBALS['_upload_post_process_notice']);

    // ---------------------------------------------------------------
    echo "=== watermark skip notice (Imagick host) ===\n";

    ConfigStub::set(array('watermark_open' => 1, 'watermark_text' => 'T', 'watermark_pic' => ''));
    $GLOBALS['__test_imagick_can_animated_gif'] = true;
    unset($GLOBALS['_upload_post_process_notice']);
    $wmOut = $tmpdir . DIRECTORY_SEPARATOR . 'im_wm.gif';
    $cleanup[] = $wmOut;
    TestAssert::same(true, watermark_img($animPath, $wmOut, 1), 'watermark_img skips animated GIF');
    TestAssert::contains(upload_post_process_last_notice(), '水印', 'watermark skip sets notice');

    if (imagick_is_usable()) {
        unset($GLOBALS['_upload_post_process_notice']);
        TestAssert::same(true, upload_post_process_image($animPath, true, true), 'post-process with Imagick succeeds');
        TestAssert::contains(upload_post_process_last_notice(), '水印', 'post-process with Imagick sets watermark skip notice');
    } else {
        echo "SKIP: Imagick required for post-process watermark notice test\n";
    }
    ConfigStub::reset();
    unset($GLOBALS['_upload_post_process_notice']);

    // ---------------------------------------------------------------
    // cleanup
    unset($GLOBALS['__test_imagick_can_animated_gif']);
    foreach ($cleanup as $f) {
        @unlink($f);
    }
    @rmdir($tmpdir);
}));
