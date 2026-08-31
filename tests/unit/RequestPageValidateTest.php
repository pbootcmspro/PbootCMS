<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core\basic\Request::validate
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once CORE_PATH . '/basic/Request.php';

/**
 * @return array{stdout:string,stderr:string,exit_code:int}
 */
function request_page_probe(string $queryString, string $argSeparatorInput = ''): array
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'request_page_validate_probe.php';
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
    if ($proc === false || (!is_resource($proc) && !is_object($proc))) {
        return array('stdout' => '', 'stderr' => '', 'exit_code' => -1);
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return array(
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
        'exit_code' => (int) proc_close($proc),
    );
}

/**
 * @return int|null
 */
function request_page_status(array $result)
{
    if (preg_match('/HTTP_STATUS=(\d+)/', $result['stdout'], $m)) {
        return (int) $m[1];
    }
    return null;
}

return TestAssert::runSuite(function () {
    echo "=== missing page and valid positive integers pass ===\n";
    foreach (array('', 'foo=bar', 'page=1', 'page=2', 'foo=1&page=10') as $qs) {
        $r = request_page_probe($qs);
        TestAssert::same(200, request_page_status($r), "allow: $qs");
        TestAssert::contains($r['stdout'], 'OK', "allow body: $qs");
    }

    echo "=== invalid page shapes return 400 ===\n";
    $rejectCases = array(
        'page=abc',
        'page=',
        'page=0',
        'page=-1',
        'page=1.5',
        'page= 1',
        'page=1 ',
        'page=01',
        'page=1&page=2',
        'page[]=1',
        'page[0]=1',
        '%70age=abc',
        'page=%30',
        'page%00=abc',
        'page%00x=abc',
    );
    foreach ($rejectCases as $qs) {
        $r = request_page_probe($qs);
        TestAssert::same(400, request_page_status($r), "reject: $qs");
        TestAssert::contains($r['stdout'], 'Bad Request', "reject body: $qs");
        TestAssert::notContains($r['stdout'], 'runtime', "reject no path leak: $qs");
    }

    echo "=== encoded duplicate page keys rejected ===\n";
    $r = request_page_probe('page=1%26page=2');
    TestAssert::same(400, request_page_status($r), 'encoded amp in value does not bypass');

    echo "=== configured PHP query separators cannot hide page ===\n";
    $r = request_page_probe('foo=1;page=abc', '&;');
    TestAssert::same(400, request_page_status($r), 'semicolon separator does not bypass');

    echo "=== splitQueryPairs preserves raw segments ===\n";
    $ref = new ReflectionClass('core\\basic\\Request');
    $method = $ref->getMethod('splitQueryPairs');
    if (PHP_VERSION_ID < 80100) {
        $method->setAccessible(true);
    }
    TestAssert::same(
        array('a=1', 'page=2', ''),
        $method->invoke(null, 'a=1&page=2&'),
        'splitQueryPairs: trailing amp preserved'
    );
});
