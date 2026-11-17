<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers app\home\controller\SitemapController::pagePlan
 * @covers app\home\controller\SitemapController::contentWindow
 * @covers app\home\controller\SitemapController::childLoc
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once CORE_PATH . '/basic/Url.php';
require_once APP_PATH . '/home/controller/SitemapController.php';

if (! defined('SITE_INDEX_DIR')) {
    define('SITE_INDEX_DIR', '');
}

function sitemap_plan_reset_url_cache()
{
    $prop = (new ReflectionClass('core\\basic\\Url'))->getProperty('urls');
    if (PHP_VERSION_ID < 80100) {
        $prop->setAccessible(true);
    }
    $prop->setValue(null, array());
}

return TestAssert::runSuite(function () {
    echo "=== 恰好 5 万条 URL 不分页 ===\n";

    $exact = \app\home\controller\SitemapController::pagePlan(26, 49974);
    TestAssert::same(50000, $exact['total'], '首页加栏目加内容恰好 5 万');
    TestAssert::false($exact['indexed'], '恰好 5 万仍走单文件');
    TestAssert::same(1, $exact['pages'], '恰好 5 万只有 1 页');
    TestAssert::same(49974, $exact['first_content'], '第 1 页内容名额扣掉固定链接');

    echo "=== 5 万加 1 拆成索引 ===\n";

    $over = \app\home\controller\SitemapController::pagePlan(26, 49975);
    TestAssert::same(50001, $over['total'], '超过 5 万 1 条');
    TestAssert::true($over['indexed'], '超过 5 万走索引');
    TestAssert::same(2, $over['pages'], '多出的 1 条内容进入第 2 页');
    TestAssert::same(49974, $over['first_content'], '第 1 页仍只放剩余名额');
    TestAssert::same(
        array(0, 49974),
        \app\home\controller\SitemapController::contentWindow(1, $over),
        '第 1 页内容从 0 开始'
    );
    TestAssert::same(
        array(49974, 50000),
        \app\home\controller\SitemapController::contentWindow(2, $over),
        '第 2 页接在第 1 页内容之后'
    );

    echo "=== 20 万条内容的页窗口 ===\n";

    $large = \app\home\controller\SitemapController::pagePlan(26, 200000);
    TestAssert::true($large['indexed'], '20 万条走索引');
    TestAssert::same(5, $large['pages'], '固定链接占名额后共 5 页');
    TestAssert::same(
        array(149974, 50000),
        \app\home\controller\SitemapController::contentWindow(4, $large),
        '第 4 页偏移'
    );
    TestAssert::same(
        array(199974, 50000),
        \app\home\controller\SitemapController::contentWindow(5, $large),
        '最后一页偏移'
    );

    echo "=== 固定链接占满或超过上限 ===\n";

    $full = \app\home\controller\SitemapController::pagePlan(50000, 0);
    TestAssert::false($full['indexed'], '固定链接恰好 5 万且没有内容时不拆分');

    $fixedFull = \app\home\controller\SitemapController::pagePlan(50000, 1);
    TestAssert::true($fixedFull['indexed'], '固定链接占满后再有内容则拆分');
    TestAssert::same(0, $fixedFull['first_content'], '第 1 页不再放内容');
    TestAssert::same(2, $fixedFull['pages'], '多出的内容在第 2 页');
    TestAssert::same(
        array(0, 0),
        \app\home\controller\SitemapController::contentWindow(1, $fixedFull),
        '第 1 页内容窗口为空'
    );

    $tooManySorts = \app\home\controller\SitemapController::pagePlan(50001, 0);
    TestAssert::true($tooManySorts['indexed'], '栏目本身超过 5 万仍标记为索引');
    TestAssert::same(1, $tooManySorts['pages'], '没有内容时只有第 1 页');
    TestAssert::same(0, $tooManySorts['first_content'], '第 1 页内容名额为 0');

    echo "=== 三种 URL 模式的子文件地址 ===\n";

    ConfigStub::reset();
    $_SERVER['HTTP_HOST'] = 'www.example.com';
    unset($_SERVER['HTTPS'], $_SERVER['REQUEST_SCHEME'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_CLIENT_SCHEME']);

    $cases = array(
        '1' => array(
            'http://www.example.com/index.php/sitemap.xml?page=1',
            'http://www.example.com/index.php/sitemap.xml?page=2'
        ),
        '2' => array(
            'http://www.example.com/sitemap.xml?page=1',
            'http://www.example.com/sitemap.xml?page=2'
        ),
        '3' => array(
            'http://www.example.com/?sitemap.xml&amp;page=1',
            'http://www.example.com/?sitemap.xml&amp;page=2'
        )
    );
    foreach ($cases as $type => $expected) {
        sitemap_plan_reset_url_cache();
        ConfigStub::set(array(
            'url_rule_type' => $type,
            'url_rule_suffix' => '.html'
        ));
        $first = \app\home\controller\SitemapController::childLoc(1);
        $second = \app\home\controller\SitemapController::childLoc(2);
        TestAssert::same($expected[0], $first, '模式 ' . $type . ' 第 1 页地址');
        TestAssert::same($expected[1], $second, '模式 ' . $type . ' 第 2 页地址');
        TestAssert::notContains($first . $second, '&&', '模式 ' . $type . ' 不重复拼接 &');
        TestAssert::notContains($first . $second, '??', '模式 ' . $type . ' 不重复拼接 ?');
    }
    TestAssert::notContains($cases['3'][0], '&page=', '兼容模式地址中的 & 已转义');

    ConfigStub::reset();
});
