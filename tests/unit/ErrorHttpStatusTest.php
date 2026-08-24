<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers error() _404() HTTP 状态码
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * @return array{stdout:string,stderr:string,exit_code:int}
 */
function error_status_run_probe(string $mode): array
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'error_http_status_probe.php';
    $cmd = $php . ' ' . implode(' ', array_map('escapeshellarg', array($script, $mode)));

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
function error_status_from_probe(array $result)
{
    if (preg_match('/HTTP_STATUS=(\d+)/', $result['stdout'], $m)) {
        return (int) $m[1];
    }
    return null;
}

/**
 * @return array<string,mixed>|null
 */
function error_status_json_from_probe(array $result)
{
    if (!preg_match('/\{.*\}/s', $result['stdout'], $m)) {
        return null;
    }
    $decoded = json_decode($m[0], true);
    return is_array($decoded) ? $decoded : null;
}

return TestAssert::runSuite(function () {
    echo "=== error() default HTML status remains 404 ===\n";
    $r = error_status_run_probe('error_default');
    TestAssert::same(404, error_status_from_probe($r), 'error() default HTTP status');

    echo "=== error() accepts custom status code ===\n";
    $r = error_status_run_probe('error_custom');
    TestAssert::same(403, error_status_from_probe($r), 'error() custom HTTP status');

    echo "=== AJAX error() defaults to 200 + code:0 with real message ===\n";
    $r = error_status_run_probe('error_ajax_default');
    TestAssert::same(200, error_status_from_probe($r), 'AJAX error() HTTP 200');
    $json = error_status_json_from_probe($r);
    TestAssert::true(is_array($json), 'AJAX error() returns JSON body');
    TestAssert::same(0, isset($json['code']) ? (int) $json['code'] : -1, 'AJAX error() code 0');
    TestAssert::same('您的账号权限不足，您无法执行该操作！', isset($json['data']) ? $json['data'] : '', 'AJAX error() keeps Chinese reason');

    echo "=== API json module keeps HTTP 404 on default error() ===\n";
    $r = error_status_run_probe('error_api_json_default');
    TestAssert::same(404, error_status_from_probe($r), 'API json error() HTTP 404');
    $json = error_status_json_from_probe($r);
    TestAssert::true(is_array($json), 'API json error() returns JSON body');
    TestAssert::same(0, isset($json['code']) ? (int) $json['code'] : -1, 'API json error() code 0');
    TestAssert::same('API 模块业务错误', isset($json['data']) ? $json['data'] : '', 'API json error() keeps reason');

    echo "=== AJAX explicit 403/404 preserved ===\n";
    $r = error_status_run_probe('error_ajax_403');
    TestAssert::same(403, error_status_from_probe($r), 'AJAX explicit 403');
    $r = error_status_run_probe('error_ajax_404');
    TestAssert::same(404, error_status_from_probe($r), 'AJAX explicit 404');

    echo "=== _404() fallback without 404.html stays 404 ===\n";
    TestAssert::false(file_exists(ROOT_PATH . '404.html'), 'repo has no root 404.html');
    $r = error_status_run_probe('_404_fallback');
    TestAssert::same(404, error_status_from_probe($r), '_404() fallback HTTP status');
});
