<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers get_server_soft()
 *
 * 回归：SERVER_SOFTWARE 以 Apache/nginx 等关键字开头时，strpos 返回 0，
 * 旧写法 if (strpos(...)) 会落到 other，导致伪静态不部署 .htaccess。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * @param string|null $software
 * @return string
 */
function get_server_soft_with($software)
{
    $prev = array_key_exists('SERVER_SOFTWARE', $_SERVER) ? $_SERVER['SERVER_SOFTWARE'] : null;
    if ($software === null) {
        unset($_SERVER['SERVER_SOFTWARE']);
    } else {
        $_SERVER['SERVER_SOFTWARE'] = $software;
    }
    try {
        return get_server_soft();
    } finally {
        if ($prev === null) {
            unset($_SERVER['SERVER_SOFTWARE']);
        } else {
            $_SERVER['SERVER_SOFTWARE'] = $prev;
        }
    }
}

return TestAssert::runSuite(function () {
    echo "=== source contract: strpos must use !== false ===\n";

    $src = file_get_contents(CORE_PATH . '/function/handle.php');
    TestAssert::false($src === false, 'handle.php readable');
    $pos = strpos($src, 'function get_server_soft()');
    TestAssert::false($pos === false, 'get_server_soft found');
    // 下一函数为 deploy_pseudo_static_rewrite；勿用更远的会话目录注释，避免切片吞并邻接函数
    $posEnd = strpos($src, 'function deploy_pseudo_static_rewrite(', $pos);
    TestAssert::false($posEnd === false, 'function end marker found');
    $fn = substr($src, $pos, $posEnd - $pos);
    TestAssert::notContains($fn, 'function deploy_pseudo_static_rewrite', 'slice excludes deploy helper');

    TestAssert::contains($fn, "strpos(\$soft, 'iis') !== false", 'iis uses !== false');
    TestAssert::contains($fn, "strpos(\$soft, 'apache') !== false", 'apache uses !== false');
    TestAssert::contains($fn, "strpos(\$soft, 'nginx') !== false", 'nginx uses !== false');
    TestAssert::notContains($fn, "if (strpos(\$soft, 'iis')) {", 'no truthy strpos for iis');
    TestAssert::notContains($fn, "elseif (strpos(\$soft, 'apache')) {", 'no truthy strpos for apache');
    TestAssert::notContains($fn, "elseif (strpos(\$soft, 'nginx')) {", 'no truthy strpos for nginx');

    echo "=== keyword at offset 0 (former bug) ===\n";

    TestAssert::same('apache', get_server_soft_with('Apache/2.4.58 (Win64)'), 'Apache at start');
    TestAssert::same('apache', get_server_soft_with('apache/2.4.41 (Unix)'), 'apache lowercase at start');
    TestAssert::same('nginx', get_server_soft_with('nginx/1.24.0'), 'nginx at start');
    // 覆盖偏移 0：strtolower('IIS/10.0') === 'iis/10.0'
    TestAssert::same('iis', get_server_soft_with('IIS/10.0'), 'IIS at start');
    TestAssert::same('iis', get_server_soft_with('Microsoft-IIS/10.0'), 'Microsoft-IIS');

    echo "=== keyword not at start (still ok) ===\n";

    TestAssert::same('apache', get_server_soft_with('Win64 Apache/2.4.58'), 'Apache mid-string');
    TestAssert::same('nginx', get_server_soft_with('openresty/nginx'), 'nginx mid-string');

    echo "=== other / empty ===\n";

    TestAssert::same('other', get_server_soft_with(''), 'empty string');
    TestAssert::same('other', get_server_soft_with('LiteSpeed'), 'unknown soft');
    TestAssert::same('other', get_server_soft_with(null), 'missing SERVER_SOFTWARE');
});
