<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/extend/excel/SimpleXLSX.php
 * @covers core/extend/excel/SimpleXLSXEx.php
 *
 * #273：内置表格读取器替换为 SimpleXLSX，经框架自动加载读取 .xlsx
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once CORE_PATH . '/basic/Basic.php';

spl_autoload_register('core\\basic\\Basic::autoLoad', true, true);

use core\extend\excel\SimpleXLSX;

exit(TestAssert::runSuite(function () {
    $sample = TEST_ROOT . 'fixtures/excel/simplexlsx_sample.xlsx';

    TestAssert::false(class_exists('core\\extend\\excel\\SimpleXLSX', false), 'reader not loaded before first use');

    $xlsx = SimpleXLSX::parse($sample);
    TestAssert::true($xlsx instanceof SimpleXLSX, 'parse() reads sample via framework autoload');
    TestAssert::same(
        realpath(CORE_PATH . '/extend/excel/SimpleXLSX.php'),
        realpath((new \ReflectionClass('core\\extend\\excel\\SimpleXLSX'))->getFileName()),
        'SimpleXLSX resolved from core/extend/excel'
    );

    TestAssert::same(2, $xlsx->sheetsCount(), 'sample has two worksheets');
    TestAssert::same(array('学生', 'English'), array_values($xlsx->sheetNames()), 'worksheet names include chinese');
    TestAssert::same(
        array(
            array('编号', '姓名', '城市'),
            array(1, '张三', '北京'),
            array(2, '李四', '上海'),
        ),
        $xlsx->rows(0),
        'first worksheet rows with chinese cells'
    );
    TestAssert::same(
        array(
            array('id', 'name'),
            array(100, 'Alice'),
        ),
        $xlsx->rows(1),
        'second worksheet rows'
    );

    TestAssert::false(class_exists('core\\extend\\excel\\SimpleXLSXEx', false), 'SimpleXLSXEx not loaded before rowsEx()');
    $rowsEx = $xlsx->rowsEx(0);
    TestAssert::true(class_exists('core\\extend\\excel\\SimpleXLSXEx', false), 'rowsEx() loads SimpleXLSXEx');
    TestAssert::same(3, count($rowsEx), 'rowsEx() row count matches rows()');
    TestAssert::same('张三', $rowsEx[1][1]['value'], 'rowsEx() cell value');
    TestAssert::same('B2', $rowsEx[1][1]['name'], 'rowsEx() cell name');

    $invalid = array(
        'missing file' => array(TEST_ROOT . 'fixtures/excel/not_exists.xlsx', false, 'File not found'),
        'non-xlsx file' => array(__FILE__, false, 'Unknown archive format'),
        'non-xlsx data' => array('plain text, not a zip archive', true, 'Unknown archive format'),
    );
    foreach ($invalid as $label => $case) {
        $result = SimpleXLSX::parse($case[0], $case[1]);
        TestAssert::same(false, $result, $label . ': parse() returns false');
        $error = SimpleXLSX::parseError();
        TestAssert::true(is_string($error), $label . ': parseError() returns string');
        TestAssert::contains((string) $error, $case[2], $label . ': parseError() message');
        SimpleXLSX::parseError('-');
    }
}));
