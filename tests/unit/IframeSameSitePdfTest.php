<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/handle.php filter_iframe_sanitize_src()
 * @covers core/function/handle.php filter_iframe_is_same_origin()
 * @covers core/function/handle.php filter_iframe_rebuild()
 * @covers core/function/handle.php filter_html_iframes()
 * @covers core/function/handle.php filter_html()
 *
 * 同源（本站）iframe / PDF 嵌入回归（issue #48）：
 *   - 相对路径与同源绝对地址（scheme+host+端口 全等）免走域名白名单
 *   - 同源 PDF 不下发 sandbox（Chrome/Chromium 禁止 sandbox iframe 渲染 PDF 插件）
 *   - 跨域嵌入仍需命中白名单、仍带 sandbox（安全边界不变）
 *   - 同 host 但跨端口 / 跨协议按跨源处理（不免白名单、不免 sandbox）
 *
 * 运行：
 *   php tests/unit/IframeSameSitePdfTest.php
 *   php tests/run.php --filter=Iframe
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    ConfigStub::reset();
    unset($_SERVER['HTTP_HOST']);

    $emptyWl = array();

    echo "=== filter_iframe_sanitize_src(): 放行同源根相对路径 ===\n";

    TestAssert::same(
        '/static/upload/file/20260722/x.pdf',
        filter_iframe_sanitize_src('/static/upload/file/20260722/x.pdf'),
        'sanitize: root-relative pdf allowed'
    );
    TestAssert::same('/embed/page', filter_iframe_sanitize_src('/embed/page'), 'sanitize: root-relative non-pdf allowed');
    TestAssert::same('//example.com/embed', filter_iframe_sanitize_src('//example.com/embed'), 'sanitize: protocol-relative still allowed');
    TestAssert::same('https://example.com/x', filter_iframe_sanitize_src('https://example.com/x'), 'sanitize: absolute still allowed');
    TestAssert::same('', filter_iframe_sanitize_src('foo.pdf'), 'sanitize: bare relative rejected (avoid bare-domain ambiguity)');
    TestAssert::same('', filter_iframe_sanitize_src('/x' . "\x00" . 'y.pdf'), 'sanitize: relative null byte rejected');
    TestAssert::same('', filter_iframe_sanitize_src('javascript:alert(1)'), 'sanitize: javascript rejected');

    echo "=== filter_iframe_is_same_origin()：完整 origin（scheme+host+端口）比较 ===\n";

    TestAssert::true(filter_iframe_is_same_origin('/embed/page'), 'same-origin: root-relative true');
    TestAssert::false(filter_iframe_is_same_origin('https://example.com/x'), 'same-origin: no HTTP_HOST -> absolute false');
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['HTTP_HOST'] = 'www.chengczx.com';
    TestAssert::true(filter_iframe_is_same_origin('https://www.chengczx.com/x'), 'same-origin: same scheme+host+port true');
    TestAssert::true(filter_iframe_is_same_origin('//www.chengczx.com/x'), 'same-origin: protocol-relative same host true');
    TestAssert::false(filter_iframe_is_same_origin('https://cdn.other.com/x'), 'same-origin: host mismatch false');
    TestAssert::false(filter_iframe_is_same_origin('https://www.chengczx.com:8443/x'), 'same-origin: PORT mismatch false (P1)');
    TestAssert::false(filter_iframe_is_same_origin('http://www.chengczx.com/x'), 'same-origin: SCHEME mismatch false (P1)');
    unset($_SERVER['HTTP_HOST'], $_SERVER['HTTPS']);

    echo "=== filter_iframe_rebuild(): 同源相对 PDF 保留且无 sandbox（空白名单也放行）===\n";

    $relPdf = filter_iframe_rebuild(
        'src="/static/upload/file/20260722/1784686115123352.pdf" width="800" height="1140" scrolling="no" align=""',
        $emptyWl
    );
    TestAssert::true($relPdf !== '', 'rebuild: relative pdf kept with empty whitelist');
    TestAssert::contains($relPdf, 'src="/static/upload/file/20260722/1784686115123352.pdf"', 'rebuild: relative pdf src kept');
    TestAssert::contains($relPdf, 'width="800"', 'rebuild: width kept');
    TestAssert::contains($relPdf, 'height="1140"', 'rebuild: height kept');
    TestAssert::notContains($relPdf, 'sandbox=', 'rebuild: same-origin pdf has NO sandbox');
    TestAssert::contains($relPdf, 'referrerpolicy="strict-origin-when-cross-origin"', 'rebuild: safe referrerpolicy kept');
    TestAssert::contains($relPdf, 'frameborder="0"', 'rebuild: frameborder kept');
    TestAssert::contains($relPdf, 'loading="lazy"', 'rebuild: loading kept');
    TestAssert::notContains($relPdf, 'scrolling', 'rebuild: non-whitelisted attr dropped');

    echo "=== filter_iframe_rebuild(): 同源相对非 PDF 保留但仍带 sandbox ===\n";

    $relHtml = filter_iframe_rebuild('src="/portal/embed" width="560"', $emptyWl);
    TestAssert::true($relHtml !== '', 'rebuild: relative non-pdf kept');
    TestAssert::contains($relHtml, 'src="/portal/embed"', 'rebuild: relative non-pdf src kept');
    TestAssert::contains($relHtml, 'sandbox=', 'rebuild: same-origin non-pdf keeps sandbox');

    echo "=== filter_iframe_rebuild(): 同源绝对 PDF（scheme+host+端口 全等）无 sandbox ===\n";

    $_SERVER['HTTPS'] = 'on';
    $_SERVER['HTTP_HOST'] = 'www.chengczx.com';
    $absPdf = filter_iframe_rebuild('src="https://www.chengczx.com/static/upload/file/x.pdf"', $emptyWl);
    TestAssert::true($absPdf !== '', 'rebuild: same-origin absolute pdf kept (whitelist bypass)');
    TestAssert::contains($absPdf, 'www.chengczx.com/static/upload/file/x.pdf', 'rebuild: absolute same-origin pdf src kept');
    TestAssert::notContains($absPdf, 'sandbox=', 'rebuild: absolute same-origin pdf no sandbox');

    echo "=== filter_iframe_rebuild(): 同 host 跨端口 / 跨协议属跨源，空白名单必删（P1 回归）===\n";

    TestAssert::same('', filter_iframe_rebuild('src="//www.chengczx.com:8443/x.pdf"', $emptyWl), 'rebuild: same-host cross-PORT pdf removed under empty wl');
    TestAssert::same('', filter_iframe_rebuild('src="https://www.chengczx.com:8443/x.pdf"', $emptyWl), 'rebuild: absolute cross-PORT pdf removed under empty wl');
    TestAssert::same('', filter_iframe_rebuild('src="http://www.chengczx.com/x.pdf"', $emptyWl), 'rebuild: cross-SCHEME pdf removed under empty wl');

    echo "=== filter_iframe_rebuild(): 跨端口即便命中白名单也须保留 sandbox（免 sandbox 不得泄漏跨源）===\n";

    $crossPort = filter_iframe_rebuild(
        'src="https://www.chengczx.com:8443/x.pdf"',
        IframeSanitizeHarness::whitelistMap(array('www.chengczx.com'))
    );
    TestAssert::true($crossPort !== '', 'rebuild: whitelisted cross-port pdf kept');
    TestAssert::contains($crossPort, 'sandbox=', 'rebuild: cross-port pdf keeps sandbox (not same-origin)');

    TestAssert::same('', filter_iframe_rebuild('src="https://cdn.other.com/x.pdf"', $emptyWl), 'rebuild: cross-origin pdf empty wl removed');
    $crossPdf = filter_iframe_rebuild(
        'src="https://cdn.other.com/x.pdf"',
        IframeSanitizeHarness::whitelistMap(array('cdn.other.com'))
    );
    TestAssert::true($crossPdf !== '', 'rebuild: whitelisted cross-origin pdf kept');
    TestAssert::contains($crossPdf, 'sandbox=', 'rebuild: cross-origin pdf keeps sandbox (same-origin exemption must not leak cross-origin)');
    unset($_SERVER['HTTP_HOST'], $_SERVER['HTTPS']);

    echo "=== filter_html_iframes(): 空白名单保留同源 PDF、移除跨域 ===\n";

    ConfigStub::clearIframeWhitelist();
    $mixed = filter_html_iframes(
        '<p>x</p><iframe src="/static/upload/file/x.pdf" width="800"></iframe><iframe src="https://evil.com/e"></iframe>'
    );
    TestAssert::true(IframeSanitizeHarness::hasIframeTag($mixed), 'iframes: same-origin pdf survives empty whitelist');
    TestAssert::contains($mixed, '/static/upload/file/x.pdf', 'iframes: pdf src kept');
    TestAssert::notContains($mixed, 'evil.com', 'iframes: cross-origin removed under empty whitelist');
    TestAssert::notContains($mixed, 'sandbox=', 'iframes: same-origin pdf no sandbox');
    TestAssert::contains($mixed, '<p>x</p>', 'iframes: surrounding html kept');
    TestAssert::false(IframeSanitizeHarness::hasUnmatchedIframeClose($mixed), 'iframes: no unmatched close tag');

    echo "=== filter_html(): issue #48 用户实景（无白名单，相对 PDF 正常渲染）===\n";

    ConfigStub::clearIframeWhitelist();
    $payload = '<p><iframe src="/static/upload/file/20260722/1784686115123352.pdf" width="800" height="1140" scrolling="no" frameborder="0" align="" loading="lazy"></iframe></p>';
    $out = filter_html($payload);
    TestAssert::contains($out, '<iframe', 'filter_html: pdf iframe kept');
    TestAssert::contains($out, '/static/upload/file/20260722/1784686115123352.pdf', 'filter_html: pdf src kept');
    TestAssert::notContains($out, 'sandbox=', 'filter_html: same-origin pdf no sandbox');

    ConfigStub::reset();
    unset($_SERVER['HTTP_HOST']);
});
