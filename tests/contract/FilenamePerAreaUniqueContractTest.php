<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers apps/admin/model/content/ContentSortModel.php checkFilename()
 * @covers apps/admin/model/content/ContentModel.php checkFilename()
 * @covers apps/admin/controller/content/ContentSortController.php
 * @covers apps/admin/controller/content/ContentController.php
 *
 * URL 名称查重契约：按数据区域查重（不同语言允许同名），重复时报错而非静默改名。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    $files = array(
        'ContentSortModel' => APP_PATH . '/admin/model/content/ContentSortModel.php',
        'ContentModel' => APP_PATH . '/admin/model/content/ContentModel.php',
        'ContentSortController' => APP_PATH . '/admin/controller/content/ContentSortController.php',
        'ContentController' => APP_PATH . '/admin/controller/content/ContentController.php'
    );
    $src = array();
    foreach ($files as $name => $path) {
        $body = file_get_contents($path);
        TestAssert::false($body === false, "$name readable");
        $src[$name] = (string) $body;
    }

    echo "=== 查重限定在当前数据区域内 ===\n";
    foreach (array('ContentSortModel', 'ContentModel') as $name) {
        $pos = strpos($src[$name], 'public function checkFilename');
        TestAssert::true($pos !== false, "$name 有 checkFilename");
        $body = substr($src[$name], $pos, 400);
        TestAssert::contains($body, "'acode' => session('acode')", "$name 查重带 acode");
        TestAssert::contains($body, "'filename' => \$filename", "$name filename 参数绑定");
        TestAssert::notContains($body, "where(\"filename='\$filename'\")", "$name 不再字面量拼接 filename");
    }

    echo "=== 重复时报错，不静默追加随机后缀 ===\n";
    foreach (array('ContentSortController', 'ContentController') as $name) {
        TestAssert::same(0, substr_count($src[$name], 'mt_rand'), "$name 无随机后缀");
        TestAssert::notContains($src[$name], 'while ($this->model->checkFilename', "$name 无改名循环");
        // 新增与修改两条路径都要拦
        TestAssert::same(2, substr_count($src[$name], '$this->model->checkFilename('), "$name 新增与修改各查重一次");
    }

    echo "=== 修改路径排除自身 ===\n";
    TestAssert::contains($src['ContentSortController'], "checkFilename(\$filename, \"scode<>'\$scode'\")", '栏目修改排除自身 scode');
    TestAssert::contains($src['ContentController'], 'checkFilename($filename, "id<>$id")', '内容修改排除自身 id');

    echo "=== 报错文案提示语言维度 ===\n";
    TestAssert::contains($src['ContentSortController'], '本语言下其他栏目', '栏目报错文案');
    TestAssert::contains($src['ContentController'], '本语言下其他内容', '内容报错文案');

    echo "=== 栏目与内容之间交叉查重 ===\n";
    // 路由先试 getSort() 再试 getContent()，栏目与内容撞名会让该内容永久访问不到
    $pos = strpos($src['ContentSortModel'], 'public function checkContentFilename');
    TestAssert::true($pos !== false, 'ContentSortModel 有 checkContentFilename');
    $body = substr($src['ContentSortModel'], $pos, 400);
    TestAssert::contains($body, "table('ay_content')", '栏目侧查 ay_content');
    TestAssert::contains($body, "'acode' => session('acode')", '栏目侧交叉查重带 acode');

    $pos = strpos($src['ContentModel'], 'public function checkSortFilename');
    TestAssert::true($pos !== false, 'ContentModel 有 checkSortFilename');
    $body = substr($src['ContentModel'], $pos, 400);
    TestAssert::contains($body, "table('ay_content_sort')", '内容侧查 ay_content_sort');
    TestAssert::contains($body, "'acode' => session('acode')", '内容侧交叉查重带 acode');

    // 新增与修改两条路径都要拦
    TestAssert::same(2, substr_count($src['ContentSortController'], '$this->model->checkContentFilename('), '栏目新增与修改各交叉查重一次');
    TestAssert::same(2, substr_count($src['ContentController'], '$this->model->checkSortFilename('), '内容新增与修改各交叉查重一次');
    TestAssert::contains($src['ContentSortController'], '已被本语言下某篇内容使用', '栏目交叉撞名文案');
    TestAssert::contains($src['ContentController'], '已被本语言下某个栏目使用', '内容交叉撞名文案');

    echo "=== 路由侧优先当前语言 ===\n";
    $parser = file_get_contents(APP_PATH . '/home/model/ParserModel.php');
    TestAssert::false($parser === false, 'ParserModel readable');
    TestAssert::contains((string) $parser, "'a.acode' => get_lg()", 'getSort 先按当前语言查');
    TestAssert::contains((string) $parser, 'private function findContentRow', 'getContent 抽出按语言取行');
    TestAssert::contains((string) $parser, '$this->findContentRow($field, $join, $where, get_lg())', 'getContent 先按当前语言查');
    TestAssert::contains((string) $parser, "\$this->findContentRow(\$field, \$join, \$where, '')", 'getContent 跨语言兜底');
});
