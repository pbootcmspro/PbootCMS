<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers alert_location() success() error() JSON/HTML tourl sanitization
 *
 * 运行：php tests/unit/SafeRedirectHelperTest.php
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * @return array<string,mixed>|null
 */
function safe_redirect_json_from_probe(array $result)
{
    if (!preg_match('/\{.*\}/s', $result['stdout'], $m)) {
        return null;
    }
    $decoded = json_decode($m[0], true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * @return array{stdout:string,stderr:string,exit_code:int}
 */
function safe_redirect_run_probe(string $mode): array
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'safe_redirect_probe.php';
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
 * @return array{stdout:string,stderr:string,exit_code:int}
 */
function safe_redirect_run_get_btn_back_probe(string $mode): array
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'get_btn_back_probe.php';
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

return TestAssert::runSuite(function () {
    echo "=== alert_location AJAX tourl sanitized ===\n";

    $r = safe_redirect_run_probe('alert_external');
    $json = safe_redirect_json_from_probe($r);
    TestAssert::true(is_array($json), 'alert_location external returns JSON');
    TestAssert::same('/', isset($json['tourl']) ? $json['tourl'] : '', 'external tourl fallback /');

    $r = safe_redirect_run_probe('alert_javascript');
    $json = safe_redirect_json_from_probe($r);
    TestAssert::same('/', isset($json['tourl']) ? $json['tourl'] : '', 'javascript tourl fallback /');

    echo "=== success() after base64_decode path sanitized ===\n";

    $r = safe_redirect_run_probe('success_base64_external');
    $json = safe_redirect_json_from_probe($r);
    TestAssert::same('/', isset($json['tourl']) ? $json['tourl'] : '', 'base64 external tourl fallback /');

    echo "=== error() Referer -1 prefix bypass sanitized ===\n";

    $r = safe_redirect_run_probe('error_referer_spoof');
    $json = safe_redirect_json_from_probe($r);
    TestAssert::same('/', isset($json['tourl']) ? $json['tourl'] : '', 'referer spoof tourl fallback /');

    echo "=== get_btn_back() entity-encoded backurl rejected in href ===\n";

    $r = safe_redirect_run_get_btn_back_probe('entity_javascript');
    TestAssert::same(0, $r['exit_code'], 'entity_javascript probe exit 0');
    TestAssert::notContains($r['stdout'], 'javascript:alert', 'entity javascript not in href');
    TestAssert::contains($r['stdout'], '/admin/Content/index', 'entity javascript falls back to index');

    $r = safe_redirect_run_get_btn_back_probe('entity_triple_javascript');
    TestAssert::same(0, $r['exit_code'], 'entity_triple_javascript probe exit 0');
    TestAssert::notContains($r['stdout'], 'javascript:alert', 'triple entity javascript not in href');
    TestAssert::contains($r['stdout'], '/admin/Content/index', 'triple entity javascript falls back to index');

    $r = safe_redirect_run_get_btn_back_probe('entity_space_protocol_relative');
    TestAssert::same(0, $r['exit_code'], 'entity_space_protocol_relative probe exit 0');
    TestAssert::notContains($r['stdout'], '//evil.test', 'space entity protocol-relative not in href');
    TestAssert::contains($r['stdout'], '/admin/Content/index', 'space entity protocol-relative falls back to index');

    $r = safe_redirect_run_get_btn_back_probe('entity_hex_space_protocol_relative');
    TestAssert::same(0, $r['exit_code'], 'entity_hex_space_protocol_relative probe exit 0');
    TestAssert::notContains($r['stdout'], '//evil.test', 'hex space entity protocol-relative not in href');
    TestAssert::contains($r['stdout'], '/admin/Content/index', 'hex space entity protocol-relative falls back to index');

    echo "=== get_btn_back() safe query href escapes ampersand ===\n";

    $r = safe_redirect_run_get_btn_back_probe('safe_query');
    TestAssert::same(0, $r['exit_code'], 'safe_query probe exit 0');
    TestAssert::contains($r['stdout'], "href='/search?a=1&amp;b=2'", 'query ampersand escaped in href');
});
