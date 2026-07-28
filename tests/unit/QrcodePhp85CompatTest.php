<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/extend/qrcode/phpqrcode.php QRimage::freeImage
 * @covers core/qrcode.php
 *
 * 覆盖 PHP 8.5 下 imagedestroy 废弃告警污染 PNG 输出的问题：
 * core/qrcode.php 不经 init.php，display_errors=On 时废弃文本会损坏图片并泄露路径。
 *
 * stdout 路径必须在独立进程中验证：同进程内先有 TestAssert 输出会导致
 * Header() 触发 headers already sent，污染或吞掉 PNG 缓冲。
 */

require dirname(__DIR__) . '/support/Assert.php';

if (! function_exists('imagecreatetruecolor') || ! function_exists('imagepng')) {
    echo "SKIP: GD PNG unavailable\n";
    exit(0);
}

exit(TestAssert::runSuite(function () {
    TestAssert::false(
        function_exists('gd_free_image'),
        'standalone entry must not require gd_free_image'
    );

    $php = PHP_BINARY ?: 'php';
    $script = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'qrcode_verify.php';
    $cmd = escapeshellarg($php)
        . ' -d display_errors=1 -d error_reporting=30719 '
        . escapeshellarg($script) . ' entry';
    $output = array();
    $code = 1;
    exec($cmd, $output, $code);

    TestAssert::same(0, $code, 'qrcode entry outputs clean PNG');
    TestAssert::contains(
        implode("\n", $output),
        'PNG magic: 89504e47 (OK)',
        'qrcode entry reports valid PNG magic'
    );
    TestAssert::contains(
        implode("\n", $output),
        'Deprecated text: NO (OK)',
        'qrcode entry reports no Deprecated text'
    );
}));
