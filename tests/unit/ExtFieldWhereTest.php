<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/handle.php build_extfield_where()
 * @covers core/function/handle.php build_csv_field_where()
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * 用逗号边界规则判断 stored CSV 是否包含成员 q（与 exact SQL 语义一致）
 */
function extfield_exact_member_match($stored, $q)
{
    if ($stored === $q) {
        return true;
    }
    $prefix = $q . ',';
    if (strlen($stored) >= strlen($prefix) && substr($stored, 0, strlen($prefix)) === $prefix) {
        return true;
    }
    $suffix = ',' . $q;
    if (strlen($stored) >= strlen($suffix) && substr($stored, -strlen($suffix)) === $suffix) {
        return true;
    }
    return strpos($stored, ',' . $q . ',') !== false;
}

return TestAssert::runSuite(function () {
    echo "=== build_extfield_where() exact: digit members ===\n";

    $exact1 = build_extfield_where('ext_color', '1', false);
    TestAssert::contains($exact1, "ext_color='1'", 'exact: equality for lone value');
    TestAssert::contains($exact1, "ext_color like '1,%'", 'exact: prefix boundary');
    TestAssert::contains($exact1, "ext_color like '%,1'", 'exact: suffix boundary');
    TestAssert::contains($exact1, "ext_color like '%,1,%'", 'exact: middle boundary');
    TestAssert::notContains($exact1, "like '%1%'", 'exact: no unbounded substring');

    TestAssert::true(extfield_exact_member_match('1,11,21', '1'), '1,11,21 contains member 1');
    TestAssert::true(extfield_exact_member_match('1,11,21', '11'), '1,11,21 contains member 11');
    TestAssert::true(extfield_exact_member_match('1,11,21', '21'), '1,11,21 contains member 21');
    TestAssert::false(extfield_exact_member_match('11', '1'), '11 does not contain member 1');
    TestAssert::false(extfield_exact_member_match('21', '1'), '21 does not contain member 1');
    TestAssert::false(extfield_exact_member_match('11,21', '1'), '11,21 does not contain member 1');

    echo "=== build_extfield_where() exact: multi-digit & positions ===\n";

    TestAssert::true(extfield_exact_member_match('100,200', '100'), 'prefix multi-digit');
    TestAssert::true(extfield_exact_member_match('200,100', '100'), 'suffix multi-digit');
    TestAssert::true(extfield_exact_member_match('200,100,300', '100'), 'middle multi-digit');
    TestAssert::false(extfield_exact_member_match('100,200', '10'), '10 must not match 100');
    TestAssert::false(extfield_exact_member_match('100,200', '00'), '00 must not match 100');

    echo "=== build_extfield_where() exact: Chinese ===\n";

    $exactZh = build_extfield_where('ext_tag', '红色', false);
    TestAssert::contains($exactZh, "ext_tag='红色'", 'exact Chinese: equality');
    TestAssert::contains($exactZh, "ext_tag like '红色,%'", 'exact Chinese: prefix');
    TestAssert::contains($exactZh, "ext_tag like '%,红色'", 'exact Chinese: suffix');
    TestAssert::contains($exactZh, "ext_tag like '%,红色,%'", 'exact Chinese: middle');

    TestAssert::true(extfield_exact_member_match('红色,橙色', '红色'), 'Chinese prefix member');
    TestAssert::true(extfield_exact_member_match('橙色,红色', '红色'), 'Chinese suffix member');
    TestAssert::true(extfield_exact_member_match('橙,红色,蓝', '红色'), 'Chinese middle member');
    TestAssert::false(extfield_exact_member_match('深红色', '红色'), 'Chinese substring without comma boundary');

    echo "=== build_extfield_where() fuzzy mode ===\n";

    $fuzzy = build_extfield_where('ext_color', '1', true);
    TestAssert::same("ext_color like '%1%'", $fuzzy, 'fuzzy: unbounded substring (legacy)');

    echo "=== build_extfield_where() guards ===\n";

    TestAssert::same('', build_extfield_where('title', '1', false), 'non-ext field rejected');
    TestAssert::same('', build_extfield_where('ext_;drop', '1', false), 'invalid ext field rejected');
    TestAssert::same('', build_extfield_where('ext_color', '', false), 'empty value returns empty');
    TestAssert::same('', build_extfield_where('ext_color', '   ', false), 'whitespace value returns empty');

    echo "=== build_tags_where delegates to csv helper ===\n";

    $tagsExact = build_tags_where('新闻', false);
    TestAssert::contains($tagsExact, "a.tags='新闻'", 'tags exact still works');
    TestAssert::same("a.tags like '%新闻%'", build_tags_where('新闻', true), 'tags fuzzy still works');
});
