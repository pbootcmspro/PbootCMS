<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/extend/excel/excelreader.php Spreadsheet_Excel_Reader::__construct
 * @covers core/extend/excel/excelreader.php Spreadsheet_Excel_Reader::Spreadsheet_Excel_Reader
 */

require dirname(__DIR__) . '/support/Assert.php';
require dirname(__DIR__, 2) . '/core/extend/excel/excelreader.php';

exit(TestAssert::runSuite(function () {
    $previousErrorReporting = error_reporting();

    try {
        error_reporting($previousErrorReporting & ~E_DEPRECATED);
        $reader = new \core\extend\excel\Spreadsheet_Excel_Reader();
    } finally {
        error_reporting($previousErrorReporting);
    }

    TestAssert::true(
        $reader->_ole instanceof \core\extend\excel\OLERead,
        'constructor initializes _ole as OLERead'
    );
}));
