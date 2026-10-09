<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/file.php
 *
 * fix #285：PHP < 8.1 多进程并发递归创建同一多级目录，create_dir() 不得偶发返回 false
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}
require_once CORE_PATH . '/function/file.php';

function create_dir_proc_ok($proc)
{
    return $proc !== false && (is_resource($proc) || is_object($proc));
}

function create_dir_barrier_run(string $target, string $syncDir, int $workers): array
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'create_dir_probe.php';
    $cmd = $php . ' ' . implode(' ', array_map('escapeshellarg', array($script, $target, $syncDir)));
    $descriptors = array(1 => array('pipe', 'w'), 2 => array('pipe', 'w'));

    $runs = array();
    for ($i = 0; $i < $workers; $i++) {
        $pipes = array();
        $proc = proc_open($cmd, $descriptors, $pipes);
        $runs[] = array($proc, $pipes);
    }

    $deadline = microtime(true) + 30;
    while (microtime(true) < $deadline) {
        $ready = glob($syncDir . DIRECTORY_SEPARATOR . 'ready_*');
        if (is_array($ready) && count($ready) >= $workers) {
            break;
        }
        usleep(2000);
    }
    file_put_contents($syncDir . DIRECTORY_SEPARATOR . 'go', '1');

    $results = array();
    foreach ($runs as $run) {
        list($proc, $pipes) = $run;
        if (! create_dir_proc_ok($proc)) {
            $results[] = array('exit_code' => -1, 'stdout' => '', 'stderr' => '');
            continue;
        }
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $results[] = array('exit_code' => proc_close($proc), 'stdout' => $stdout, 'stderr' => $stderr);
    }
    return $results;
}

return TestAssert::runSuite(function () {
    $base = sys_get_temp_dir() . '/pboot_create_dir_test_' . getmypid();
    $workers = 50;
    $rounds = 3;
    $cases = array(
        'two-level' => array('a', 'b'),
        'three-level' => array('a', 'b', 'c'),
    );

    foreach ($cases as $label => $segments) {
        echo "=== concurrent create_dir(): {$workers} processes x {$rounds} rounds, {$label} ===\n";

        $failures = 0;
        $detail = '';
        for ($round = 1; $round <= $rounds; $round++) {
            path_delete($base, true);
            mkdir($base . '/sync', 0777, true);
            $target = $base . '/' . implode('/', $segments);
            TestAssert::false(is_dir($base . '/' . $segments[0]), $label . ' round ' . $round . ': target absent before release');

            $results = create_dir_barrier_run($target, $base . '/sync', $workers);
            TestAssert::same($workers, count($results), $label . ' round ' . $round . ': all results collected');
            foreach ($results as $result) {
                $res = json_decode(trim($result['stdout']), true);
                if ($result['exit_code'] !== 0 || ! is_array($res) || empty($res['ok'])) {
                    $failures++;
                    if ($detail === '') {
                        $detail = 'exit=' . $result['exit_code'] . ', stdout=' . trim($result['stdout']) . ', stderr=' . trim($result['stderr']);
                    }
                }
            }
            TestAssert::true(is_dir($target), $label . ' round ' . $round . ': target exists');
        }
        TestAssert::same(0, $failures, $label . ': zero create_dir() failures' . ($detail !== '' ? ' (' . $detail . ')' : ''));
    }

    path_delete($base, true);
});
