<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers app\home\controller\SitemapController
 * @covers app\home\model\SitemapModel
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

function sitemap_contract_slice($src, $from, $to)
{
    $start = strpos($src, $from);
    TestAssert::true($start !== false, '找到 ' . $from);
    if ($start === false) {
        return '';
    }
    if ($to === '') {
        return substr($src, $start);
    }
    $end = strpos($src, $to, $start + strlen($from));
    TestAssert::true($end !== false, '找到 ' . $to);
    if ($end === false) {
        return '';
    }
    return substr($src, $start, $end - $start);
}

return TestAssert::runSuite(function () {
    $controller = file_get_contents(APP_PATH . '/home/controller/SitemapController.php');
    $model = file_get_contents(APP_PATH . '/home/model/SitemapModel.php');
    $index = file_get_contents(APP_PATH . '/home/controller/IndexController.php');
    $route = file_get_contents(APP_PATH . '/common/route.php');
    $llms = file_get_contents(APP_PATH . '/home/controller/LlmsController.php');
    $home = file_get_contents(APP_PATH . '/common/HomeController.php');
    TestAssert::false($controller === false, 'SitemapController 可读');
    TestAssert::false($model === false, 'SitemapModel 可读');

    echo "=== 页码校验先于输出 ===\n";

    $indexBody = sitemap_contract_slice($controller, 'function index()', 'function linkTxt()');
    $txtBody = sitemap_contract_slice($controller, 'function linkTxt()', 'function pagePlan(');
    $prepare = sitemap_contract_slice($controller, 'function prepare()', 'function readPage()');
    foreach (array('index' => $indexBody, 'linkTxt' => $txtBody) as $name => $body) {
        $preparePos = strpos($body, 'prepare()');
        $headerPos = strpos($body, 'header(');
        TestAssert::true($preparePos !== false && $headerPos !== false && $preparePos < $headerPos, $name . ' 在输出头之前完成分流');
    }
    $homeCtor = sitemap_contract_slice($home, 'function __construct()', '}');
    TestAssert::contains($homeCtor, 'Request::validate()', '畸形 page 由前台公共控制器先行 400 拒绝');
    TestAssert::notContains($controller, 'Request::validate()', 'sitemap 不重复校验 page');
    TestAssert::contains($prepare, '_404(', '超出页数返回 404');
    TestAssert::notContains($prepare, 'header(', '校验失败时不发送 sitemap 内容类型');

    echo "=== 未超限走原拼接，超限逐条输出索引 ===\n";

    TestAssert::contains($indexBody, "renderLegacyXml(\$plan['sorts'])", '未超限 XML 复用已取栏目');
    TestAssert::contains($indexBody, 'renderSitemapIndex(', '超限且不带 page 时输出索引');
    TestAssert::contains($txtBody, "renderLegacyTxt(\$plan['sorts'])", '未超限 TXT 复用已取栏目');
    TestAssert::contains($txtBody, "\$page = 1", 'TXT 不带 page 时输出第 1 页');

    $legacyXml = sitemap_contract_slice($controller, 'function renderLegacyXml(', 'function makeNode(');
    TestAssert::contains($legacyXml, '<urlset xmlns= "http://www.sitemaps.org/schemas/sitemap/0.9">', '原 urlset 头保持不变');
    TestAssert::contains($legacyXml, '$str .=', '未超限仍整段拼接');
    TestAssert::contains($legacyXml, 'getSortContent(', '未超限仍按栏目取内容');
    TestAssert::notContains($legacyXml, 'getSorts(', '未超限 XML 不再查栏目');

    $legacyTxt = sitemap_contract_slice($controller, 'function renderLegacyTxt(', '');
    TestAssert::contains($legacyTxt, '$str .=', '未超限 TXT 仍整段拼接');
    TestAssert::contains($legacyTxt, 'getSortContent(', '未超限 TXT 仍按栏目取内容');
    TestAssert::notContains($legacyTxt, 'getSorts(', '未超限 TXT 不再查栏目');

    $indexRender = sitemap_contract_slice($controller, 'function renderSitemapIndex(', 'function renderUrlsetPage(');
    TestAssert::contains($indexRender, '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', '索引使用 sitemapindex');
    TestAssert::contains($indexRender, 'childLoc(', '索引用子文件地址');
    TestAssert::notContains($indexRender, '$str .=', '索引不累积整段字符串');

    $urlset = sitemap_contract_slice($controller, 'function renderUrlsetPage(', 'function renderTxtPage(');
    TestAssert::contains($urlset, "echo \"\\n</urlset>\";", '子文件逐段输出 urlset');
    TestAssert::notContains($urlset, '$str .=', '子文件不累积整段字符串');

    $child = sitemap_contract_slice($controller, 'function childLoc(', 'function prepare()');
    TestAssert::contains($child, "Url::addQuery(Url::home('sitemap.xml', false)", '子文件地址在三种 URL 模式上追加 page');
    TestAssert::contains($child, 'htmlspecialchars(', '子文件地址做 XML 转义');

    echo "=== 分页查询不改旧接口 ===\n";

    $oldContent = sitemap_contract_slice($model, 'function getSortContent(', 'function countOutputContent(');
    TestAssert::notContains($oldContent, 'order(', '原栏目内容查询仍不排序');
    $pageQuery = sitemap_contract_slice($model, 'function getContentPage(', 'function outputContentQuery(');
    TestAssert::contains($pageQuery, "order('a.id ASC')", '分页内容按 id 升序');
    TestAssert::contains($pageQuery, '->limit(', '分页使用 limit');
    TestAssert::notContains($model, '->page(', '不走会读取 page 参数的分页器');
    TestAssert::contains($model, 'function getLlmsSortContent(', 'llms 查询仍在');

    echo "=== 入口与兼容模式 page 参数 ===\n";

    TestAssert::contains($route, "'home/sitemap.xml' => 'home/Sitemap/index'", 'sitemap.xml 路由不变');
    TestAssert::contains($route, "'home/sitemap.txt' => 'home/Sitemap/linkTxt'", 'sitemap.txt 路由不变');
    TestAssert::contains($route, "'home/sitemap' => 'home/Sitemap/index'", 'sitemap 路由不变');
    TestAssert::contains($index, "unset(\$output['page'])", '兼容模式只从路径解析里去掉 page');
    TestAssert::notContains($index, "unset(\$_GET['page'])", '兼容模式保留 page 查询参数');
    TestAssert::contains($llms, "Url::home('sitemap.xml', false)", 'llms.txt 仍指向不带 page 的 sitemap.xml');
});
