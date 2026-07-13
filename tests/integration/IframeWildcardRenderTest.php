<?php

declare(strict_types=1);

/**
 * @suite integration
 * @covers filter_html_iframes() with Config content_iframe_whitelist wildcards
 *
 * 运行：
 *   php tests/run.php --suite=integration --filter=IframeWildcard
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== filter_html_iframes: *.example.com allows subdomain ===\n";

    ConfigStub::setIframeWhitelist(array('*.example.com'));
    $sub = filter_html_iframes('<iframe src="https://sub.example.com/v" onload="evil()"></iframe>');
    IframeSanitizeHarness::assertSafeIframeContract($sub, true, 'wildcard sub');
    TestAssert::contains($sub, 'sub.example.com', 'wildcard: subdomain kept');

    echo "=== filter_html_iframes: *.example.com rejects bare domain ===\n";

    $bare = filter_html_iframes('<iframe src="https://example.com/v"></iframe>');
    TestAssert::false(IframeSanitizeHarness::hasIframeTag($bare), 'wildcard: bare domain stripped');

    echo "=== filter_html_iframes: exact + wildcard mix ===\n";

    ConfigStub::setIframeWhitelist(array('map.baidu.com', '*.bilibili.com'));
    $map = filter_html_iframes('<iframe src="https://map.baidu.com/x"></iframe>');
    IframeSanitizeHarness::assertSafeIframeContract($map, true, 'mix exact');
    $bili = filter_html_iframes('<iframe src="https://player.bilibili.com/x"></iframe>');
    IframeSanitizeHarness::assertSafeIframeContract($bili, true, 'mix wildcard');
    $other = filter_html_iframes('<iframe src="https://evil.com/x"></iframe>');
    TestAssert::false(IframeSanitizeHarness::hasIframeTag($other), 'mix: non-wl stripped');
});
