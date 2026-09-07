<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers long2ip_safe()
 *
 * fix #210 回归：读取端兜底，IP字段的历史脏数据不再让列表页抛TypeError
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== 正常数值：按long2ip还原 ===\n";
    TestAssert::same('127.0.0.1', long2ip_safe('2130706433'), '数值字符串');
    TestAssert::same('127.0.0.1', long2ip_safe(2130706433), '整数');
    TestAssert::same('0.0.0.0', long2ip_safe('0'), '字符串零');
    TestAssert::same('0.0.0.0', long2ip_safe(0), '整数零');
    if (PHP_INT_SIZE >= 8) {
        TestAssert::same('255.255.255.255', long2ip_safe('4294967295'), '64位下的IP上界');
    }

    echo "=== 空值：归零而非抛异常 ===\n";
    TestAssert::same('0.0.0.0', long2ip_safe(''), '空串（ip2long失败时的入库值）');
    TestAssert::same('0.0.0.0', long2ip_safe(null), 'null');

    echo "=== 非数值文本：原样返回 ===\n";
    TestAssert::same('192.168.1.1', long2ip_safe('192.168.1.1'), '点分文本');
    TestAssert::same('unknown', long2ip_safe('unknown'), '其它文本');

    echo "=== 任何输入都不抛TypeError ===\n";
    $inputs = array('', '0', '2130706433', '192.168.1.1', 'unknown', null, 0, 2130706433);
    $thrown = 0;
    foreach ($inputs as $input) {
        try {
            long2ip_safe($input);
        } catch (Throwable $e) {
            $thrown ++;
        }
    }
    TestAssert::same(0, $thrown, '全部输入均未抛出异常');
});
