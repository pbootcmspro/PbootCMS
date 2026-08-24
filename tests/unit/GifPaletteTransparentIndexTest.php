<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/file.php gd_prepare_canvas_transparency resize_img watermark_img
 *
 * 回归：GIF 透明色索引 >= imagecolorstotal() 时不得触发 PHP 8+ ValueError / PHP 7 Warning。
 */

if (! function_exists('gd_prepare_canvas_transparency')) {
    require dirname(__DIR__) . '/bootstrap_upload.php';
}

if (! class_exists('ConfigStub', false)) {
    require dirname(__DIR__) . '/support/ConfigStub.php';
}

exit(TestAssert::runSuite(function () {
    if (! function_exists('imagecreate')
        || ! function_exists('imagegif')
        || ! function_exists('imagecreatefromgif')
        || ! function_exists('imagecreatetruecolor')
        || ! function_exists('imagepng')) {
        echo "SKIP: GD GIF/PNG unavailable\n";
        return;
    }

    $tmpdir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp_gif_palette_' . mt_rand(10000, 99999);
    if (! mkdir($tmpdir) && ! is_dir($tmpdir)) {
        echo "SKIP: cannot create temp dir\n";
        return;
    }

    $cleanup = array();
    $register = function ($path) use (&$cleanup) {
        $cleanup[] = $path;
        return $path;
    };

    $sampleCanvasPixel = function ($canvas) {
        $idx = imagecolorat($canvas, 0, 0);
        return imagecolorsforindex($canvas, $idx);
    };

    $assertReadableGif = function ($path, $msg) {
        $info = @getimagesize($path);
        TestAssert::true(is_array($info) && (int) $info[2] === IMAGETYPE_GIF, $msg . ' getimagesize is GIF');
        $img = @imagecreatefromgif($path);
        TestAssert::true($img !== false, $msg . ' imagecreatefromgif succeeds');
        if ($img !== false) {
            gd_free_image($img);
        }
    };

    $runWithoutThrowable = function (callable $fn, $msg) {
        try {
            $fn();
            TestAssert::true(true, $msg . ' does not throw');
            return true;
        } catch (\Throwable $e) {
            TestAssert::true(false, $msg . ' threw ' . get_class($e) . ': ' . $e->getMessage());
            return false;
        }
    };

    // ---------- 合法透明 GIF：保持原色铺底 ----------
    echo "=== legal transparent GIF ===\n";
    $legalPath = $register($tmpdir . DIRECTORY_SEPARATOR . 'legal.gif');
    $legal = imagecreate(32, 32);
    $magenta = imagecolorallocate($legal, 255, 0, 255);
    $green = imagecolorallocate($legal, 0, 128, 0);
    imagefilledrectangle($legal, 0, 0, 31, 31, $magenta);
    imagefilledrectangle($legal, 8, 8, 23, 23, $green);
    imagecolortransparent($legal, $magenta);
    TestAssert::true(imagecolortransparent($legal) >= 0, 'legal fixture has transparent index');
    TestAssert::true(
        imagecolortransparent($legal) < imagecolorstotal($legal),
        'legal fixture transparent index is within palette'
    );
    imagegif($legal, $legalPath);

    $canvas = imagecreatetruecolor(8, 8);
    $ok = $runWithoutThrowable(function () use ($canvas, $legal) {
        gd_prepare_canvas_transparency($canvas, IMAGETYPE_GIF, $legal);
    }, 'legal gd_prepare_canvas_transparency');
    if ($ok) {
        $px = $sampleCanvasPixel($canvas);
        TestAssert::same(255, $px['red'], 'legal canvas keeps magenta red');
        TestAssert::same(0, $px['green'], 'legal canvas keeps magenta green');
        TestAssert::same(255, $px['blue'], 'legal canvas keeps magenta blue');
    }
    gd_free_image($canvas);
    gd_free_image($legal);

    $legalResized = $register($tmpdir . DIRECTORY_SEPARATOR . 'legal_resized.gif');
    $runWithoutThrowable(function () use ($legalPath, $legalResized) {
        TestAssert::same(true, resize_img($legalPath, $legalResized, 16, 16), 'legal resize_img succeeds');
    }, 'legal resize_img');
    if (is_file($legalResized)) {
        $assertReadableGif($legalResized, 'legal resize');
    }

    // ---------- 越界透明索引（trans >= total） ----------
    echo "=== out-of-range transparent index ===\n";

    // 内存：256 色 + 透明索引 255，再 deallocate 末色，尽量得到 total=255 / trans=255
    $oobSrc = imagecreate(16, 16);
    for ($i = 0; $i < 256; $i++) {
        imagecolorallocate($oobSrc, $i % 256, (int) ($i / 3) % 256, (255 - $i) % 256);
    }
    imagecolortransparent($oobSrc, 255);
    if (function_exists('imagecolordeallocate') && imagecolorstotal($oobSrc) === 256) {
        @imagecolordeallocate($oobSrc, 255);
    }
    $memTrans = imagecolortransparent($oobSrc);
    $memTotal = (int) imagecolorstotal($oobSrc);
    $memOob = ($memTrans >= 0 && $memTotal > 0 && $memTrans >= $memTotal);

    // 文件：写盘再读回；本机 GD 常把调色板压成 total=1 且保留 trans=255
    $oobPath = $register($tmpdir . DIRECTORY_SEPARATOR . 'oob.gif');
    $writer = imagecreate(16, 16);
    for ($i = 0; $i < 256; $i++) {
        imagecolorallocate($writer, $i % 256, (int) ($i / 3) % 256, (255 - $i) % 256);
    }
    imagecolortransparent($writer, 255);
    imagegif($writer, $oobPath);
    gd_free_image($writer);

    $oobReloaded = @imagecreatefromgif($oobPath);
    TestAssert::true($oobReloaded !== false, 'oob GIF reloads');
    $fileTrans = $oobReloaded !== false ? imagecolortransparent($oobReloaded) : -1;
    $fileTotal = $oobReloaded !== false ? (int) imagecolorstotal($oobReloaded) : 0;
    $fileOob = ($fileTrans >= 0 && $fileTotal > 0 && $fileTrans >= $fileTotal);

    TestAssert::true(
        $memOob || $fileOob,
        'fixture reproduces transparent index >= palette total'
            . ' (mem trans=' . $memTrans . ' total=' . $memTotal
            . ', file trans=' . $fileTrans . ' total=' . $fileTotal . ')'
    );

    if ($memOob) {
        echo "using in-memory OOB: trans={$memTrans} total={$memTotal}\n";
        $canvas = imagecreatetruecolor(8, 8);
        $ok = $runWithoutThrowable(function () use ($canvas, $oobSrc) {
            gd_prepare_canvas_transparency($canvas, IMAGETYPE_GIF, $oobSrc);
        }, 'OOB gd_prepare_canvas_transparency (memory)');
        if ($ok) {
            $px = $sampleCanvasPixel($canvas);
            TestAssert::same(255, $px['red'], 'OOB canvas falls back to white red');
            TestAssert::same(255, $px['green'], 'OOB canvas falls back to white green');
            TestAssert::same(255, $px['blue'], 'OOB canvas falls back to white blue');
        }
        gd_free_image($canvas);
    } elseif ($fileOob && $oobReloaded !== false) {
        echo "using reloaded OOB: trans={$fileTrans} total={$fileTotal}\n";
        $canvas = imagecreatetruecolor(8, 8);
        $ok = $runWithoutThrowable(function () use ($canvas, $oobReloaded) {
            gd_prepare_canvas_transparency($canvas, IMAGETYPE_GIF, $oobReloaded);
        }, 'OOB gd_prepare_canvas_transparency (reloaded)');
        if ($ok) {
            $px = $sampleCanvasPixel($canvas);
            TestAssert::same(255, $px['red'], 'OOB canvas falls back to white red');
            TestAssert::same(255, $px['green'], 'OOB canvas falls back to white green');
            TestAssert::same(255, $px['blue'], 'OOB canvas falls back to white blue');
        }
        gd_free_image($canvas);
    }

    if ($fileOob) {
        $oobResized = $register($tmpdir . DIRECTORY_SEPARATOR . 'oob_resized.gif');
        // 强制走重采样：源 16x16，目标上限更小
        $runWithoutThrowable(function () use ($oobPath, $oobResized) {
            TestAssert::same(true, resize_img($oobPath, $oobResized, 8, 8), 'OOB resize_img succeeds');
        }, 'OOB resize_img');
        if (is_file($oobResized)) {
            $assertReadableGif($oobResized, 'OOB resize');
        }
    }

    gd_free_image($oobSrc);
    if ($oobReloaded !== false) {
        gd_free_image($oobReloaded);
    }

    // ---------- 水印端到端 ----------
    echo "=== watermark end-to-end ===\n";
    $wmPath = $register($tmpdir . DIRECTORY_SEPARATOR . 'watermark.png');
    $wmImg = imagecreatetruecolor(10, 10);
    $blue = imagecolorallocate($wmImg, 40, 40, 200);
    imagefill($wmImg, 0, 0, $blue);
    imagepng($wmImg, $wmPath);
    gd_free_image($wmImg);
    $wmUrl = '/tests/' . basename($tmpdir) . '/watermark.png';

    ConfigStub::set(array('watermark_open' => 1));

    $legalWmOut = $register($tmpdir . DIRECTORY_SEPARATOR . 'legal_wm.gif');
    $runWithoutThrowable(function () use ($legalPath, $legalWmOut, $wmUrl) {
        TestAssert::same(true, watermark_img($legalPath, $legalWmOut, 1, $wmUrl), 'legal watermark_img succeeds');
    }, 'legal watermark_img');
    if (is_file($legalWmOut)) {
        $assertReadableGif($legalWmOut, 'legal watermark');
    }

    if ($fileOob) {
        $oobWmOut = $register($tmpdir . DIRECTORY_SEPARATOR . 'oob_wm.gif');
        $runWithoutThrowable(function () use ($oobPath, $oobWmOut, $wmUrl) {
            TestAssert::same(true, watermark_img($oobPath, $oobWmOut, 1, $wmUrl), 'OOB watermark_img succeeds');
        }, 'OOB watermark_img');
        if (is_file($oobWmOut)) {
            $assertReadableGif($oobWmOut, 'OOB watermark');
        }
    }

    ConfigStub::reset();

    foreach ($cleanup as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($tmpdir);
}));
