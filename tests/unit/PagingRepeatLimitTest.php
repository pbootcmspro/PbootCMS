<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers \core\view\Paging::limit()
 *
 * fix #286 回归：同一请求多次分页时不重复定义分页常量、不重复注入分页模板变量，
 * 且常量与模板变量来自同一次（首次）分页
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once CORE_PATH . '/function/file.php';
require_once CORE_PATH . '/view/View.php';
require_once CORE_PATH . '/view/Paging.php';
require_once CORE_PATH . '/basic/Model.php';

if (! defined('RUN_PATH')) {
    define('RUN_PATH', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot_paging_test_' . getmypid());
}
if (! defined('APP_VIEW_PATH')) {
    define('APP_VIEW_PATH', RUN_PATH . '/view');
}
if (! defined('URL')) {
    define('URL', '/list/');
}
if (! defined('M')) {
    define('M', 'home');
}
if (! defined('C')) {
    define('C', 'List');
}
if (! defined('SITE_INDEX_DIR')) {
    define('SITE_INDEX_DIR', '');
}

return TestAssert::runSuite(function () {
    error_reporting(E_ALL);
    ConfigStub::reset();
    ConfigStub::set(array(
        'pagenum' => 5
    ));
    $_GET = array(
        'page' => '2'
    );
    $_SERVER['QUERY_STRING'] = 'page=2';

    $paging = \core\view\Paging::getInstance();

    echo "=== 连续两次 limit() 无 warning、无重复注入 ===\n";
    $paging->page = 1;
    $paging->pageSize = 10;
    $paging->start = 1;
    $limitA = $paging->limit(100, true);

    $paging->page = 1;
    $paging->pageSize = 3;
    $paging->start = 1;
    $limitB = $paging->limit(7, true);

    echo "=== 各列表 LIMIT 仍按自身参数计算 ===\n";
    TestAssert::same('10,10', $limitA, '列表A LIMIT');
    TestAssert::same('3,3', $limitB, '列表B LIMIT');

    echo "=== 常量与模板变量来自首次分页 ===\n";
    TestAssert::same(2, (int) PAGE, 'PAGE');
    TestAssert::same(10.0, (float) PAGECOUNT, 'PAGECOUNT');
    TestAssert::same(100, ROWTOTAL, 'ROWTOTAL');
    TestAssert::same(10, PAGESIZE, 'PAGESIZE');
    TestAssert::same('共100条 当前2/10页', get_var('pagestatus'), 'pagestatus');
    TestAssert::same(PAGECOUNT, get_var('pagecount'), 'pagecount 与常量一致');
    TestAssert::contains((string) get_var('pagebar'), '共100条 当前2/10页', 'pagebar');

    echo "=== 未传 start 时不残留上一列表的起始偏移 ===\n";
    $model = new \core\basic\Model();
    $model->page(1, 5, 3);
    TestAssert::same(3, $paging->start, '带 start=3 的列表');
    // 上一列表未执行 limit()（如分组查询结果为空）时，下一列表仍须复位
    $model->page(1, 5);
    TestAssert::same(1, $paging->start, '随后未传 start 的列表');
    TestAssert::same('5,5', $paging->limit(50, true), '随后未传 start 的列表 LIMIT');
    $model->page(1, 5, 3);
    $model->page();
    TestAssert::same(1, $paging->start, '单参数 page() 同样复位');
});
