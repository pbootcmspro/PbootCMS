<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers tests/scripts/build_login_bg_svg.php login_bg_svg
 *
 * 运行：php tests/unit/LoginBgSvgTest.php
 * 重新生成：php tests/scripts/build_login_bg_svg.php
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require TEST_ROOT . 'scripts/build_login_bg_svg.php';

return TestAssert::runSuite(function () {
    $theme = APP_PATH . '/admin/view/default';
    $file = $theme . '/images/bg.svg';
    $svg = (string) @file_get_contents($file);

    echo "=== bg.svg matches generator ===\n";
    TestAssert::true($svg !== '', 'bg.svg exists');
    TestAssert::true(login_bg_svg() === $svg, 'bg.svg equals generator output; rerun tests/scripts/build_login_bg_svg.php');
    TestAssert::same(md5(login_bg_svg()), md5(login_bg_svg()), 'generator is deterministic');

    echo "=== bg.svg is small, valid and inert ===\n";
    TestAssert::true(strlen($svg) <= 5120, 'bg.svg is at most 5KB');
    $prev = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($svg);
    libxml_use_internal_errors($prev);
    TestAssert::true($doc !== false && $doc->getName() === 'svg', 'bg.svg parses as svg');
    $body = str_replace('xmlns="http://www.w3.org/2000/svg"', '', $svg);
    foreach (array('<script', 'foreignObject', 'href', '//', '<image', '@import') as $needle) {
        TestAssert::false(stripos($body, $needle) !== false, 'bg.svg has no ' . $needle);
    }
    TestAssert::false(preg_match('/\son\w+\s*=/i', $body) === 1, 'bg.svg has no on* event attribute');

    echo "=== login.css uses bg.svg ===\n";
    $css = (string) file_get_contents($theme . '/css/login.css');
    TestAssert::contains($css, 'url(../images/bg.svg) no-repeat center / cover', 'login.css covers with bg.svg');
    TestAssert::notContains($css, 'bg.jpg', 'login.css no longer references bg.jpg');
    TestAssert::false(file_exists($theme . '/images/bg.jpg'), 'bg.jpg is removed');
});
