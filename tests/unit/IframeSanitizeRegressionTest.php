<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/handle.php filter_iframe_sanitize_src()
 * @covers core/function/handle.php filter_iframe_rebuild()
 * @covers core/function/handle.php filter_html_iframes()
 * @covers core/function/handle.php filter_html()
 *
 * 运行：
 *   php tests/unit/IframeSanitizeRegressionTest.php
 *   php tests/run.php --filter=Iframe
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    ConfigStub::reset();

    $exactWl = IframeSanitizeHarness::whitelistMap(array('example.com'));

    echo "=== filter_iframe_sanitize_src() rejects dangerous schemes ===\n";

    TestAssert::same('', filter_iframe_sanitize_src(IframeSanitizePayloads::entityEncodedJavascript()), 'sanitize: numeric entity javascript');
    TestAssert::same('', filter_iframe_sanitize_src(IframeSanitizePayloads::hexEntityJavascript()), 'sanitize: hex entity javascript');
    TestAssert::same('', filter_iframe_sanitize_src('JaVaScRiPt:alert(1)'), 'sanitize: mixed-case javascript');
    TestAssert::same('', filter_iframe_sanitize_src('vbscript:msgbox(1)'), 'sanitize: vbscript');
    TestAssert::same('', filter_iframe_sanitize_src('data:text/html,x'), 'sanitize: data');
    TestAssert::same('', filter_iframe_sanitize_src("https://example.com/x\x00y"), 'sanitize: null byte');
    TestAssert::same('', filter_iframe_sanitize_src("https://example.com/x\ny"), 'sanitize: newline');
    TestAssert::same('', filter_iframe_sanitize_src('https:\\/\\/example.com'), 'sanitize: backslash');

    TestAssert::same('https://example.com/embed', filter_iframe_sanitize_src('https://example.com/embed'), 'sanitize: https allowed');
    TestAssert::same('//example.com/embed', filter_iframe_sanitize_src('//example.com/embed'), 'sanitize: protocol-relative allowed');

    echo "=== filter_iframe_rebuild() strips dangerous attrs ===\n";

    $rebuilt = filter_iframe_rebuild(
        'onload="evil()" src="https://example.com/embed" width="560" height="315" title="t"',
        $exactWl
    );
    TestAssert::true($rebuilt !== '', 'rebuild: whitelisted src kept');
    IframeSanitizeHarness::assertSafeIframeContract($rebuilt, true, 'rebuild onload');
    TestAssert::contains($rebuilt, 'width="560"', 'rebuild: width preserved');
    TestAssert::contains($rebuilt, 'height="315"', 'rebuild: height preserved');
    TestAssert::contains($rebuilt, 'title="t"', 'rebuild: title preserved');
    TestAssert::notContains($rebuilt, 'onload', 'rebuild: onload dropped');

    TestAssert::same(
        '',
        filter_iframe_rebuild('src="foo>bar" onload="evil()"', $exactWl),
        'rebuild: malformed quote-gt rejected'
    );

    $dupGood = filter_iframe_rebuild(
        'src="javascript:alert(1)" src="https://example.com/embed"',
        $exactWl
    );
    TestAssert::true($dupGood !== '', 'rebuild: duplicate src last good wins');
    IframeSanitizeHarness::assertSafeIframeContract($dupGood, true, 'rebuild dup last good');

    TestAssert::same(
        '',
        filter_iframe_rebuild('src="https://example.com/embed" src="javascript:alert(1)"', $exactWl),
        'rebuild: duplicate src last bad rejected'
    );

    echo "=== filter_iframe_rebuild() unquoted src ===\n";

    $unquotedOut = filter_iframe_rebuild('src=https://example.com/embed width=560', $exactWl);
    TestAssert::true($unquotedOut !== '', 'rebuild: unquoted src whitelisted');
    IframeSanitizeHarness::assertSafeIframeContract($unquotedOut, true, 'rebuild unquoted');
    TestAssert::contains($unquotedOut, 'width="560"', 'rebuild: unquoted width preserved');

    $queryEquals = filter_iframe_rebuild(
        'src=https://example.com/x.jpg?x-oss-process=image/resize,w_1024',
        $exactWl
    );
    TestAssert::true($queryEquals !== '', 'rebuild: unquoted query equals kept');
    TestAssert::contains(
        $queryEquals,
        'x.jpg?x-oss-process=image/resize,w_1024',
        'rebuild: full query string preserved'
    );

    echo "=== filter_html_iframes() malformed / nested tags ===\n";

    ConfigStub::setIframeWhitelist(array('example.com'));

    $malformed = filter_html_iframes(IframeSanitizePayloads::malformedQuoteGt());
    TestAssert::false(IframeSanitizeHarness::hasIframeTag($malformed), 'iframes: quote-gt removed');
    IframeSanitizeHarness::assertSafeIframeContract($malformed, false, 'iframes quote-gt');

    $malformedPath = filter_html_iframes(IframeSanitizePayloads::malformedSrcPathGt());
    TestAssert::false(IframeSanitizeHarness::hasIframeTag($malformedPath), 'iframes: path-gt removed');
    IframeSanitizeHarness::assertSafeIframeContract($malformedPath, false, 'iframes path-gt');

    $whitelisted = filter_html_iframes(IframeSanitizePayloads::whitelistedWithOnload());
    IframeSanitizeHarness::assertSafeIframeContract($whitelisted, true, 'iframes whitelisted');
    TestAssert::contains($whitelisted, 'example.com', 'iframes: src host kept');

    $dupHtml = filter_html_iframes(IframeSanitizePayloads::duplicateSrcLastGood());
    IframeSanitizeHarness::assertSafeIframeContract($dupHtml, true, 'iframes dup last good');

    $dupBadHtml = filter_html_iframes(IframeSanitizePayloads::duplicateSrcLastBad());
    TestAssert::false(IframeSanitizeHarness::hasIframeTag($dupBadHtml), 'iframes: dup last bad removed');

    $unclosed = filter_html_iframes(IframeSanitizePayloads::unclosedWhitelisted());
    IframeSanitizeHarness::assertSafeIframeContract($unclosed, true, 'iframes unclosed');
    TestAssert::false(IframeSanitizeHarness::hasUnmatchedIframeClose($unclosed), 'iframes: no unmatched close tag');
    TestAssert::contains($unclosed, '<p>before</p>', 'iframes: surrounding html kept');
    TestAssert::contains($unclosed, '<p>after</p>', 'iframes: trailing html kept');

    $nestedGood = filter_html_iframes(IframeSanitizePayloads::nestedOuterGoodInnerBad());
    IframeSanitizeHarness::assertSafeIframeContract($nestedGood, true, 'iframes nested outer good');
    TestAssert::notContains($nestedGood, 'evil.com', 'iframes: inner evil host gone');

    $nestedBad = filter_html_iframes(IframeSanitizePayloads::nestedOuterBadInnerGood());
    TestAssert::false(IframeSanitizeHarness::hasIframeTag($nestedBad), 'iframes: nested outer bad fail-closed');
    IframeSanitizeHarness::assertSafeIframeContract($nestedBad, false, 'iframes nested outer bad');

    $nonWl = filter_html_iframes(IframeSanitizePayloads::nonWhitelisted());
    TestAssert::false(IframeSanitizeHarness::hasIframeTag($nonWl), 'iframes: non-whitelisted removed');
    TestAssert::contains($nonWl, '<p>keep</p>', 'iframes: non-wl keeps prefix');
    TestAssert::contains($nonWl, '<p>also</p>', 'iframes: non-wl keeps suffix');

    echo "=== filter_html_iframes() unquoted src ===\n";

    $unquotedIframe = filter_html_iframes('<iframe src=https://example.com/embed width=560></iframe>');
    IframeSanitizeHarness::assertSafeIframeContract($unquotedIframe, true, 'iframes unquoted');
    TestAssert::contains($unquotedIframe, 'example.com', 'iframes: unquoted whitelisted kept');

    echo "=== filter_html_iframes() empty whitelist removes all ===\n";

    ConfigStub::clearIframeWhitelist();
    $emptyWl = filter_html_iframes(IframeSanitizePayloads::whitelistedWithOnload());
    TestAssert::false(IframeSanitizeHarness::hasIframeTag($emptyWl), 'iframes: empty whitelist strips all');
    TestAssert::false(IframeSanitizeHarness::hasUnmatchedIframeClose($emptyWl), 'iframes: empty wl no unmatched close');

    echo "=== filter_html() end-to-end iframe + script ===\n";

    ConfigStub::setIframeWhitelist(array('example.com'));
    $filtered = filter_html(IframeSanitizePayloads::iframeWithScript());
    IframeSanitizeHarness::assertSafeIframeContract($filtered, true, 'filter_html iframe');
    TestAssert::notContains($filtered, '<script', 'filter_html: script tag removed');
    TestAssert::notContains($filtered, 'onload', 'filter_html: no onload');

    ConfigStub::reset();
});
