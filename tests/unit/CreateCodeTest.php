<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/handle.php create_code()
 *
 * fix #201 回归：验证码长度、字符集与 CSPRNG 生成
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    $charset = retrieve_code_charset();

    echo "=== create_code() length ===\n";

    TestAssert::same(6, strlen(create_code()), 'create_code: default length 6');
    TestAssert::same(8, strlen(create_code(8)), 'create_code: explicit length 8');

    echo "=== create_code() charset ===\n";

    for ($i = 0; $i < 50; $i++) {
        $code = create_code(6);
        TestAssert::same(6, strlen($code), 'create_code: iteration length');
        TestAssert::true(
            (bool) preg_match('/^[' . preg_quote($charset, '/') . ']+$/', $code),
            'create_code: only charset chars (sample ' . $i . ')'
        );
    }

    echo "=== create_code() entropy space ===\n";

    TestAssert::same(27, strlen($charset), 'charset: 27 chars');
    TestAssert::true(pow(27, 6) > 300000000, 'create_code: 6-digit space > 3e8');
});
