<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers IndexController::getRequestPath
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once APP_PATH . '/common/HomeController.php';
require_once APP_PATH . '/common/HomeBaseController.php';
require_once APP_PATH . '/home/controller/IndexController.php';

return TestAssert::runSuite(function () {
    $controller = (new ReflectionClass('app\\home\\controller\\IndexController'))->newInstanceWithoutConstructor();
    $pathMethod = new ReflectionMethod($controller, 'getRequestPath');
    $redirectMethod = new ReflectionMethod($controller, 'getRedirectUrl');
    if (PHP_VERSION_ID < 80100) {
        $pathMethod->setAccessible(true);
        $redirectMethod->setAccessible(true);
    }

    echo "=== homepage advertising query does not become a route ===\n";
    $_SERVER['REQUEST_URI'] = '/?bd_vid=9515903888264888243';
    TestAssert::same('/', $pathMethod->invoke($controller), 'bd_vid: homepage path retained');

    echo "=== page path is retained while advertising query is ignored by routing ===\n";
    $_SERVER['REQUEST_URI'] = '/news/?bd_vid=9515903888264888243&utm_source=baidu';
    TestAssert::same('/news/', $pathMethod->invoke($controller), 'bd_vid: content path retained');

    echo "=== encoded query characters do not affect the route path ===\n";
    $_SERVER['REQUEST_URI'] = '/?url=https%3A%2F%2Fexample.com%2Fa%3Fb%3D1';
    TestAssert::same('/', $pathMethod->invoke($controller), 'encoded query: homepage path retained');

    echo "=== malformed request targets fail closed ===\n";
    $_SERVER['REQUEST_URI'] = '///invalid?bd_vid=9515903888264888243';
    TestAssert::same('', $pathMethod->invoke($controller), 'malformed URI: no routable path');

    echo "=== canonical redirects preserve advertising queries ===\n";
    $_SERVER['HTTP_HOST'] = 'm.example.com';
    $_SERVER['REQUEST_URI'] = '/index.php/foo?bd_vid=9515903888264888243&utm_source=baidu';
    TestAssert::same(
        'https://m.example.com/index.php?bd_vid=9515903888264888243&utm_source=baidu',
        $redirectMethod->invoke($controller, 'https://', '/index.php'),
        'redirect: query string retained'
    );

    $_SERVER['REQUEST_URI'] = '/index.php/foo';
    TestAssert::same(
        'http://m.example.com/index.php',
        $redirectMethod->invoke($controller, 'http://', '/index.php'),
        'redirect: no empty query marker'
    );

    $source = file_get_contents(APP_PATH . '/home/controller/IndexController.php');
    TestAssert::contains(
        $source,
        '$this->getRedirectUrl($http, $matches1[0])',
        'urlJump: canonical redirect uses query-preserving builder'
    );
});
