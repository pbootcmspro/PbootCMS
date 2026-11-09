<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/common/function.php admin_saved
 *
 * 运行：php tests/unit/AdminWriteOkTest.php
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require APP_PATH . '/common/function.php';

return TestAssert::runSuite(function () {
    echo "=== unchanged update is saved, zero insert is not ===\n";

    TestAssert::true(admin_saved(true, true), 'sqlite exec true is saved');
    TestAssert::true(admin_saved(0, true), 'update affected 0 is saved');
    TestAssert::true(admin_saved(1, true), 'update affected 1 is saved');
    TestAssert::true(admin_saved('0', true), 'numeric string 0 update is saved');
    TestAssert::false(admin_saved(0, false), 'insert affected 0 is not saved');
    TestAssert::true(admin_saved(1, false), 'insert affected 1 is saved');
    TestAssert::true(admin_saved(true, false), 'sqlite exec true insert is saved');
    TestAssert::false(admin_saved(false, true), 'false is not saved');
    TestAssert::false(admin_saved(null, true), 'null is not saved');
    TestAssert::false(admin_saved(new stdClass(), true), 'object result is not saved');
});
