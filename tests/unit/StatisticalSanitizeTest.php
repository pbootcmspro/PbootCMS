<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers decode_string + ParserController::sanitizePhpOpenTag 统计码输出链路
 *
 * Issue #26 / 审计 #7：可信管理员配置的统计码完整输出（勿走 filter_html）
 *
 * 运行：
 *   php tests/unit/StatisticalSanitizeTest.php
 *   php tests/run.php --filter=Statistical
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once TEST_ROOT . 'fixtures/security/StatisticalSanitizePayloads.php';

/**
 * 与 ParserController statistical 分支一致：decode_string 后 sanitizePhpOpenTag
 */
function statistical_output_pipeline($raw)
{
    $h = ParserControllerHarness::create();
    $ref = new ReflectionClass($h->parser);
    $m = $ref->getMethod('sanitizePhpOpenTag');
    if (PHP_VERSION_ID < 80100) {
        $m->setAccessible(true);
    }
    return $m->invoke($h->parser, decode_string($raw));
}

return TestAssert::runSuite(function () {
    echo "=== filter_html() destroys stats (why statistical must not use it) ===\n";
    $baidu = StatisticalSanitizePayloads::baiduInline();
    $broken = filter_html($baidu);
    TestAssert::notContains($broken, '<script', 'filter_html: script tag stripped');

    echo "=== trusted admin pipeline keeps Baidu / CNZZ intact ===\n";
    $baiduOut = statistical_output_pipeline($baidu);
    TestAssert::contains($baiduOut, '<script', 'baidu inline: script kept');
    TestAssert::contains($baiduOut, 'hm.baidu.com/hm.js', 'baidu inline: hm.js kept');
    TestAssert::contains($baiduOut, '_hmt', 'baidu inline: _hmt kept');

    $baiduExt = statistical_output_pipeline(StatisticalSanitizePayloads::baiduExternal());
    TestAssert::same(
        StatisticalSanitizePayloads::baiduExternal(),
        $baiduExt,
        'baidu external: passthrough'
    );

    $cnzzExt = statistical_output_pipeline(StatisticalSanitizePayloads::cnzzExternal());
    TestAssert::contains($cnzzExt, 's4.cnzz.com/z_stat.php', 'cnzz external: src kept');
    TestAssert::contains($cnzzExt, 'language="JavaScript"', 'cnzz external: attrs kept');

    $cnzzInline = statistical_output_pipeline(StatisticalSanitizePayloads::cnzzInlineConcat());
    TestAssert::contains($cnzzInline, 'document.write', 'cnzz inline: document.write kept');
    TestAssert::contains($cnzzInline, 'unescape', 'cnzz inline: unescape kept');
    TestAssert::contains($cnzzInline, 's23.cnzz.com', 'cnzz inline: host kept');

    echo "=== sanitizePhpOpenTag still blocks PHP open tags ===\n";
    $phpOut = statistical_output_pipeline(StatisticalSanitizePayloads::withPhpOpenTag());
    TestAssert::contains($phpOut, '<script>var x=1;</script>', 'php sample: script kept');
    TestAssert::notContains($phpOut, '<?php', 'php sample: open tag escaped');
    TestAssert::contains($phpOut, '&lt;?', 'php sample: escaped entity present');
});
