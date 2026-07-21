<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/basic/Url.php Url::get (subdir deploy)
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

function lgpath_subdir_probe(int $appUrlType): string
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'lgpath_url_probe.php';
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . (string) $appUrlType;
    $output = shell_exec($cmd);
    if (! is_string($output) || $output === '') {
        return '';
    }
    $data = json_decode(trim($output), true);
    return is_array($data) && isset($data['url']) ? (string) $data['url'] : '';
}

return TestAssert::runSuite(function () {
    echo "=== Url::get home/Do/area (subdir /cms) ===\n";

    TestAssert::same(
        '/cms/index.php/Do/area/?lg=en',
        lgpath_subdir_probe(1),
        'subdir app_url_type=1'
    );
    TestAssert::same(
        '/cms/Do/area/?lg=en',
        lgpath_subdir_probe(2),
        'subdir app_url_type=2 pseudo-static'
    );
    TestAssert::same(
        '/cms/?p=/Do/area&lg=en',
        lgpath_subdir_probe(3),
        'subdir app_url_type=3'
    );
});
