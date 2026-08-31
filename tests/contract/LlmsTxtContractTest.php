<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers /llms.txt 路由、响应头、后台配置入口与在线升级兼容性
 *
 * Issue #109：守护几条不易被单测覆盖、却一改就坏的不变量：
 *  1. 路由与非路由兜底必须同时存在，否则伪静态可用而兼容模式 404
 *  2. 开关判定必须早于 header()，否则关闭时会输出 text/plain 的 404 页面
 *  3. 后台 tab 标题与面板数量必须一一对应，layui 按下标配对
 *  4. 实现不得落在在线升级会过滤的目录，且必须兼容 PHP 7.0
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== 路由与非路由兜底同时注册 ===\n";

    $route = file_get_contents(APP_PATH . '/common/route.php');
    TestAssert::false($route === false, 'route.php readable');
    TestAssert::contains($route, "'home/llms.txt' => 'home/Llms/index'", '注册 /llms.txt 路由');

    $index = file_get_contents(APP_PATH . '/home/controller/IndexController.php');
    TestAssert::false($index === false, 'IndexController readable');
    TestAssert::contains($index, "case 'llms':", '兜底分派存在 llms');
    // 兼容模式下 parse_str 会把 llms.txt 的点转成下划线，缺这条则 /?llms.txt 走不通
    TestAssert::contains($index, "case 'llms_txt':", '兜底分派存在 llms_txt');
    TestAssert::contains($index, 'new LlmsController()', '兜底分派指向 LlmsController');

    echo "=== 响应头与开关顺序 ===\n";

    $llms = file_get_contents(APP_PATH . '/home/controller/LlmsController.php');
    TestAssert::false($llms === false, 'LlmsController readable');
    TestAssert::contains($llms, "header('Content-type: text/plain; charset=utf-8')", '输出纯文本内容类型');
    TestAssert::contains($llms, "header('X-Robots-Tag: noindex, nofollow')", '该页自身不参与收录');
    // 正文含站点内容，缺这条则老旧客户端可能按内容嗅探改判类型
    TestAssert::contains($llms, "header('X-Content-Type-Options: nosniff')", '禁止内容类型嗅探');

    $pos404 = strpos($llms, "_404(");
    $posHeader = strpos($llms, "header('Content-type");
    TestAssert::false($pos404 === false, '关闭时走 _404');
    TestAssert::false($posHeader === false, '存在内容类型头');
    TestAssert::true($pos404 < $posHeader, '开关判定早于发送内容类型头');
    TestAssert::contains($llms, "config('llms_open')", '总开关取自 ay_config');

    echo "=== 取数走 ORM 且外链字段已选出 ===\n";

    $model = file_get_contents(APP_PATH . '/home/model/SitemapModel.php');
    TestAssert::false($model === false, 'SitemapModel readable');

    $methodBody = function ($name) use ($model) {
        $start = strpos($model, 'public function ' . $name . '(');
        if ($start === false) {
            return '';
        }
        $next = strpos($model, 'public function ', $start + 1);
        return $next === false ? substr($model, $start) : substr($model, $start, $next - $start);
    };

    // 缺少 outlink 字段时控制器的外链跳过逻辑会静默失效（sitemap 曾长期如此）
    foreach (array('getSortContent', 'getLlmsSortContent', 'getLlmsSorts') as $method) {
        $body = $methodBody($method);
        TestAssert::false($body === '', $method . ' 存在');
        TestAssert::contains($body, "'a.outlink'", $method . ' 选出 outlink 字段');
    }

    $llmsContent = $methodBody('getLlmsSortContent');
    TestAssert::contains($llmsContent, '->limit($num)', 'llms 内容取数带条数上限');
    TestAssert::contains($llmsContent, "'a.scode' => \$scode", 'llms 取数走参数绑定');
    TestAssert::notContains($llmsContent, "a.scode='", 'llms 取数不拼接 scode');
    TestAssert::contains($methodBody('getLlmsSorts'), "'a.acode' => get_lg()", '栏目按当前语言过滤');

    echo "=== 后台配置入口 ===\n";

    $config = file_get_contents(APP_PATH . '/admin/controller/system/ConfigController.php');
    TestAssert::false($config === false, 'ConfigController readable');
    TestAssert::contains($config, "case 'llms':", '存在 llms 提交分支');
    TestAssert::contains($config, "get_tab('t13')", '提交后回到 llms.txt 面板');
    TestAssert::contains($config, "\$_POST[\$llms_scope_key] = '0';", '未勾选的范围复选框补 0');
    TestAssert::contains($config, 'mergeLlmsOptionalScodes(', 'Optional 段栏目做跨区域合并');
    // 超限须拒绝保存：尾部截断会先丢掉当前区域勾选，且页面仍提示成功
    TestAssert::contains($config, "strlen(\$merged) > 200", 'Optional 段超长在合并时拦截');
    TestAssert::contains($config, 'Optional段栏目数量超出配置项容量限制', 'Optional 段超长提示用户');
    TestAssert::notContains($config, "strrpos(\$value, ',')", 'Optional 段不得静默截断');
    // 写库路径必须用只截断不解码的版本；换成 llms_plain_text 会把转义态解开后入库，
    // 后台 textarea 回显随即失去转义保护
    TestAssert::contains($config, 'llms_truncate_intro($value, 190)', '补充说明按转义态收敛');
    TestAssert::notContains($config, 'llms_plain_text($value', '写库路径不得解码配置值');
    TestAssert::contains($config, 'name="llms_optional_sorts[]"', 'Optional 段栏目渲染为复选框数组');

    $view = file_get_contents(APP_PATH . '/admin/view/default/system/config.html');
    TestAssert::false($view === false, 'config.html readable');
    TestAssert::contains($view, 'lay-id="t13"', '存在 llms.txt 标签页');
    TestAssert::contains($view, 'name="submit" value="llms"', '存在 llms 提交按钮');
    TestAssert::contains($view, '{$llms_sort_checkbox}', 'Optional 段栏目复选框已挂载');

    // layui 按下标把标题与面板配对，数量不等会导致整块配置错位
    TestAssert::same(
        preg_match_all('/<li\s[^>]*lay-id="t\d+"/', $view),
        preg_match_all('/class="layui-tab-item/', $view),
        'tab 标题数与面板数一致'
    );

    $llmsFormPos = strpos($view, 'name="submit" value="llms"');
    $llmsFormStart = strrpos(substr($view, 0, $llmsFormPos), '<form');
    $llmsForm = substr($view, $llmsFormStart, $llmsFormPos - $llmsFormStart);
    TestAssert::contains($llmsForm, 'name="formcheck" value="{$formcheck}"', 'llms 表单带 formcheck 校验');

    echo "=== 在线升级兼容：不新增被过滤目录下的运行必需文件 ===\n";

    $added = array(
        'apps/home/controller/LlmsController.php',
        'apps/common/function.php',
        'apps/common/route.php',
        'apps/home/model/SitemapModel.php',
        'apps/admin/controller/system/ConfigController.php',
        'apps/admin/view/default/system/config.html'
    );
    foreach ($added as $file) {
        TestAssert::true(is_file(ROOT_PATH . $file), $file . ' 存在');
        foreach (array('config/', 'data/', 'static/', 'template/') as $filtered) {
            TestAssert::false(strpos($file, $filtered) === 0, $file . ' 不在被过滤目录 ' . $filtered);
        }
    }

    echo "=== PHP 7.0 语法兼容 ===\n";

    $forbidden = array(
        '箭头函数' => '/[^\w$>]fn\s*\([^)]*\)\s*=>/',
        '空合并赋值' => '/\?\?=/',
        'match表达式' => '/[^\w$>]match\s*\(/',
        '可空链调用' => '/\?->/',
        '注解语法' => '/(^|\s)#\[/m',
        '数组展开' => '/\[\s*\.\.\.\$/',
        '类型化属性' => '/(public|protected|private)\s+\??(int|float|string|bool|array|iterable|object|self|static)\s+\$/',
        '联合返回类型' => '/function\s+\w+\s*\([^)]*\)\s*:\s*\??\w+\s*\|/'
    );
    $scan = array(
        'apps/home/controller/LlmsController.php',
        'apps/home/model/SitemapModel.php'
    );
    foreach ($scan as $file) {
        $src = file_get_contents(ROOT_PATH . $file);
        TestAssert::false($src === false, $file . ' readable');
        foreach ($forbidden as $name => $pattern) {
            TestAssert::same(0, preg_match($pattern, $src), $file . ' 未使用' . $name);
        }
    }
});
