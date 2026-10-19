<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers apps/home/model/ParserModel.php
 * @covers apps/home/controller/ParserController.php
 *
 * 前台标签查询不再把变量拼进 where 字符串。get_lg() / date() 与 (int) 比较除外。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== ParserModel where literals ===\n";

    $src = file_get_contents(APP_PATH . '/home/model/ParserModel.php');
    TestAssert::false($src === false, 'ParserModel readable');

    TestAssert::notContains($src, 'implode_quot(', 'no implode_quot');
    TestAssert::notContains($src, 'escape_string(', 'no model escape_string');
    TestAssert::notContains($src, 'id=$id', 'no bare id equals variable');
    TestAssert::notContains($src, '" . session(', 'no session concat in SQL string');
    TestAssert::contains($src, "'a.id<' . (int) \$id", 'previous id uses int cast');
    TestAssert::contains($src, "'a.id>' . (int) \$id", 'next id uses int cast');

    $dollarLiteral = '';
    if (preg_match_all('/->where\s*\(\s*([\'"])(.*?)\\1/s', $src, $matches)) {
        foreach ($matches[2] as $literal) {
            if (strpos($literal, '$') !== false) {
                $dollarLiteral = $literal;
                break;
            }
        }
    }
    TestAssert::same('', $dollarLiteral, 'where string literal has no variable');

    echo "=== ParserController drops extra escape_string ===\n";

    $ctl = file_get_contents(APP_PATH . '/home/controller/ParserController.php');
    TestAssert::false($ctl === false, 'ParserController readable');

    foreach (array(
        'getMultSort',
        'getSelect',
        'getContent',
        'getAbout',
        'getContentPics',
        'getContentCheckbox',
        'getContentTags',
        'getSlides',
        'getLinks',
        'getMessage',
        'getFormTable',
        'getForm'
    ) as $method) {
        TestAssert::false(
            (bool) preg_match('/' . $method . '\s*\(\s*escape_string\s*\(/', $ctl),
            $method . ' arg is not escape_string'
        );
    }

    TestAssert::contains($ctl, 'getSlides($gid, (int) $num, $start)', 'slide num cast');
    TestAssert::contains($ctl, 'getLinks($gid, (int) $num, $start)', 'link num cast');
    TestAssert::contains($ctl, 'getMessage((int) $num, $page, $start, $lg)', 'message num cast');
    TestAssert::contains($ctl, 'getForm($table, (int) $num, $page, $start)', 'form num cast');
    TestAssert::contains($ctl, 'escape_string($value)', 'filter where still escapes values');
});
