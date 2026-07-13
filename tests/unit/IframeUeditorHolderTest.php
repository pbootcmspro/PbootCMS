<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/handle.php ueditor_holder_html()
 * @covers core/function/handle.php normalize_iframe_unquoted_src()
 * @covers core/function/handle.php normalize_richtext_for_storage()
 *
 * 运行：
 *   php tests/unit/IframeUeditorHolderTest.php
 *   php tests/run.php --filter=Iframe
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== ueditor_holder_html() escapes </script> breakout ===\n";

    $literal = ueditor_holder_html(IframeSanitizePayloads::scriptCloseBreakout());
    TestAssert::false(IframeSanitizeHarness::hasBareScriptClose($literal), 'ueditor: no bare script close');
    TestAssert::contains($literal, '<\\/script', 'ueditor: escaped script close');
    TestAssert::contains($literal, '<img onerror=1>', 'ueditor: rest of content preserved');

    $escaped = escape_string(IframeSanitizePayloads::scriptCloseBreakout());
    $fromDb = ueditor_holder_html($escaped);
    TestAssert::false(IframeSanitizeHarness::hasBareScriptClose($fromDb), 'ueditor: entity round-trip no bare close');
    TestAssert::contains($fromDb, '<\\/script', 'ueditor: entity round-trip escaped close');

    $entity = ueditor_holder_html(IframeSanitizePayloads::scriptCloseEntity());
    TestAssert::false(IframeSanitizeHarness::hasBareScriptClose($entity), 'ueditor: decoded entity no bare close');
    TestAssert::contains($entity, '<\\/script', 'ueditor: decoded entity escaped close');

    echo "=== ueditor_holder_html() does not strip iframe (fix/67) ===\n";

    $withIframe = ueditor_holder_html(IframeSanitizePayloads::editorContentWithIframe());
    TestAssert::contains($withIframe, '<iframe', 'ueditor: iframe preserved');
    TestAssert::contains($withIframe, 'player.bilibili.com', 'ueditor: iframe src preserved');
    TestAssert::contains($withIframe, '<p>text</p>', 'ueditor: surrounding html preserved');

    $iframeFromDb = ueditor_holder_html(escape_string(IframeSanitizePayloads::editorContentWithIframe()));
    TestAssert::contains($iframeFromDb, '<iframe', 'ueditor: iframe preserved after escape round-trip');
    TestAssert::contains($iframeFromDb, 'player.bilibili.com', 'ueditor: src preserved after round-trip');

    echo "=== ueditor_holder_html() quotes unquoted iframe src ===\n";

    $unquoted = '<iframe src=https://imgpai.thepaper.cn/newpai/image/x.jpg?x-oss-process=image/resize,w_1024></iframe>';
    $quotedOut = ueditor_holder_html(escape_string($unquoted));
    TestAssert::contains($quotedOut, 'src="https://imgpai.thepaper.cn/newpai/image/x.jpg?x-oss-process=image/resize,w_1024"', 'ueditor: unquoted src quoted for editor');
    TestAssert::notContains($quotedOut, 'src="https:"', 'ueditor: not truncated to https:');

    echo "=== normalize_iframe_unquoted_src() duplicate / mixed src ===\n";

    $dupUnquoted = normalize_iframe_unquoted_src(
        '<iframe src=javascript:alert(1) src=https://example.com/embed></iframe>'
    );
    TestAssert::contains($dupUnquoted, 'src="javascript:alert(1)"', 'normalize: quotes first unquoted src');
    TestAssert::contains($dupUnquoted, 'src="https://example.com/embed"', 'normalize: quotes last unquoted src');

    $mixed = normalize_iframe_unquoted_src(
        '<iframe src="https://evil.com/x" src=https://example.com/embed></iframe>'
    );
    TestAssert::contains($mixed, 'src="https://evil.com/x"', 'normalize: keeps existing quoted src');
    TestAssert::contains($mixed, 'src="https://example.com/embed"', 'normalize: quotes trailing unquoted src');

    echo "=== normalize_richtext_for_storage() ===\n";

    $stored = normalize_richtext_for_storage(escape_string($unquoted));
    TestAssert::contains(decode_string($stored), 'src="https://imgpai.thepaper.cn/newpai/image/x.jpg?x-oss-process=image/resize,w_1024"', 'storage: iframe src preserved after round-trip');
});
