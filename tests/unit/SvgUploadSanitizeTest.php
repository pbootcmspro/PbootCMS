<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/file.php sanitize_svg_string sanitize_uploaded_svg svgz_decompress_limited
 */

if (! function_exists('sanitize_svg_string')) {
    require dirname(__DIR__) . '/bootstrap_upload.php';
}

return TestAssert::runSuite(function () {
    $tmpdir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot_svg_test_' . mt_rand(10000, 99999);
    if (! mkdir($tmpdir) && ! is_dir($tmpdir)) {
        echo "SKIP: cannot create temp dir\n";
        return;
    }

    echo "=== sanitize strips script / events / external href ===\n";

    $evil = '<?xml version="1.0"?>'
        . '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
        . '<script>alert(1)</script>'
        . '<circle r="10" onclick="alert(2)"/>'
        . '<image xlink:href="https://evil.example/x.png"/>'
        . '<a href="javascript:alert(3)"><text>x</text></a>'
        . '<use href="https://evil.example/u.svg#i"/>'
        . '<rect width="1" height="1"/>'
        . '</svg>';

    list($clean, $ok) = sanitize_svg_string($evil);
    TestAssert::true($ok === true, 'sanitize_svg_string succeeds');
    TestAssert::true(is_string($clean), 'clean output is string');
    TestAssert::notContains($clean, '<script', 'script removed');
    TestAssert::notContains($clean, 'onclick', 'event attr removed');
    TestAssert::notContains($clean, 'javascript:', 'javascript href removed');
    TestAssert::notContains($clean, 'https://evil.example', 'external refs removed');
    TestAssert::contains($clean, '<rect', 'safe shape kept');

    echo "=== reject ENTITY / DOCTYPE escape ===\n";

    list($bad, $badOk) = sanitize_svg_string('<!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&xxe;</svg>');
    TestAssert::false($badOk === true, 'DOCTYPE+ENTITY rejected');
    TestAssert::contains((string) $bad, '文档类型', 'DOCTYPE reject message');

    echo "=== sanitize_uploaded_svg writes clean file ===\n";

    $svgPath = $tmpdir . DIRECTORY_SEPARATOR . 'evil.svg';
    file_put_contents($svgPath, $evil);
    TestAssert::same(true, sanitize_uploaded_svg($svgPath), 'sanitize_uploaded_svg ok');
    $saved = file_get_contents($svgPath);
    TestAssert::notContains($saved, '<script', 'saved file has no script');
    TestAssert::contains($saved, '<rect', 'saved file keeps rect');

    echo "=== SVGZ decompress limits ===\n";

    $svgzPath = $tmpdir . DIRECTORY_SEPARATOR . 'ok.svgz';
    $plain = '<svg xmlns="http://www.w3.org/2000/svg"><circle r="5"/></svg>';
    file_put_contents($svgzPath, gzencode($plain, 9));
    list($decoded, $decOk) = svgz_decompress_limited($svgzPath);
    TestAssert::true($decOk === true, 'svgz decompress ok');
    TestAssert::contains($decoded, '<circle', 'svgz payload decoded');

    TestAssert::same(true, sanitize_uploaded_svg($svgzPath), 'sanitize svgz ok');

    // 压缩比炸弹：极小 gzip 膨胀出超大（用小 max_bytes 模拟限制）
    $bombPath = $tmpdir . DIRECTORY_SEPARATOR . 'bomb.svgz';
    $big = str_repeat('A', 50000);
    file_put_contents($bombPath, gzencode($big, 9));
    list($bombOut, $bombOk) = svgz_decompress_limited($bombPath, 10000, 10);
    TestAssert::false($bombOk === true, 'svgz bomb rejected by size/ratio');

    echo "=== SMIL set / style removed ===\n";

    $smil = '<svg xmlns="http://www.w3.org/2000/svg">'
        . '<circle r="40"><set attributeName="onclick" to="alert(1)" begin="0s"/></circle>'
        . '<style>@import url(https://evil.example/x.css);</style>'
        . '<rect width="1" height="1"/>'
        . '</svg>';
    list($smilClean, $smilOk) = sanitize_svg_string($smil);
    TestAssert::true($smilOk === true, 'smil svg sanitize succeeds');
    TestAssert::notContains($smilClean, '<set', 'set element removed');
    TestAssert::notContains($smilClean, '<style', 'style element removed');
    TestAssert::contains($smilClean, '<circle', 'circle kept after smil removal');

    echo "=== paint attrs url() external refs stripped ===\n";

    $paint = '<svg xmlns="http://www.w3.org/2000/svg">'
        . '<defs><radialGradient id="localGrad"/></defs>'
        . '<rect fill="url(https://evil.example/p.svg#x)" '
        . 'filter="url(https://evil.example/f.svg#y)" '
        . 'clip-path="url(https://evil.example/c.svg#z)" '
        . 'mask="url(//evil.example/m.svg#m)" '
        . 'marker-start="url(https://evil.example/mk.svg#s)" '
        . 'width="10" height="10"/>'
        . '<circle fill="url(#localGrad)" r="5"/>'
        . '<path stroke="url(\'https://evil.example/s.svg#a\')" d="M0 0"/>'
        . '<g style="fill:url(https://evil.example/g.svg#g)"/>'
        . '</svg>';
    list($paintClean, $paintOk) = sanitize_svg_string($paint);
    TestAssert::true($paintOk === true, 'paint url sanitize succeeds');
    TestAssert::notContains($paintClean, 'evil.example', 'paint external urls removed');
    TestAssert::notContains($paintClean, 'url(https:', 'no https url() left');
    TestAssert::notContains($paintClean, 'url(//', 'no protocol-relative url() left');
    TestAssert::contains($paintClean, 'url(#localGrad)', 'same-doc url(#id) kept');
    TestAssert::contains($paintClean, '<rect', 'rect element kept after attr strip');
    TestAssert::contains($paintClean, '<circle', 'circle with safe fill kept');

    echo "=== style mixed safe/unsafe url() drops whole style ===\n";

    $mixedStyle = '<svg xmlns="http://www.w3.org/2000/svg">'
        . '<rect style="fill:url(#ok); stroke:url(https://evil.example/x)" width="1" height="1"/>'
        . '</svg>';
    list($mixedClean, $mixedOk) = sanitize_svg_string($mixedStyle);
    TestAssert::true($mixedOk === true, 'mixed style sanitize succeeds');
    TestAssert::notContains($mixedClean, 'evil.example', 'mixed style external removed');
    TestAssert::notContains($mixedClean, 'style=', 'unsafe style attr removed entirely');

    @unlink($svgPath);
    @unlink($svgzPath);
    @unlink($bombPath);
    @rmdir($tmpdir);
});
