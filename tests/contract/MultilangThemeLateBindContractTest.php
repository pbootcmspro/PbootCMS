<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers 主题单次晚绑定不变量（issue #121）
 *
 * 语言在路由取到栏目/内容后才终态（IndexController::switchLg），因此主题必须
 * 延后到首次渲染前按最终语言绑定，且每请求只绑定一次；View.php 不得被改动。
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== View.php 保持原样（未引入 overwrite 重复注入） ===\n";

    $vsrc = file_get_contents(CORE_PATH . '/view/View.php');
    TestAssert::false($vsrc === false, 'View 源码可读');
    TestAssert::contains($vsrc, 'public function assign($var, $value)', 'View::assign 保持两参数原型');
    TestAssert::notContains($vsrc, '$overwrite', 'View 未引入 overwrite 参数');
    TestAssert::contains($vsrc, '出现重复注入', 'View 仍拒绝重复注入');

    echo "=== core 提供渲染前置钩子，且不反向依赖应用层 ===\n";

    $csrc = file_get_contents(CORE_PATH . '/basic/Controller.php');
    TestAssert::false($csrc === false, 'Controller 源码可读');
    TestAssert::contains($csrc, 'protected function beforeParser($file)', 'Controller 提供 beforeParser 钩子');
    TestAssert::contains($csrc, 'function setTheme($themeName)', 'setTheme 恢复单参数原型');
    TestAssert::notContains($csrc, '$overwrite', 'Controller 未保留 overwrite');
    TestAssert::notContains($csrc, 'get_theme(', 'core 不直接调用应用层 get_theme（避免分层倒置）');

    foreach (array('display', 'parser') as $fn) {
        $pos = strpos($csrc, 'final protected function ' . $fn . '($file)');
        TestAssert::false($pos === false, $fn . ' 存在');
        $body = substr($csrc, $pos, 260);
        $posHook = strpos($body, '$this->beforeParser($file)');
        $posParse = strpos($body, '$view->parser($file)');
        TestAssert::false($posHook === false, $fn . ' 调用 beforeParser');
        TestAssert::false($posParse === false, $fn . ' 调用 view->parser');
        TestAssert::true($posHook < $posParse, $fn . ' 钩子早于 view->parser');
    }

    echo "=== HomeController 只记录 wap 后缀，不再绑定主题 ===\n";

    $hsrc = file_get_contents(APP_PATH . '/common/HomeController.php');
    TestAssert::false($hsrc === false, 'HomeController 源码可读');
    TestAssert::notContains($hsrc, 'setTheme(', 'HomeController 不再调用 setTheme');
    TestAssert::contains($hsrc, 'private static $themeSuffix', 'HomeController 记录 wap 后缀');
    TestAssert::contains($hsrc, 'function getThemeSuffix()', '提供后缀读取接口');
    TestAssert::contains($hsrc, "self::\$themeSuffix = '/wap'", 'wap 分支记录后缀');
    TestAssert::contains($hsrc, "header('Location:'", 'wap 域名跳转仍在原处保留');

    echo "=== 晚绑定实现：单次、按最终语言、不重复 wap 判定 ===\n";

    $bsrc = file_get_contents(APP_PATH . '/common/HomeBaseController.php');
    TestAssert::false($bsrc === false, 'HomeBaseController 源码可读');
    TestAssert::contains($bsrc, 'protected function beforeParser($file)', '重写 beforeParser');
    TestAssert::contains($bsrc, "\$this->getVar('theme') !== null", '已绑定则跳过，保证每请求仅一次');
    TestAssert::contains($bsrc, 'setTheme(get_theme() . HomeController::getThemeSuffix())', '按最终语言+已决后缀绑定一次');
    TestAssert::notContains($bsrc, 'is_mobile(', '不重复 wap 判定');
    TestAssert::notContains($bsrc, 'wap_domain', '不重复 wap 域名判定');

    echo "=== 全部前台渲染控制器继承晚绑定基类 ===\n";

    foreach (array('IndexController', 'MemberController', 'SearchController', 'TagController', 'CommentController') as $c) {
        $src = file_get_contents(APP_PATH . '/home/controller/' . $c . '.php');
        TestAssert::false($src === false, $c . ' 源码可读');
        TestAssert::contains($src, 'class ' . $c . ' extends HomeBaseController', $c . ' 继承 HomeBaseController');
        TestAssert::contains($src, 'use app\common\HomeBaseController;', $c . ' 引入基类');
    }

    echo "=== switchLg 只切语言，不再碰主题 ===\n";

    $isrc = file_get_contents(APP_PATH . '/home/controller/IndexController.php');
    $posSwitch = strpos($isrc, 'private function switchLg(');
    $posIndexPage = strpos($isrc, 'private function getIndexPage(');
    TestAssert::false($posSwitch === false, 'switchLg 存在');
    $body = substr($isrc, $posSwitch, $posIndexPage - $posSwitch);

    TestAssert::contains($body, "cookie('lg', \$acode)", 'switchLg 切换语言');
    TestAssert::contains($body, "Config::get('lgautosw') === '0'", '保留 lgautosw 开关语义');
    TestAssert::contains($body, "isset(\$lgs[\$acode]['theme'])", '校验目标语言存在');
    TestAssert::notContains($body, 'setTheme', 'switchLg 不再绑定主题');
    TestAssert::notContains($body, 'getVar', 'switchLg 不再读取主题');
    TestAssert::notContains($body, 'header(', 'switchLg 不重定向');

    TestAssert::same(3, substr_count($isrc, '$this->switchLg('), '三处切换点');
    foreach (array('getListPage', 'getContentPage', 'getAboutPage') as $fn) {
        $posFn = strpos($isrc, 'private function ' . $fn . '(');
        $end = strpos($isrc, 'private function ', $posFn + 20);
        $fnBody = $end === false ? substr($isrc, $posFn) : substr($isrc, $posFn, $end - $posFn);
        $posCall = strpos($fnBody, '$this->switchLg(');
        $posParser = strpos($fnBody, 'parent::parser(');
        TestAssert::false($posCall === false, $fn . ' 调用 switchLg');
        TestAssert::true($posCall < $posParser, $fn . ' 语言切换早于渲染');
    }

    echo "=== get_lg/get_theme 不产生空主题 ===\n";

    $fsrc = file_get_contents(APP_PATH . '/common/function.php');
    TestAssert::contains($fsrc, 'is_array($lgs) && ! isset($lgs[$lg])', 'get_lg 校验语言存在');
    $posTheme = strpos($fsrc, 'function get_theme()');
    $posEnd = strpos($fsrc, "\nfunction ", $posTheme + 10);
    $themeBody = substr($fsrc, $posTheme, $posEnd - $posTheme);
    TestAssert::contains($themeBody, "isset(\$lgs[\$lg]['theme'])", 'get_theme 先判存在');
    TestAssert::contains($themeBody, "return 'default';", 'get_theme 有兜底，永不返回 null');
});
