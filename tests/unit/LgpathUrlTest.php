<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/basic/Url.php Url::addQuery Url::get Url::lgArea
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

define('SITE_DIR', '');
define('SITE_INDEX_DIR', '');
defined('DOC_PATH') ?: define('DOC_PATH', ROOT_PATH);
defined('RUN_PATH') ?: define('RUN_PATH', ROOT_PATH . 'runtime');
defined('CONF_PATH') ?: define('CONF_PATH', ROOT_PATH . 'config');

require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/Url.php';
require_once CORE_PATH . '/function/handle.php';

function lgpath_test_reset_url_cache()
{
    $ref = new ReflectionClass('core\basic\Url');
    $prop = $ref->getProperty('urls');
    if (PHP_VERSION_ID < 80100) {
        $prop->setAccessible(true);
    }
    $prop->setValue(null, array());
}

function lgpath_test_setup_url(int $appUrlType, string $scriptName = '/index.php')
{
    lgpath_test_reset_url_cache();
    ConfigStub::set(array('app_url_type' => $appUrlType));
    $_SERVER['SCRIPT_NAME'] = $scriptName;
}

return TestAssert::runSuite(function () {
    echo "=== Url::addQuery ===\n";

    TestAssert::same(
        '/index.php/home/Do/area/?lg=en',
        \core\basic\Url::addQuery('/index.php/home/Do/area/', 'lg=en'),
        'addQuery: pathinfo url uses ?'
    );
    TestAssert::same(
        '/index.php?p=/home/Do/area&lg=en',
        \core\basic\Url::addQuery('/index.php?p=/home/Do/area', 'lg=en'),
        'addQuery: query url uses &'
    );
    TestAssert::same(
        '/index.php/home/Do/area/#top',
        \core\basic\Url::addQuery('/index.php/home/Do/area/#top', ''),
        'addQuery: empty query unchanged'
    );

    echo "=== Url::get / lgArea (root deploy, no URL_BIND) ===\n";

    lgpath_test_setup_url(1);
    TestAssert::same(
        '/index.php/home/Do/area/?lg=en',
        \core\basic\Url::addQuery(\core\basic\Url::get('home/Do/area'), 'lg=en'),
        'app_url_type=1: lg switch url'
    );

    lgpath_test_setup_url(2);
    TestAssert::same(
        '/home/Do/area/?lg=en',
        \core\basic\Url::lgArea('en'),
        'app_url_type=2: lgArea uses pseudo-static path'
    );

    lgpath_test_setup_url(3);
    TestAssert::same(
        '/?p=/home/Do/area&lg=en',
        \core\basic\Url::addQuery(\core\basic\Url::get('home/Do/area'), 'lg=en'),
        'app_url_type=3: lg switch url'
    );

    echo "=== legacy template separators normalized ===\n";

    lgpath_test_setup_url(1);
    $base = \core\basic\Url::get('home/Do/area');
    TestAssert::notContains(
        \core\basic\Url::addQuery($base, 'lg=cn'),
        '/&lg=',
        'app_url_type=1: must not use & before first query'
    );

    lgpath_test_setup_url(3);
    $base3 = \core\basic\Url::get('home/Do/area');
    TestAssert::contains(
        \core\basic\Url::addQuery($base3, 'lg=cn'),
        '&lg=cn',
        'app_url_type=3: &lg template form'
    );
    TestAssert::notContains(
        \core\basic\Url::addQuery($base3, 'lg=cn'),
        '?lg=cn',
        'app_url_type=3: must not add second ?'
    );

    echo "=== URL_BIND=home ===\n";

    if (! defined('URL_BIND')) {
        define('URL_BIND', 'home');
    }
    if (! defined('M')) {
        define('M', 'home');
    }
    lgpath_test_setup_url(2);
    TestAssert::same(
        '/Do/area/?lg=en',
        \core\basic\Url::lgArea('en'),
        'app_url_type=2 + URL_BIND=home: lgArea'
    );
});
