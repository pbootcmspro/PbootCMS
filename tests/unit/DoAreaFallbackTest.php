<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/home/controller/DoController.php area
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

function do_area_probe(string $lgArg): array
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'do_area_probe.php';
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($lgArg);
    $output = shell_exec($cmd);
    if (! is_string($output) || $output === '') {
        return array('location' => null, 'cookie_lg' => null, 'error' => 'empty output');
    }
    $data = json_decode(trim($output), true);
    return is_array($data) ? $data : array('location' => null, 'cookie_lg' => null, 'error' => 'invalid json');
}

return TestAssert::runSuite(function () {
    echo "=== DoController::area fallback redirect ===\n";

    $missing = do_area_probe('');
    TestAssert::same('/cms/', $missing['location'], 'missing lg redirects home');
    TestAssert::same(null, $missing['cookie_lg'], 'missing lg does not set cookie');

    $valid = do_area_probe('en');
    TestAssert::same('/cms/', $valid['location'], 'valid lg redirects home');
    TestAssert::same('en', $valid['cookie_lg'], 'valid lg sets cookie');

    $invalid = do_area_probe('xx');
    TestAssert::same('/cms/', $invalid['location'], 'invalid lg redirects home');
    TestAssert::same(null, $invalid['cookie_lg'], 'invalid lg does not set cookie');
});
