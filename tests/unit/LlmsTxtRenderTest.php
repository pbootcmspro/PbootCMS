<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers app\home\controller\LlmsController::render()
 *
 * 校验输出严格符合 https://llmstxt.org/ 结构，并覆盖外链跳过、条数上限、
 * Optional 段与输出范围开关等验收点；数据源与地址生成均以测试桩替代，不依赖数据库。
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

define('SITE_DIR', '');
define('SITE_INDEX_DIR', '');

require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/Url.php';
require APP_PATH . '/common/function.php';
require APP_PATH . '/home/controller/LlmsController.php';

/** 栏目与内容取数桩 */
final class LlmsModelStub
{
    public $sorts = array();

    public $contents = array();

    /** @var array 记录每次取内容传入的条数上限 */
    public $requestedNums = array();

    public function getLlmsSorts()
    {
        return $this->sorts;
    }

    public function getLlmsSortContent($scode, $num)
    {
        $this->requestedNums[] = $num;
        $rows = isset($this->contents[$scode]) ? $this->contents[$scode] : array();
        return array_slice($rows, 0, (int) $num);
    }
}

/** 站点信息桩 */
final class LlmsSiteModelStub
{
    public $site;

    public function getSite()
    {
        return $this->site;
    }
}

/** 地址生成桩，产出与伪静态模式一致的站内路径 */
final class LlmsParserStub
{
    public function parserLink($type, $urlname, $pagetype, $scode, $sortfilename, $id = '', $contentfilename = '')
    {
        if ($pagetype === 'content') {
            return '/' . ($sortfilename ? $sortfilename : 'list_' . $scode) . '/' . ($contentfilename ? $contentfilename : $id) . '.html';
        }
        return '/' . ($sortfilename ? $sortfilename : $urlname . '_' . $scode) . '/';
    }
}

function llms_test_row(array $fields)
{
    $defaults = array(
        'id' => 0,
        'pcode' => '0',
        'scode' => '',
        'name' => '',
        'title' => '',
        'filename' => '',
        'sortfilename' => '',
        'outlink' => '',
        'description' => '',
        'type' => 2,
        'urlname' => 'list',
        'date' => '2026-01-01 00:00:00'
    );
    return (object) array_merge($defaults, $fields);
}

/** 以给定配置渲染一次 llms.txt */
function llms_test_render(array $configs, LlmsModelStub $model, LlmsSiteModelStub $siteModel)
{
    ConfigStub::reset();
    ConfigStub::set($configs);

    $ref = new ReflectionClass('app\\home\\controller\\LlmsController');
    $controller = $ref->newInstanceWithoutConstructor();

    foreach (array('model' => $model, 'siteModel' => $siteModel, 'parser' => new LlmsParserStub()) as $name => $value) {
        $prop = $ref->getProperty($name);
        if (PHP_VERSION_ID < 80100) {
            $prop->setAccessible(true);
        }
        $prop->setValue($controller, $value);
    }

    $render = $ref->getMethod('render');
    if (PHP_VERSION_ID < 80100) {
        $render->setAccessible(true);
    }

    return (string) $render->invoke($controller);
}

return TestAssert::runSuite(function () {
    $_SERVER['HTTP_HOST'] = 'www.example.com';
    $_SERVER['SERVER_PORT'] = '80';
    unset($_SERVER['HTTPS'], $_SERVER['REQUEST_SCHEME'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTP_X_CLIENT_SCHEME']);

    $siteModel = new LlmsSiteModelStub();
    $siteModel->site = (object) array(
        'title' => '示例站点',
        'description' => '示例站点的一句话描述'
    );

    $model = new LlmsModelStub();
    $model->sorts = array(
        llms_test_row(array('scode' => '1', 'name' => '关于我们', 'filename' => 'about', 'type' => 1, 'urlname' => 'about', 'description' => '公司介绍')),
        llms_test_row(array('scode' => '2', 'name' => '新闻中心', 'filename' => 'news', 'type' => 2, 'urlname' => 'news')),
        llms_test_row(array('pcode' => '2', 'scode' => '3', 'name' => '行业动态', 'filename' => 'trends', 'type' => 2, 'urlname' => 'news')),
        llms_test_row(array('scode' => '4', 'name' => '合作伙伴', 'type' => 2, 'outlink' => 'https://partner.example.com')),
        llms_test_row(array('scode' => '5', 'name' => '法律条款', 'filename' => 'legal', 'type' => 1, 'urlname' => 'about')),
        llms_test_row(array('scode' => '6', 'name' => '帮助中心', 'filename' => 'help', 'type' => 1, 'urlname' => 'about')),
        llms_test_row(array('pcode' => '6', 'scode' => '7', 'name' => '常见问题', 'filename' => 'faq', 'type' => 2, 'urlname' => 'news'))
    );
    $model->contents = array(
        '2' => array(
            llms_test_row(array('id' => 11, 'scode' => '2', 'sortfilename' => 'news', 'title' => '第一条新闻', 'description' => '第一条摘要')),
            llms_test_row(array('id' => 12, 'scode' => '2', 'sortfilename' => 'news', 'title' => '外链新闻', 'outlink' => 'https://other.example.com/a')),
            llms_test_row(array('id' => 13, 'scode' => '2', 'sortfilename' => 'news', 'title' => '第三条新闻'))
        ),
        '3' => array(
            llms_test_row(array('id' => 21, 'scode' => '3', 'sortfilename' => 'trends', 'title' => '子栏目文章'))
        ),
        '7' => array(
            llms_test_row(array('id' => 31, 'scode' => '7', 'sortfilename' => 'faq', 'title' => '如何退换货'))
        )
    );

    $baseConfig = array(
        'llms_open' => '1',
        'llms_inc_about' => '1',
        'llms_inc_list' => '1',
        'llms_inc_content' => '1',
        'llms_sort_num' => '20',
        'llms_desc_source' => 'description',
        'llms_desc_len' => '100',
        'llms_with_sitemap' => '0',
        'llms_intro' => '',
        'llms_optional_scodes' => '',
        'url_rule_type' => '2'
    );

    echo "=== 输出结构遵循规范书写顺序 ===\n";

    $out = llms_test_render($baseConfig, $model, $siteModel);
    $lines = explode("\n", $out);

    TestAssert::same('# 示例站点', $lines[0], '首行为 H1 站点名');
    TestAssert::same('', $lines[1], 'H1 后空行');
    TestAssert::same('> 示例站点的一句话描述', $lines[2], '引用式摘要紧随 H1');
    TestAssert::true(strpos($out, "\n## 关于我们\n") !== false, '存在栏目分段标题');
    TestAssert::true(
        strpos($out, '> 示例站点') < strpos($out, '## 关于我们'),
        '摘要排在分段之前'
    );
    TestAssert::same("\n", substr($out, -1), '输出以换行结尾');

    echo "=== 所有链接为绝对地址且格式合规 ===\n";

    TestAssert::contains($out, '- [关于我们](http://www.example.com/about/): 公司介绍', '单页栏目链接');
    TestAssert::contains($out, '- [新闻中心](http://www.example.com/news/)', '列表栏目链接');
    TestAssert::contains($out, '- [第一条新闻](http://www.example.com/news/11.html): 第一条摘要', '内容链接带说明');
    TestAssert::contains($out, '- [第三条新闻](http://www.example.com/news/13.html)', '描述为空时省略说明');
    TestAssert::contains($out, '- [子栏目文章](http://www.example.com/trends/21.html)', '子栏目内容归入父级分段');

    foreach ($lines as $line) {
        if (strpos($line, '- [') !== 0) {
            continue;
        }
        TestAssert::true(
            (bool) preg_match('#^- \[[^\]]+\]\(https?://[^)]+\)(: .+)?$#u', $line),
            '链接行符合 - [标题](绝对URL): 说明：' . $line
        );
    }

    echo "=== 外链栏目与外链内容不出现在输出中 ===\n";

    TestAssert::notContains($out, '合作伙伴', '外链栏目被跳过');
    TestAssert::notContains($out, 'partner.example.com', '外链栏目地址不出现');
    TestAssert::notContains($out, '外链新闻', '外链内容被跳过');
    TestAssert::notContains($out, 'other.example.com', '外链内容地址不出现');

    echo "=== 每栏目条数上限生效 ===\n";

    $model->requestedNums = array();
    $limited = llms_test_render(array_merge($baseConfig, array('llms_sort_num' => '1')), $model, $siteModel);
    TestAssert::contains($limited, '第一条新闻', '上限内的内容保留');
    TestAssert::notContains($limited, '第三条新闻', '超出上限的内容不输出');
    TestAssert::same(array(1, 1, 1), $model->requestedNums, '上限透传到取数层');

    $model->requestedNums = array();
    llms_test_render(array_merge($baseConfig, array('llms_sort_num' => '0')), $model, $siteModel);
    TestAssert::same(array(20, 20, 20), $model->requestedNums, '非法条数回落默认20');

    $model->requestedNums = array();
    llms_test_render(array_merge($baseConfig, array('llms_sort_num' => '9999')), $model, $siteModel);
    TestAssert::same(array(500, 500, 500), $model->requestedNums, '超大条数收敛到500');

    echo "=== 输出范围开关分别生效 ===\n";

    $noAbout = llms_test_render(array_merge($baseConfig, array('llms_inc_about' => '0')), $model, $siteModel);
    TestAssert::notContains($noAbout, '- [关于我们]', '关闭单页栏目后不输出');
    TestAssert::contains($noAbout, '- [新闻中心]', '关闭单页不影响列表栏目');

    $noList = llms_test_render(array_merge($baseConfig, array('llms_inc_list' => '0')), $model, $siteModel);
    TestAssert::notContains($noList, '- [新闻中心]', '关闭列表栏目后不输出栏目自身');
    TestAssert::contains($noList, '- [第一条新闻]', '关闭列表栏目仍可输出其内容');

    $noContent = llms_test_render(array_merge($baseConfig, array('llms_inc_content' => '0')), $model, $siteModel);
    TestAssert::notContains($noContent, '第一条新闻', '关闭内容后不输出文章');
    TestAssert::contains($noContent, '- [新闻中心]', '关闭内容不影响栏目链接');

    echo "=== 说明文字来源与截断长度 ===\n";

    $noDesc = llms_test_render(array_merge($baseConfig, array('llms_desc_source' => 'none')), $model, $siteModel);
    TestAssert::contains($noDesc, '- [关于我们](http://www.example.com/about/)', '关闭说明后仍输出链接');
    TestAssert::notContains($noDesc, '公司介绍', '关闭说明后不输出描述');

    $shortDesc = llms_test_render(array_merge($baseConfig, array('llms_desc_len' => '2')), $model, $siteModel);
    TestAssert::contains($shortDesc, '- [关于我们](http://www.example.com/about/): 公司…', '说明按配置长度截断');

    // 后台允许配到500，链接行内不得再压到更短的上限
    $longModel = new LlmsModelStub();
    $longModel->sorts = array(
        llms_test_row(array('scode' => '1', 'name' => '关于我们', 'filename' => 'about', 'type' => 1, 'urlname' => 'about', 'description' => str_repeat('阿', 400)))
    );
    $longDesc = llms_test_render(array_merge($baseConfig, array('llms_desc_len' => '300')), $longModel, $siteModel);
    TestAssert::contains($longDesc, ': ' . str_repeat('阿', 300) . '…', '说明长度可超过200字');

    echo "=== Optional 段语义与站点地图 ===\n";

    $optional = llms_test_render(array_merge($baseConfig, array(
        'llms_optional_scodes' => '5',
        'llms_with_sitemap' => '1'
    )), $model, $siteModel);

    TestAssert::contains($optional, "\n## Optional\n", '存在 Optional 分段');
    TestAssert::contains($optional, '- [法律条款](http://www.example.com/legal/)', '选中栏目移入 Optional 段');
    TestAssert::contains($optional, '- [Sitemap](http://www.example.com/sitemap.xml)', '附带站点地图链接');
    TestAssert::true(
        strpos($optional, '## Optional') > strpos($optional, '## 新闻中心'),
        'Optional 段排在常规分段之后'
    );
    TestAssert::same(
        1,
        substr_count($optional, '- [法律条款]'),
        'Optional 栏目不再重复出现在常规分段'
    );
    TestAssert::notContains($out, '## Optional', '未选栏目且未附地图时不输出空 Optional 段');

    echo "=== Optional 栏目的子树整体随之移入 ===\n";

    $withSon = llms_test_render(array_merge($baseConfig, array('llms_optional_scodes' => '6')), $model, $siteModel);
    $optionalPos = strpos($withSon, '## Optional');
    TestAssert::false($optionalPos === false, '存在 Optional 分段');

    $mainPart = substr($withSon, 0, $optionalPos);
    $optionalPart = substr($withSon, $optionalPos);

    TestAssert::contains($optionalPart, '- [帮助中心](http://www.example.com/help/)', '选中栏目本身在 Optional 段');
    // 修复前子栏目在主分段被跳过、又不会出现在 Optional 段，导致整棵子树静默消失
    TestAssert::contains($optionalPart, '- [常见问题](http://www.example.com/faq/)', '子栏目随父栏目移入 Optional 段');
    TestAssert::contains($optionalPart, '- [如何退换货](http://www.example.com/faq/31.html)', '子栏目下的内容同样保留');
    TestAssert::notContains($mainPart, '常见问题', '子栏目不再留在常规分段');
    TestAssert::notContains($mainPart, '如何退换货', '子栏目内容不再留在常规分段');
    TestAssert::same(1, substr_count($withSon, '- [常见问题]'), '子栏目全文只出现一次');
    TestAssert::same(1, substr_count($withSon, '- [如何退换货]'), '子栏目内容全文只出现一次');

    $bothChecked = llms_test_render(array_merge($baseConfig, array('llms_optional_scodes' => '6,7')), $model, $siteModel);
    TestAssert::same(1, substr_count($bothChecked, '- [常见问题]'), '父子同时勾选时不重复展开');
    TestAssert::same(1, substr_count($bothChecked, '- [如何退换货]'), '父子同时勾选时内容不重复');

    // 只勾子栏目：父栏目留在常规分段，子栏目独立移入 Optional
    $sonOnly = llms_test_render(array_merge($baseConfig, array('llms_optional_scodes' => '7')), $model, $siteModel);
    $sonOptionalPos = strpos($sonOnly, '## Optional');
    TestAssert::false($sonOptionalPos === false, '只勾子栏目时仍生成 Optional 段');
    TestAssert::contains(substr($sonOnly, 0, $sonOptionalPos), '- [帮助中心]', '未勾选的父栏目留在常规分段');
    TestAssert::contains(substr($sonOnly, $sonOptionalPos), '- [常见问题]', '被勾选的子栏目移入 Optional 段');
    TestAssert::same(1, substr_count($sonOnly, '- [常见问题]'), '子栏目不重复出现');

    echo "=== 补充说明与站点信息缺失兜底 ===\n";

    $intro = llms_test_render(array_merge($baseConfig, array('llms_intro' => '这是站点定位说明')), $model, $siteModel);
    $introLines = explode("\n", $intro);
    TestAssert::same('这是站点定位说明', $introLines[4], '补充说明位于摘要与分段之间');
    TestAssert::true(
        strpos($intro, '这是站点定位说明') < strpos($intro, '## 关于我们'),
        '补充说明排在分段之前'
    );

    $emptySite = new LlmsSiteModelStub();
    $emptySite->site = (object) array('title' => '', 'description' => '');
    $fallback = llms_test_render($baseConfig, $model, $emptySite);
    TestAssert::same('# www.example.com', explode("\n", $fallback)[0], '站点标题为空时回退域名');
    TestAssert::notContains($fallback, '> ', '站点描述为空时不输出空引用行');

    $noSite = new LlmsSiteModelStub();
    $noSite->site = false;
    $noRow = llms_test_render($baseConfig, $model, $noSite);
    TestAssert::same('# www.example.com', explode("\n", $noRow)[0], '站点行缺失时不报错');

    ConfigStub::reset();
});
