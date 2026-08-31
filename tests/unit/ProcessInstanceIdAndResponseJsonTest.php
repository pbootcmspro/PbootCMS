<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers process_instance_id()
 * @covers Response::json rowtotal Countable 安全
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/Response.php';

/**
 * 捕获 Response::json 输出（会 exit）
 *
 * @return array{stdout:string,stderr:string,exit_code:int}
 */
function response_json_probe($code, $data): array
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'response_json_probe.php';
    $payload = base64_encode(serialize(array($code, $data)));
    $cmd = $php . ' ' . implode(' ', array_map('escapeshellarg', array($script, $payload)));

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
    echo "=== process_instance_id 返回正整数 ===\n";

    TestAssert::true(function_exists('process_instance_id'), 'process_instance_id loaded');
    $id = process_instance_id();
    TestAssert::true(is_int($id) || is_float($id), 'process_instance_id numeric');
    TestAssert::true((int) $id > 0, 'process_instance_id positive');

    echo "=== Response::json 对 stdClass 不抛 TypeError ===\n";

    $r = response_json_probe(1, (object) array('msg' => 'hello'));
    TestAssert::same(0, $r['exit_code'], 'stdClass json exit 0');
    $decoded = json_decode($r['stdout'], true);
    TestAssert::true(is_array($decoded), 'stdClass json body');
    TestAssert::same(1, isset($decoded['code']) ? (int) $decoded['code'] : -1, 'stdClass code');
    TestAssert::same(1, isset($decoded['rowtotal']) ? (int) $decoded['rowtotal'] : -1, 'stdClass rowtotal is 1');

    echo "=== Response::json 对数组仍正确计数 ===\n";

    $r = response_json_probe(1, array('a', 'b', 'c'));
    TestAssert::same(0, $r['exit_code'], 'array json exit 0');
    $decoded = json_decode($r['stdout'], true);
    TestAssert::same(3, isset($decoded['rowtotal']) ? (int) $decoded['rowtotal'] : -1, 'array rowtotal is 3');

    echo "=== Response::json 对字符串 rowtotal=1 ===\n";

    $r = response_json_probe(0, '业务错误文案');
    TestAssert::same(0, $r['exit_code'], 'string json exit 0');
    $decoded = json_decode($r['stdout'], true);
    TestAssert::same(1, isset($decoded['rowtotal']) ? (int) $decoded['rowtotal'] : -1, 'string rowtotal is 1');
    TestAssert::same('业务错误文案', isset($decoded['data']) ? $decoded['data'] : '', 'string data preserved');
});
