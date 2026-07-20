<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/file.php watermark_img
 */

if (! function_exists('watermark_img')) {
    require dirname(__DIR__) . '/bootstrap_upload.php';
}

exit(TestAssert::runSuite(function () {
    if (! function_exists('imagecreatetruecolor') || ! function_exists('imagepng')) {
        echo "SKIP: GD PNG unavailable\n";
        return;
    }
    error_reporting(error_reporting() & ~E_DEPRECATED);

    $tmpdir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp_watermark_' . mt_rand(10000, 99999);
    if (! mkdir($tmpdir) && ! is_dir($tmpdir)) {
        echo "SKIP: cannot create temp dir\n";
        return;
    }

    $destroy = function ($image) {
        if (PHP_VERSION_ID < 80000) {
            imagedestroy($image);
        }
    };
    $createSource = function (string $path, string $format) use ($destroy): bool {
        $image = imagecreatetruecolor(80, 50);
        $color = imagecolorallocate($image, 200, 40, 40);
        imagefill($image, 0, 0, $color);
        if ($format === 'png') {
            $result = imagepng($image, $path);
        } elseif ($format === 'webp') {
            $result = imagewebp($image, $path, 100);
        } else {
            $result = imagejpeg($image, $path, 100);
        }
        $destroy($image);
        return $result;
    };
    $assertBlendedPixel = function (string $path, string $format) use ($destroy) {
        if ($format === 'png') {
            $image = imagecreatefrompng($path);
        } elseif ($format === 'webp') {
            $image = imagecreatefromwebp($path);
        } else {
            $image = imagecreatefromjpeg($path);
        }
        $rgba = imagecolorsforindex($image, imagecolorat($image, 19, 9));
        $destroy($image);

        TestAssert::true($rgba['red'] > 70, strtoupper($format) . ' watermark keeps base-image red channel');
        TestAssert::true($rgba['blue'] > 70, strtoupper($format) . ' watermark blends blue channel');
    };

    $watermark = $tmpdir . DIRECTORY_SEPARATOR . 'watermark.png';
    $watermarkImage = imagecreatetruecolor(10, 10);
    imagealphablending($watermarkImage, false);
    imagesavealpha($watermarkImage, true);
    $blue = imagecolorallocatealpha($watermarkImage, 40, 40, 200, 64);
    imagefill($watermarkImage, 0, 0, $blue);
    imagepng($watermarkImage, $watermark);
    $destroy($watermarkImage);

    $config = \core\basic\Config::get();
    $config['watermark_open'] = 1;
    $property = new ReflectionProperty('core\\basic\\Config', 'configs');
    if (PHP_VERSION_ID < 80100) {
        $property->setAccessible(true);
    }
    $property->setValue(null, $config);
    $watermarkPath = '/tests/' . basename($tmpdir) . '/watermark.png';

    $formats = array('png');
    if (gd_supports_webp()) {
        $formats[] = 'webp';
    } else {
        echo "SKIP: GD WebP unavailable\n";
    }
    if (gd_supports_jpeg()) {
        $formats[] = 'jpeg';
    } else {
        echo "SKIP: GD JPEG unavailable\n";
    }

    foreach ($formats as $format) {
        $source = $tmpdir . DIRECTORY_SEPARATOR . 'source.' . ($format === 'jpeg' ? 'jpg' : $format);
        $output = $tmpdir . DIRECTORY_SEPARATOR . 'output.' . ($format === 'jpeg' ? 'jpg' : $format);
        TestAssert::true($createSource($source, $format), strtoupper($format) . ' source fixture created');
        TestAssert::same(true, watermark_img($source, $output, 1, $watermarkPath), strtoupper($format) . ' watermark succeeds');
        $assertBlendedPixel($output, $format);
        @unlink($source);
        @unlink($output);
    }

    @unlink($watermark);
    @rmdir($tmpdir);
}));
