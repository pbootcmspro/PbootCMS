<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/basic/Url.php Url::get 静态缓存键与裁剪后路径一致性
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

define('SITE_DIR', '');
define('SITE_INDEX_DIR', '');
defined('DOC_PATH') ?: define('DOC_PATH', ROOT_PATH);
defined('RUN_PATH') ?: define('RUN_PATH', ROOT_PATH . 'runtime');
defined('CONF_PATH') ?: define('CONF_PATH', ROOT_PATH . 'config');
defined('URL_BIND') ?: define('URL_BIND', 'admin');
defined('M') ?: define('M', 'admin');

require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/Url.php';
require_once CORE_PATH . '/function/handle.php';

function url_get_cache_reset()
{
    $ref = new ReflectionClass('core\basic\Url');
    $prop = $ref->getProperty('urls');
    if (PHP_VERSION_ID < 80100) {
        $prop->setAccessible(true);
    }
    $prop->setValue(null, array());
}

function url_get_cache_keys(): array
{
    $ref = new ReflectionClass('core\basic\Url');
    $prop = $ref->getProperty('urls');
    if (PHP_VERSION_ID < 80100) {
        $prop->setAccessible(true);
    }
    return array_keys($prop->getValue(null));
}

function url_get_cache_setup(int $appUrlType, string $scriptName = '/admin.php')
{
    url_get_cache_reset();
    ConfigStub::set(array(
        'app_url_type' => $appUrlType,
        'url_route' => array(),
        'app_domain_bind' => array(),
    ));
    $_SERVER['SCRIPT_NAME'] = $scriptName;
}

return TestAssert::runSuite(function () {
    echo "=== Url::get cache key with URL_BIND ===\n";

    url_get_cache_setup(3);
    $first = \core\basic\Url::get('/admin/Content/index');
    $second = \core\basic\Url::get('/admin/Content/index');
    $keys = url_get_cache_keys();

    TestAssert::same($first, $second, 'same path twice returns identical url');
    TestAssert::same(1, count($keys), 'same path shares a single cache entry');
    TestAssert::true(
        strpos($keys[0], 'admin/Content/index') === 0,
        'cache key uses original path, not cut path (got: ' . var_export($keys[0], true) . ')'
    );
    TestAssert::false(
        $keys[0] === 'Content/index' || strpos($keys[0], "Content/index\0") === 0,
        'cache must not use cut path alone as key'
    );

    echo "=== Url::get module root path ===\n";

    url_get_cache_setup(3);
    $r1 = \core\basic\Url::get('/admin');
    $r2 = \core\basic\Url::get('/admin');
    $rootKeys = url_get_cache_keys();

    TestAssert::same($r1, $r2, 'module root twice returns identical url');
    TestAssert::same(1, count($rootKeys), 'module root shares a single cache entry');
    TestAssert::true(
        strpos($rootKeys[0], 'admin') === 0,
        'module root cache key uses original path (got: ' . var_export($rootKeys[0], true) . ')'
    );

    echo "=== Url::get different suffix not colliding ===\n";

    url_get_cache_setup(2, '/index.php');
    ConfigStub::set(array('url_rule_suffix' => '.html'));
    $plain = \core\basic\Url::get('/admin/Content/index', false);
    $withSuffix = \core\basic\Url::get('/admin/Content/index', true);
    TestAssert::same(2, count(url_get_cache_keys()), 'different suffix uses distinct cache keys');
    TestAssert::true($plain !== $withSuffix, 'suffix true/false produce different urls under type=2');
});
