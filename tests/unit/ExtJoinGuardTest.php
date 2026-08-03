<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/home/model/ParserModel.php hasExtFieldReference()
 *
 * 回归目标：
 *   #77 ay_content_ext 条件 JOIN 未覆盖 order/filter/select 三条 ext 发出点
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

defined('CONF_PATH') ?: define('CONF_PATH', ROOT_PATH . 'config');
defined('RUN_PATH') ?: define('RUN_PATH', ROOT_PATH . 'runtime');
require CORE_PATH . '/basic/Config.php';
require CORE_PATH . '/basic/Model.php';
require APP_PATH . '/home/model/ParserModel.php';

use app\home\model\ParserModel;

/**
 * 通过反射调用私有的 hasExtFieldReference()，跳过构造函数的 DB 依赖
 */
function invoke_has_ext_reference(): bool
{
    static $method = null;
    static $model = null;

    if ($method === null) {
        $refClass = new ReflectionClass(ParserModel::class);
        $model = $refClass->newInstanceWithoutConstructor();
        $method = $refClass->getMethod('hasExtFieldReference');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
    }

    return (bool) $method->invokeArgs($model, func_get_args());
}

return TestAssert::runSuite(function () {
    echo "=== #77 order=ext_* 触发 JOIN ===\n";

    TestAssert::true(
        invoke_has_ext_reference('e.ext_price DESC,a.id DESC', array(), array(), array()),
        'order 含 e.ext_price'
    );
    TestAssert::true(
        invoke_has_ext_reference('ext_price DESC', array(), array(), array()),
        'order 含未限定 ext_price'
    );

    echo "=== #77 filter 原始子句触发 JOIN（#75 修复后的形态）===\n";

    $extClause = build_extfield_where('ext_color', '红色', false);
    TestAssert::true(
        invoke_has_ext_reference('a.id DESC', array($extClause), array(), array()),
        'filter 数组含 ext 原始子句'
    );

    echo "=== #77 GET 式 ext 筛选触发 JOIN（#19 既有形态）===\n";

    TestAssert::true(
        invoke_has_ext_reference('a.id DESC', array(), array(), array($extClause)),
        'select 数组含 ext 原始子句（数值索引）'
    );
    TestAssert::true(
        invoke_has_ext_reference('a.id DESC', array(), array(), array('ext_color' => '红色')),
        'select 数组以 ext 字段为键'
    );

    echo "=== SQL 字面量中的 ext_* 不触发 JOIN ===\n";

    TestAssert::false(
        invoke_has_ext_reference("a.title='ext_price' DESC", array(), array(), array()),
        '排序中的单引号字面量不触发'
    );
    TestAssert::false(
        invoke_has_ext_reference('a.title="ext_price" DESC', array(), array(), array()),
        '排序中的双引号字面量不触发'
    );
    TestAssert::false(
        invoke_has_ext_reference('a.id DESC', array("a.title='ext_color'"), array(), array()),
        '原始条件的字符串字面量不触发'
    );

    echo "=== #77 tags 条件同样纳入检查 ===\n";

    TestAssert::true(
        invoke_has_ext_reference('a.id DESC', array(), array("ext_type='A'"), array()),
        'tags 数组含 ext 子句'
    );

    echo "=== 无 ext 引用时不新增 JOIN（避免无谓开销）===\n";

    TestAssert::false(
        invoke_has_ext_reference('a.id DESC', array(), array(), array()),
        '纯 a.* 排序: 不触发'
    );
    TestAssert::false(
        invoke_has_ext_reference(
            'a.istop DESC,a.date DESC',
            array("a.title='php'"),
            array("a.tags like '%x%'"),
            array('a.title' => 'abc')
        ),
        '全部为 a.* 条件: 不触发'
    );
    TestAssert::false(
        invoke_has_ext_reference('RAND()', array(), array(), array()),
        '随机排序: 不触发'
    );

    echo "=== 词边界：不得把非扩展字段误判为扩展字段 ===\n";

    TestAssert::false(
        invoke_has_ext_reference('a.myext_foo DESC', array(), array(), array()),
        'myext_foo 不应触发'
    );
    TestAssert::false(
        invoke_has_ext_reference('a.context DESC', array(), array(), array()),
        'context 不应触发'
    );
    TestAssert::false(
        invoke_has_ext_reference('a.id DESC', array(), array(), array('subext_a' => '1')),
        'subext_a 不应触发'
    );
    TestAssert::false(
        invoke_has_ext_reference('a.id DESC', array(), array(), array('ext_' => '1')),
        '不完整的 ext_ 不应触发'
    );

    echo "=== 非字符串与嵌套结构安全处理 ===\n";

    TestAssert::false(invoke_has_ext_reference(null, array(), array(), array()), 'null 安全');
    TestAssert::false(invoke_has_ext_reference('', array(), array(), array(1, 2, 3)), '整数元素安全');
    TestAssert::true(
        invoke_has_ext_reference('', array(), array(), array(array("ext_color='红'"))),
        '嵌套数组仍被检出'
    );
});
