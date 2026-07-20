<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/home/controller/ParserController.php lgpath replacement
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

function lgpath_parser_reset_url_cache()
{
    $ref = new ReflectionClass('core\basic\Url');
    $prop = $ref->getProperty('urls');
    if (PHP_VERSION_ID < 80100) {
        $prop->setAccessible(true);
    }
    $prop->setValue(null, array());
}

function lgpath_parser_setup(int $appUrlType)
{
    lgpath_parser_reset_url_cache();
    ConfigStub::set(array('app_url_type' => $appUrlType));
    $_SERVER['SCRIPT_NAME'] = '/index.php';
}

/** 与 ParserController::parserSingleLabel 中 lgpath 替换逻辑保持一致 */
function lgpath_apply_template_labels(string $content): string
{
    $content = preg_replace_callback(
        '/\{pboot:lgpath\}[?&]lg=([\w\-\.]+)/i',
        function ($matches) {
            return \core\basic\Url::lgArea($matches[1]);
        },
        $content
    );
    return str_replace('{pboot:lgpath}', \core\basic\Url::lgArea(), $content);
}

return TestAssert::runSuite(function () {
    echo "=== lgpath template replacement ?lg= ===\n";

    lgpath_parser_setup(1);
    TestAssert::same(
        '<a href="/index.php/home/Do/area/?lg=en">EN</a>',
        lgpath_apply_template_labels('<a href="{pboot:lgpath}?lg=en">EN</a>'),
        'parser app_url_type=1 ?lg=en'
    );

    lgpath_parser_setup(3);
    TestAssert::same(
        '<a href="/?p=/home/Do/area&lg=en">EN</a>',
        lgpath_apply_template_labels('<a href="{pboot:lgpath}?lg=en">EN</a>'),
        'parser app_url_type=3 ?lg=en'
    );

    echo "=== lgpath template replacement &lg= ===\n";

    lgpath_parser_setup(1);
    TestAssert::same(
        '<a href="/index.php/home/Do/area/?lg=cn">CN</a>',
        lgpath_apply_template_labels('<a href="{pboot:lgpath}&lg=cn">CN</a>'),
        'parser app_url_type=1 &lg=cn normalized'
    );

    lgpath_parser_setup(3);
    TestAssert::same(
        '<a href="/?p=/home/Do/area&lg=cn">CN</a>',
        lgpath_apply_template_labels('<a href="{pboot:lgpath}&lg=cn">CN</a>'),
        'parser app_url_type=3 &lg=cn'
    );

    echo "=== bare lgpath / type2 ===\n";

    lgpath_parser_setup(2);
    TestAssert::same(
        'base=/home/Do/area/',
        lgpath_apply_template_labels('base={pboot:lgpath}'),
        'parser app_url_type=2 bare lgpath'
    );
    TestAssert::same(
        '<a href="/home/Do/area/?lg=en">EN</a>',
        lgpath_apply_template_labels('<a href="{pboot:lgpath}?lg=en">EN</a>'),
        'parser app_url_type=2 ?lg=en'
    );
});
