<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/handle.php build_tags_where()
 * @covers core/function/handle.php parse_fuzzy_param()
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== build_tags_where() exact mode ===\n";

    $exact = build_tags_where('新闻', false);
    TestAssert::contains($exact, "a.tags='新闻'", 'exact: single tag equality');
    TestAssert::contains($exact, "a.tags like '新闻,%'", 'exact: prefix boundary');
    TestAssert::contains($exact, "a.tags like '%,新闻'", 'exact: suffix boundary');
    TestAssert::contains($exact, "a.tags like '%,新闻,%'", 'exact: middle boundary');
    TestAssert::notContains($exact, "like '%新闻%'", 'exact: no substring fuzzy');

    echo "=== build_tags_where() fuzzy mode ===\n";

    $fuzzy = build_tags_where('新闻', true);
    TestAssert::same("a.tags like '%新闻%' ESCAPE '!'", $fuzzy, 'fuzzy: substring match with LIKE escape');

    echo "=== build_tags_where() empty input ===\n";

    TestAssert::same('', build_tags_where('', false), 'empty string returns empty');
    TestAssert::same('', build_tags_where('   ', false), 'whitespace returns empty');

    echo "=== parse_fuzzy_param() ===\n";

    TestAssert::same(false, parse_fuzzy_param('0', true), '0 is exact');
    TestAssert::same(true, parse_fuzzy_param('1', false), '1 is fuzzy');
    TestAssert::same(true, parse_fuzzy_param(null, true), 'null uses default true');
    TestAssert::same(false, parse_fuzzy_param(null, false), 'null uses default false');
    TestAssert::same(false, parse_fuzzy_param('off', true), 'off is exact');
    TestAssert::same(false, parse_fuzzy_param('false', true), 'false is exact');
    TestAssert::same(false, parse_fuzzy_param('', false), 'empty uses default false');
    TestAssert::same(true, parse_fuzzy_param('', true), 'empty uses default true');
});
