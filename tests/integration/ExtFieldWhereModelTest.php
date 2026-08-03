<?php

declare(strict_types=1);

/**
 * @suite integration
 * @covers core/basic/Model.php where()
 * @covers core/function/handle.php build_extfield_where()
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

defined('CONF_PATH') ?: define('CONF_PATH', ROOT_PATH . 'config');
defined('RUN_PATH') ?: define('RUN_PATH', ROOT_PATH . 'runtime');
require CORE_PATH . '/basic/Config.php';
require CORE_PATH . '/basic/Model.php';

/**
 * 模拟 ParserModel::getList 对 $where3 的调用方式
 *
 * @param array<int|string, mixed> $where3
 */
function build_select_sql(array $where3, $fuzzy = false)
{
    $model = new \core\basic\Model();
    return $model->table('ay_content a')
        ->where(array('a.status=1'), 'AND', 'AND', false)
        ->where($where3, 'AND', 'AND', $fuzzy)
        ->select(false);
}

return TestAssert::runSuite(function () {
    echo "=== Model::where() + ext clause (fixed path) ===\n";

    $clause = build_extfield_where('ext_color', '1', false);
    TestAssert::contains($clause, "ext_color='1'", 'clause: equality built');

    $where3 = array();
    $where3[] = $clause;

    $sql = build_select_sql($where3, false);
    TestAssert::contains($sql, "ext_color='1'", 'fixed: equality in final SQL');
    TestAssert::contains($sql, "ext_color like '1,%'", 'fixed: prefix boundary in final SQL');
    TestAssert::contains($sql, "ext_color like '%,1,%'", 'fixed: middle boundary in final SQL');
    TestAssert::notContains($sql, "like '%1%'", 'fixed: exact mode no unbounded fuzzy');

    echo "=== Model::where() string key (legacy broken path) ===\n";

    $legacyExact = build_select_sql(array('ext_color' => '1'), false);
    TestAssert::contains($legacyExact, '`ext_color`=', 'legacy exact: whole-column equality only');
    TestAssert::notContains($legacyExact, "like '1,%'", 'legacy exact: no comma boundary');

    $legacyFuzzy = build_select_sql(array('ext_color' => '1'), true);
    TestAssert::contains($legacyFuzzy, '`ext_color` like ?', 'legacy fuzzy: uses bind placeholder (unbounded at execution)');

    echo "=== fuzzy=1 on fixed path keeps legacy substring ===\n";

    $fuzzyClause = build_extfield_where('ext_color', '1', true);
    $sqlFuzzy = build_select_sql(array($fuzzyClause), true);
    TestAssert::contains($sqlFuzzy, "ext_color like '%1%' ESCAPE '!'", 'fuzzy: substring preserved through Model');

    echo "=== search-path style: fuzzy=0 + ext_ CSV member ===\n";

    // 模拟搜索/API 接入 build_extfield_where 后：$where3[] = clause（非字符串键）
    $searchExact = build_extfield_where('ext_color', '红色', false);
    $sqlSearchExact = build_select_sql(array($searchExact), false);
    TestAssert::contains($sqlSearchExact, "ext_color='红色'", 'search fuzzy=0: equality in SQL');
    TestAssert::contains($sqlSearchExact, "ext_color like '红色,%' ESCAPE '!'", 'search fuzzy=0: prefix boundary in SQL');
    TestAssert::contains($sqlSearchExact, "ext_color like '%,红色' ESCAPE '!'", 'search fuzzy=0: suffix boundary in SQL');
    TestAssert::contains($sqlSearchExact, "ext_color like '%,红色,%' ESCAPE '!'", 'search fuzzy=0: middle boundary in SQL');
    TestAssert::notContains($sqlSearchExact, "like '%红色%'", 'search fuzzy=0: no unbounded substring');

    echo "=== search-path style: fuzzy default (true) unchanged semantics ===\n";

    $searchFuzzy = build_extfield_where('ext_color', '红色', true);
    TestAssert::same("ext_color like '%红色%' ESCAPE '!'", $searchFuzzy, 'search fuzzy default: still substring');
    $sqlSearchFuzzy = build_select_sql(array($searchFuzzy), true);
    TestAssert::contains($sqlSearchFuzzy, "ext_color like '%红色%' ESCAPE '!'", 'search fuzzy default: substring in SQL');

    echo "=== LIKE metachar must not amplify ===\n";

    $pctFuzzy = build_extfield_where('ext_color', '%', true);
    TestAssert::same("ext_color like '%!%%' ESCAPE '!'", $pctFuzzy, '% fuzzy: escaped');
    $pctExact = build_extfield_where('ext_color', '%', false);
    TestAssert::contains($pctExact, "ESCAPE '!'", '% exact: ESCAPE on LIKE arms');
    TestAssert::notContains($pctExact, "like '%,%'", '% exact: no bare comma-wildcard amplify');
});
