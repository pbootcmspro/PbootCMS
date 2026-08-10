<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers ParserController sitestatistical 输出链路
 *
 * Issue #26 / 审计 #7：可信管理员统计码 decode 直出，禁止 filter_html / filter_statistical_html
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== ParserController statistical: decode_string + sanitizePhpOpenTag ===\n";
    $src = file_get_contents(APP_PATH . '/home/controller/ParserController.php');
    TestAssert::false($src === false, 'ParserController readable');

    TestAssert::contains($src, "case 'statistical':", 'statistical case exists');
    TestAssert::contains(
        $src,
        '$statistical = decode_string($data->statistical);',
        'uses decode_string on statistical'
    );
    TestAssert::contains(
        $src,
        '$this->sanitizePhpOpenTag($statistical)',
        'uses sanitizePhpOpenTag'
    );
    TestAssert::notContains(
        $src,
        'filter_html(decode_string($data->statistical))',
        'does not use filter_html on statistical'
    );
    TestAssert::notContains($src, 'filter_statistical_html', 'does not use filter_statistical_html');

    if (! preg_match(
        "/case 'statistical':.*?break;\\s*case 'copyright':.*?break;\\s*default:/s",
        $src
    )) {
        TestAssert::true(false, 'statistical and copyright must break before default');
    } else {
        TestAssert::true(true, 'statistical and copyright break before default');
    }

    echo "=== handle.php has no statistical script whitelist filter ===\n";
    $handle = file_get_contents(CORE_PATH . '/function/handle.php');
    TestAssert::false($handle === false, 'handle.php readable');
    TestAssert::notContains($handle, 'function filter_statistical_html(', 'filter_statistical_html removed');
    TestAssert::notContains($handle, 'function filter_statistical_whitelist(', 'filter_statistical_whitelist removed');
    TestAssert::contains($handle, 'function filter_html(', 'filter_html still exists for rich text');
});
