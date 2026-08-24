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

    list($unknownDt, $unknownDtOk) = sanitize_svg_string('<!DOCTYPE html><svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>');
    TestAssert::false($unknownDtOk === true, 'unknown DOCTYPE rejected');

    echo "=== standard W3C SVG PUBLIC DOCTYPE stripped ===\n";

    $w3cDoctype = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'
        . '<svg xmlns="http://www.w3.org/2000/svg"><path fill="#92c2d2" d="M0 0h1"/></svg>';
    list($w3cClean, $w3cOk) = sanitize_svg_string($w3cDoctype);
    TestAssert::true($w3cOk === true, 'w3c doctype svg sanitize succeeds');
    TestAssert::notContains($w3cClean, '<!DOCTYPE', 'w3c doctype removed from output');
    TestAssert::contains($w3cClean, '#92c2d2', 'w3c doctype svg keeps fill color');

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

    echo "=== SMIL set stripped; style text preserved as-is ===\n";

    $smil = '<svg xmlns="http://www.w3.org/2000/svg">'
        . '<circle r="40"><set attributeName="onclick" to="alert(1)" begin="0s"/></circle>'
        . '<style>@import url(https://evil.example/x.css); .cls-1{fill:#f60}</style>'
        . '<rect width="1" height="1"/>'
        . '</svg>';
    list($smilClean, $smilOk) = sanitize_svg_string($smil);
    TestAssert::true($smilOk === true, 'smil svg sanitize succeeds');
    TestAssert::notContains($smilClean, '<set', 'set element removed');
    TestAssert::contains($smilClean, '<style', 'style element kept (no CSS rewrite)');
    TestAssert::contains($smilClean, '#f60', 'style fill color kept');
    TestAssert::contains($smilClean, '<circle', 'circle kept after smil removal');

    echo "=== safe class fill style kept ===\n";

    $logoStyle = '<svg xmlns="http://www.w3.org/2000/svg" width="136" height="72" viewBox="0 0 381.7 112.7">'
        . '<defs><style>.cls-1 { fill: #f60; }</style></defs>'
        . '<path class="cls-1" d="M56.35,0C25.23,0,0,25.23,0,56.35"/>'
        . '<path d="M10 10h20"/>'
        . '</svg>';
    list($logoClean, $logoOk) = sanitize_svg_string($logoStyle);
    TestAssert::true($logoOk === true, 'safe style sanitize succeeds');
    TestAssert::contains($logoClean, '<style', 'safe style element kept');
    TestAssert::contains($logoClean, '#f60', 'safe fill color kept');
    TestAssert::contains($logoClean, 'cls-1', 'class selector kept');
    TestAssert::contains($logoClean, 'class="cls-1"', 'class attr kept on path');

    echo "=== style element outside defs kept when safe ===\n";

    $bareStyle = '<svg xmlns="http://www.w3.org/2000/svg">'
        . '<style>.a{fill:#f60}</style>'
        . '<path class="a" d="M0 0h1"/>'
        . '</svg>';
    list($bareClean, $bareOk) = sanitize_svg_string($bareStyle);
    TestAssert::true($bareOk === true, 'bare style sanitize succeeds');
    TestAssert::contains($bareClean, '<style', 'bare safe style kept');
    TestAssert::contains($bareClean, '#f60', 'bare style fill kept');

    echo "=== digit-leading class escape preserved (no normalize writeback) ===\n";

    $digitEsc = '.' . chr(92) . '31 23{fill:#ff6600}';
    $digitSvg = '<svg xmlns="http://www.w3.org/2000/svg"><style>' . $digitEsc . '</style>'
        . '<path class="123" d="M0 0h1"/></svg>';
    list($digitClean, $digitOk) = sanitize_svg_string($digitSvg);
    TestAssert::true($digitOk === true, 'digit-class escape sanitize succeeds');
    TestAssert::contains($digitClean, chr(92) . '31 23', 'digit-class escape kept verbatim');
    TestAssert::contains($digitClean, '#ff6600', 'digit-class fill color kept');
    TestAssert::contains($digitClean, 'class="123"', 'digit class attr kept');
    TestAssert::notContains($digitClean, '.123{fill', 'must not decode escape into invalid .123 selector');

    echo "=== unicode escape / quoted comment / https text preserved ===\n";

    $preserveCss = '.a{fill:#f60;content:"/* keep */ https://example.com"}'
        . ' .' . chr(92) . '4e2d{opacity:.5}';
    $preserveSvg = '<svg xmlns="http://www.w3.org/2000/svg"><style>' . $preserveCss . '</style>'
        . '<path class="a" d="M0 0h1"/></svg>';
    list($preserveClean, $preserveOk) = sanitize_svg_string($preserveSvg);
    TestAssert::true($preserveOk === true, 'preserve-css sanitize succeeds');
    TestAssert::contains($preserveClean, '/* keep */', 'quoted comment kept');
    TestAssert::contains($preserveClean, 'https://example.com', 'https text in style kept');
    TestAssert::contains($preserveClean, chr(92) . '4e2d', 'unicode escape kept');
    TestAssert::contains($preserveClean, '#f60', 'preserve-css fill kept');

    echo "=== Illustrator DOCTYPE + CDATA style kept ===\n";

    $ai = '<?xml version="1.0"?>'
        . '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'
        . '<svg xmlns="http://www.w3.org/2000/svg">'
        . '<style type="text/css"><![CDATA[.st0{fill:#92c2d2;}]]></style>'
        . '<path class="st0" d="M0 0h10"/>'
        . '</svg>';
    list($aiClean, $aiOk) = sanitize_svg_string($ai);
    TestAssert::true($aiOk === true, 'illustrator cdata sanitize succeeds');
    TestAssert::contains($aiClean, '#92c2d2', 'illustrator fill kept');
    TestAssert::contains($aiClean, '<style', 'illustrator style kept');
    TestAssert::contains($aiClean, 'st0', 'illustrator class kept');

    echo "=== sanitize_uploaded_svg preserves style on svg and svgz ===\n";

    $logoDisk = $tmpdir . DIRECTORY_SEPARATOR . 'logo.svg';
    file_put_contents($logoDisk, $logoStyle);
    TestAssert::same(true, sanitize_uploaded_svg($logoDisk), 'logo.svg sanitize ok');
    $logoSaved = file_get_contents($logoDisk);
    TestAssert::contains($logoSaved, '#f60', 'logo.svg keeps fill color');
    TestAssert::contains($logoSaved, '<style', 'logo.svg keeps style');

    $logoSvgz = $tmpdir . DIRECTORY_SEPARATOR . 'logo.svgz';
    file_put_contents($logoSvgz, gzencode($logoStyle, 9));
    TestAssert::same(true, sanitize_uploaded_svg($logoSvgz), 'logo.svgz sanitize ok');
    list($logoSvgzXml, $logoSvgzOk) = svgz_decompress_limited($logoSvgz);
    TestAssert::true($logoSvgzOk === true, 'logo.svgz decompress after sanitize');
    TestAssert::contains($logoSvgzXml, '#f60', 'logo.svgz keeps fill color');
    TestAssert::contains($logoSvgzXml, '<style', 'logo.svgz keeps style');

    echo "=== presentation attrs / same-doc url(#id) kept; href channel still scrubbed ===\n";

    $paint = '<svg xmlns="http://www.w3.org/2000/svg">'
        . '<defs><radialGradient id="localGrad"/></defs>'
        . '<rect fill="url(#localGrad)" width="10" height="10"/>'
        . '<circle fill="url(#localGrad)" r="5"/>'
        . '<g style="fill:#f60;content:\'https://brand.example\'"/>'
        . '</svg>';
    list($paintClean, $paintOk) = sanitize_svg_string($paint);
    TestAssert::true($paintOk === true, 'paint sanitize succeeds');
    TestAssert::contains($paintClean, 'url(#localGrad)', 'same-doc url(#id) kept');
    TestAssert::contains($paintClean, 'https://brand.example', 'style https text kept');
    TestAssert::contains($paintClean, 'style=', 'presentation style attr kept');
    TestAssert::contains($paintClean, '<rect', 'rect kept');
    TestAssert::contains($paintClean, '<circle', 'circle kept');

    echo "=== style expression attr removed; onload / dangerous href still stripped ===\n";

    $expr = '<svg xmlns="http://www.w3.org/2000/svg">'
        . '<rect style="fill:expression(alert(1))" width="1" height="1" onload="alert(2)"/>'
        . '<a href="javascript:alert(3)"><text>x</text></a>'
        . '</svg>';
    list($exprClean, $exprOk) = sanitize_svg_string($expr);
    TestAssert::true($exprOk === true, 'expression/onload sanitize succeeds');
    TestAssert::notContains($exprClean, 'expression(', 'expression style removed');
    TestAssert::notContains($exprClean, 'onload', 'onload removed');
    TestAssert::notContains($exprClean, 'javascript:', 'javascript href removed');

    echo "=== benign design-tool css kept ===\n";

    $benign = '<svg xmlns="http://www.w3.org/2000/svg"><style>'
        . '.cls-1{fill:#f60;} .st0{fill:#F60;} .a,.b{fill-rule:evenodd}'
        . ' g &gt; .a{opacity:.5} @media (max-width:100px){.x{fill:#0a0}}'
        . '</style><rect class="cls-1" width="1" height="1"/></svg>';
    list($benignClean, $benignOk) = sanitize_svg_string($benign);
    TestAssert::true($benignOk === true, 'benign css sanitize succeeds');
    TestAssert::contains($benignClean, '#f60', 'benign css keeps fill color');
    TestAssert::contains($benignClean, '<style', 'benign css keeps style block');
    TestAssert::contains($benignClean, '@media', 'benign css keeps @media');

    @unlink($svgPath);
    @unlink($svgzPath);
    @unlink($bombPath);
    @unlink($logoDisk);
    @unlink($logoSvgz);
    @rmdir($tmpdir);
});
