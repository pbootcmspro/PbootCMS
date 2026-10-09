<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers app\home\controller\IndexController::_empty sort paging route
 * @covers app\admin\controller\content\ContentSortController filename conflict suffix
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

function sort_paging_extract_patterns(string $src, string $breakChar): array
{
    $sortPattern = '';
    if (preg_match("/preg_match\\('(\\/\\^\\([^']+\\))' \\. \\\$url_break_char \\. '([^']+)', \\\$path, \\\$matchs\\) && !!\\\$sort = \\\$this->model->getSort/", $src, $m)) {
        $sortPattern = $m[1] . $breakChar . $m[2];
    }
    $modelPattern = '';
    if (preg_match("/preg_match\\('(\\/\\^\\([^']+\\))\\(' \\. \\\$url_break_char \\. '(\\([^']+\\))\\)\\?' \\. \\\$url_break_char \\. '([^']+)', \\\$path, \\\$matchs\\);/", $src, $m)) {
        $modelPattern = $m[1] . '(' . $breakChar . $m[2] . ')?' . $breakChar . $m[3];
    }
    return array($sortPattern, $modelPattern);
}

// 模拟 _empty() 栏目匹配的前两个分支；$suffix 表示地址带 url_rule_suffix（如 .html）
function sort_paging_route(string $pattern, string $path, array $sorts, bool $suffix = false)
{
    if ($suffix) {
        return null; // 带后缀的地址交给内容路由，不按栏目或栏目分页匹配
    }
    if (in_array($path, $sorts, true)) {
        return array($path, null);
    }
    if (preg_match($pattern, $path, $matchs) && in_array($matchs[1], $sorts, true)) {
        return array($matchs[1], $matchs[2]);
    }
    return null;
}

return TestAssert::runSuite(function () {
    $src = file_get_contents(APP_PATH . '/home/controller/IndexController.php');
    TestAssert::false($src === false, 'IndexController readable');

    list($sortPattern, $modelPattern) = sort_paging_extract_patterns($src, '_');
    TestAssert::true($sortPattern !== '', 'sort paging pattern extracted');
    TestAssert::true($modelPattern !== '', 'model paging pattern extracted');
    TestAssert::contains($src, "if (!\$suffix && !!\$sort = \$this->model->getSort(\$path)) {", 'sort full path skips suffixed paths');
    TestAssert::contains($src, "} elseif (!\$suffix && preg_match('/^([\\w\\-\\/]+)' . \$url_break_char", 'sort paging skips suffixed content paths');

    echo "=== sort filename with underscore paginates ===\n";
    $sorts = array('c/Pet_collar', 'c/Pet-collar', 'c', 'a/b/c', 'news', 'news_7', '2024');
    $cases = array(
        'c/Pet_collar' => array('c/Pet_collar', null),
        'c/Pet_collar_2' => array('c/Pet_collar', '2'),
        'c/Pet_collar_15' => array('c/Pet_collar', '15'),
        'c/Pet-collar_2' => array('c/Pet-collar', '2'),
        'c_2' => array('c', '2'),
        'a/b/c_10' => array('a/b/c', '10'),
        'news_7' => array('news_7', null),
        'news_3' => array('news', '3'),
        'news_7_2' => array('news_7', '2'),
        '2024_3' => array('2024', '3'),
    );
    foreach ($cases as $path => $expected) {
        TestAssert::same($expected, sort_paging_route($sortPattern, $path, $sorts), "route: $path");
    }

    echo "=== non-sort paths fall through ===\n";
    foreach (array('c/Pet_collar', 'c/Pet_collar_', 'unknown_2', 'list_5_2', 'list_5') as $path) {
        TestAssert::same(null, sort_paging_route($sortPattern, $path, array('news')), "fallthrough: $path");
    }

    echo "=== suffixed content paths are not sort paging ===\n";
    // 内容短路径 article_9.html 中 article 为栏目名时，不得被当作 article 栏目第 9 页
    foreach (array('article_9', 'c/Pet_collar_2', 'news_7', 'news') as $path) {
        TestAssert::same(null, sort_paging_route($sortPattern, $path, array('article', 'c/Pet_collar', 'news', 'news_7'), true), "suffixed: $path.html");
    }

    echo "=== model path paging unchanged ===\n";
    TestAssert::true((bool) preg_match($modelPattern, 'list_5_2', $m), 'model paging: list_5_2 matches');
    TestAssert::same(array('list', '5', '2'), array($m[1], $m[3], $m[4]), 'model paging: list_5_2 parts');
    TestAssert::true((bool) preg_match($modelPattern, 'list_5', $m), 'model sort: list_5 matches');
    TestAssert::same(array('list', '', '5'), array($m[1], $m[2], $m[4]), 'model sort: list_5 parts');

    echo "=== custom break char ===\n";
    list($dashPattern) = sort_paging_extract_patterns($src, '-');
    TestAssert::same(array('c/Pet_collar', '2'), sort_paging_route($dashPattern, 'c/Pet_collar-2', array('c/Pet_collar')), 'dash break: c/Pet_collar-2');

    echo "=== admin filename conflict suffix uses dash ===\n";
    $admin = file_get_contents(APP_PATH . '/admin/controller/content/ContentSortController.php');
    TestAssert::false($admin === false, 'ContentSortController readable');
    TestAssert::notContains($admin, "'_' . mt_rand(", 'no underscore conflict suffix');
    TestAssert::same(2, substr_count($admin, "\$filename . '-' . mt_rand(1, 20)"), 'add and mod both use dash suffix');
});
