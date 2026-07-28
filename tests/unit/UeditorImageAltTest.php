<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/extend/ueditor/ueditor.all.js insertimage alt
 * @covers core/extend/ueditor/dialogs/image/image.js getImageAltFromSrc
 *
 * 运行：
 *   php tests/unit/UeditorImageAltTest.php
 *   php tests/run.php --filter=UeditorImageAlt
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

function ueditor_image_alt_from_src(string $src): string
{
    if ($src === '') {
        return '';
    }
    $name = substr($src, strrpos($src, '/') + 1);
    $q = strpos($name, '?');
    if ($q !== false) {
        $name = substr($name, 0, $q);
    }
    $h = strpos($name, '#');
    if ($h !== false) {
        $name = substr($name, 0, $h);
    }
    $decoded = rawurldecode($name);
    return $decoded !== '' ? $decoded : $name;
}

function ueditor_unhtml(string $str): string
{
    return preg_replace_callback('/[&<>"\'](?:(amp|lt|ldquo|rdquo|quot|gt|#39|nbsp|#\d+);)?/', static function (array $m): string {
        if (!empty($m[1])) {
            return $m[0];
        }
        $map = [
            '<' => '&lt;',
            '&' => '&amp;',
            '"' => '&quot;',
            '“' => '&ldquo;',
            '”' => '&rdquo;',
            '>' => '&gt;',
            "'" => '&#39;',
        ];
        return $map[$m[0]] ?? $m[0];
    }, $str) ?? $str;
}

return TestAssert::runSuite(function () {
    $root = dirname(__DIR__, 2);
    $allJs = file_get_contents($root . '/core/extend/ueditor/ueditor.all.js');
    $imageJs = file_get_contents($root . '/core/extend/ueditor/dialogs/image/image.js');
    $minJs = file_get_contents($root . '/core/extend/ueditor/ueditor.all.min.js');

    echo "=== ueditor.all.js insertimage multi-image alt ===\n";

    TestAssert::contains($allJs, 'function sanitizeInsertImageItem(ci)', 'insertimage: sanitize helper present');
    TestAssert::contains($allJs, 'unhtmlForUrl:', 'utils: unhtmlForUrl present');
    TestAssert::contains($allJs, 'sanitizeInsertImageItem(ci);', 'insertimage: sanitize called per item');
    TestAssert::contains($allJs, '(ci.alt && ci.alt != "" ? \' alt="\' + ci.alt + \'"\' : "") +', 'insertimage: alt attribute in template');

    $multiStart = strpos($allJs, 'for (var i = 0; (ci = opt[i++]); )');
    TestAssert::true($multiStart !== false, 'insertimage: multi-image loop found');
    $multiChunk = substr($allJs, (int) $multiStart, 900);
    TestAssert::contains($multiChunk, 'ci.alt && ci.alt != ""', 'insertimage: multi-image branch includes alt');

    echo "=== ueditor.all.min.js insertimage multi-image alt ===\n";

    TestAssert::contains($minJs, 'g.alt&&""!=g.alt?\' alt="\'+g.alt+\'"\':""', 'min insertimage: multi-image alt present');

    echo "=== image.js online filename alt helper ===\n";

    TestAssert::contains($imageJs, 'function getImageAltFromSrc(src)', 'image.js: getImageAltFromSrc helper present');
    TestAssert::contains($imageJs, 'alt: getImageAltFromSrc(src)', 'image.js: online insert uses helper');

    echo "=== getImageAltFromSrc() behavior ===\n";

    TestAssert::same('photo.jpg', ueditor_image_alt_from_src('/upload/image/photo.jpg'), 'alt from src: plain filename');
    TestAssert::same('中文 图.jpg', ueditor_image_alt_from_src('/upload/image/%E4%B8%AD%E6%96%87%20%E5%9B%BE.jpg'), 'alt from src: decoded unicode filename');
    TestAssert::same('photo.jpg', ueditor_image_alt_from_src('/upload/image/photo.jpg?x=1'), 'alt from src: strips query');
    TestAssert::same('photo.jpg', ueditor_image_alt_from_src('/upload/image/photo.jpg#thumb'), 'alt from src: strips hash');

    echo "=== unhtml() alt escaping ===\n";

    TestAssert::same('中文 图&quot;&lt;&gt;&#39;.jpg', ueditor_unhtml("中文 图\"<>'.jpg"), 'unhtml: quotes and angle brackets escaped');
    TestAssert::notContains(ueditor_unhtml('evil" onerror=alert(1)'), '" onerror=', 'unhtml: breaks attribute breakout');
});
