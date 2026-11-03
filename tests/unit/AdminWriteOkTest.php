<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/admin/controller/content/SiteController.php writeOk
 * @covers apps/admin/controller/content/CompanyController.php writeOk
 *
 * 运行：php tests/unit/AdminWriteOkTest.php
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require APP_PATH . '/admin/controller/content/SiteController.php';
require APP_PATH . '/admin/controller/content/CompanyController.php';

return TestAssert::runSuite(function () {
    echo "=== unchanged update is saved, zero insert is not ===\n";

    foreach (array(
        'app\\admin\\controller\\content\\SiteController',
        'app\\admin\\controller\\content\\CompanyController',
    ) as $class) {
        $ref = new ReflectionClass($class);
        $obj = $ref->newInstanceWithoutConstructor();
        $method = $ref->getMethod('writeOk');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        TestAssert::true($method->invoke($obj, true, true), $class . ' sqlite exec true is saved');
        TestAssert::true($method->invoke($obj, 0, true), $class . ' update affected 0 is saved');
        TestAssert::true($method->invoke($obj, 1, true), $class . ' update affected 1 is saved');
        TestAssert::true($method->invoke($obj, '0', true), $class . ' numeric string 0 update is saved');
        TestAssert::false($method->invoke($obj, 0, false), $class . ' insert affected 0 is not saved');
        TestAssert::true($method->invoke($obj, 1, false), $class . ' insert affected 1 is saved');
        TestAssert::true($method->invoke($obj, true, false), $class . ' sqlite exec true insert is saved');
        TestAssert::false($method->invoke($obj, false, true), $class . ' false is not saved');
        TestAssert::false($method->invoke($obj, null, true), $class . ' null is not saved');
    }
});
