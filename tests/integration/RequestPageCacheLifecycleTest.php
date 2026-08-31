<?php

declare(strict_types=1);

/**
 * @suite integration
 * @covers frontend page query validation before HTML cache write
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

function request_page_cache_probe($queryString, $argSeparatorInput = '')
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'request_page_cache_probe.php';
    $args = array(escapeshellarg($php));
    if ($argSeparatorInput !== '') {
        $args[] = '-d';
        $args[] = escapeshellarg('arg_separator.input=' . $argSeparatorInput);
    }
    $args[] = escapeshellarg($script);
    $args[] = escapeshellarg(base64_encode($queryString));
    $cmd = implode(' ', $args);

    $descriptors = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $pipes = array();
    $proc = proc_open($cmd, $descriptors, $pipes);
    if ($proc === false || (! is_resource($proc) && ! is_object($proc))) {
        return array('stdout' => '', 'stderr' => '', 'exit_code' => -1, 'result' => null);
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = (int) proc_close($proc);

    $result = null;
    if (preg_match('/PROBE_RESULT=([A-Za-z0-9+\/=]+)/', $stdout, $matches)) {
        $json = base64_decode($matches[1], true);
        $decoded = $json === false ? null : json_decode($json, true);
        if (is_array($decoded)) {
            $result = $decoded;
        }
    }

    return array(
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
        'exit_code' => $exitCode,
        'result' => $result,
    );
}

return TestAssert::runSuite(function () {
    $scenarios = array(
        array('NUL key', 'page%00=abc&cache_probe=' . getmypid(), ''),
        array('semicolon separator', 'foo=1;page=abc&cache_probe=' . getmypid(), '&;'),
    );

    echo "=== illegal page request stays 400 and never creates HTML cache ===\n";
    foreach ($scenarios as $scenario) {
        for ($attempt = 1; $attempt <= 2; $attempt ++) {
            $label = $scenario[0] . ' request #' . $attempt;
            $probe = request_page_cache_probe($scenario[1], $scenario[2]);
            TestAssert::same(0, $probe['exit_code'], $label . ': process exit');
            TestAssert::same('', $probe['stderr'], $label . ': stderr');
            TestAssert::true(is_array($probe['result']), $label . ': result decoded');
            TestAssert::same(400, isset($probe['result']['status']) ? $probe['result']['status'] : null, $label . ': HTTP 400');
            TestAssert::same(array(), isset($probe['result']['new_cache_files']) ? $probe['result']['new_cache_files'] : null, $label . ': no new HTML cache');
        }
    }
});
