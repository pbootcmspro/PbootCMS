<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers apps/home/controller/ParserController.php parserNavLabel()
 *
 * Issue #235：scode 过滤必须在 array_slice(num) 之前，禁止循环内再过滤导致条数不足。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== parserNavLabel: scode filter before num slice ===\n";

    $src = file_get_contents(APP_PATH . '/home/controller/ParserController.php');
    TestAssert::false($src === false, 'ParserController readable');

    $posFn = strpos($src, 'function parserNavLabel(');
    TestAssert::false($posFn === false, 'parserNavLabel found');

    $posNext = strpos($src, "\n    public function ", $posFn + 1);
    $body = $posNext === false ? substr($src, $posFn) : substr($src, $posFn, $posNext - $posFn);

    $posFilter = strpos($body, 'if ($scode_arr)');
    $posSlice = strpos($body, 'array_slice($out_data, 0, $num)');
    TestAssert::false($posFilter === false, 'scode filter branch present');
    TestAssert::false($posSlice === false, 'num array_slice present');
    TestAssert::true(
        $posFilter < $posSlice,
        'scode filter appears before array_slice(num)'
    );

    // 循环内不得再按 scode continue（过滤应在截断前完成）
    TestAssert::notContains(
        $body,
        'if ($scode_arr && !in_array($value[\'scode\'], $scode_arr))',
        'no in-loop scode continue'
    );
});
