<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers purge_runtime_cache()
 *
 * 审计 #11：常规清缓存不得删除 runtime/image 缩略图；仅 delall 全量清理
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}
require_once CORE_PATH . '/function/file.php';

/**
 * @return string
 */
function clear_cache_tmp_run(string $suffix)
{
    $dir = sys_get_temp_dir() . '/pboot_clear_cache_' . $suffix . '_' . getmypid();
    path_delete($dir, true);
    return $dir;
}

/**
 * 在临时 RUN_PATH 下放置探针文件
 *
 * @param string $runPath
 * @param array<int, string> $dirs
 * @return array<string, string> dir => probe file path
 */
function clear_cache_seed_probes(string $runPath, array $dirs)
{
    $probes = array();
    foreach ($dirs as $dir) {
        $path = $runPath . '/' . $dir;
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $file = $path . '/probe_' . $dir . '.txt';
        file_put_contents($file, $dir);
        $probes[$dir] = $file;
    }
    return $probes;
}

/**
 * @param array<string, string> $probes
 * @param array<int, string> $expectAlive
 * @param array<int, string> $expectGone
 * @param string $label
 */
function clear_cache_assert_probes(array $probes, array $expectAlive, array $expectGone, string $label)
{
    foreach ($expectAlive as $dir) {
        TestAssert::true(
            isset($probes[$dir]) && file_exists($probes[$dir]),
            $label . ': keep ' . $dir
        );
    }
    foreach ($expectGone as $dir) {
        TestAssert::true(
            isset($probes[$dir]) && ! file_exists($probes[$dir]),
            $label . ': cleared ' . $dir
        );
    }
}

return TestAssert::runSuite(function () {
    $allDirs = array('cache', 'complile', 'config', 'upgrade', 'image', 'session', 'data');

    echo "=== purge_runtime_cache(all): keep image ===\n";
    $run = clear_cache_tmp_run('all');
    $probes = clear_cache_seed_probes($run, $allDirs);
    TestAssert::true(purge_runtime_cache($run, 'all', false), 'all mode returns true');
    clear_cache_assert_probes(
        $probes,
        array('image', 'session', 'data'),
        array('cache', 'complile', 'config', 'upgrade'),
        'mode=all'
    );
    // 缩略图命名约定探针
    $thumb = $run . '/image/w300_h200_demo.jpg';
    file_put_contents($thumb, 'thumb');
    TestAssert::true(purge_runtime_cache($run, 'all', false), 'all mode again');
    TestAssert::true(file_exists($thumb), 'thumbnail direct path survives repeated clear');
    path_delete($run, true);

    echo "=== purge_runtime_cache(sys): keep image + cache ===\n";
    $run = clear_cache_tmp_run('sys');
    $probes = clear_cache_seed_probes($run, $allDirs);
    TestAssert::true(purge_runtime_cache($run, 'sys', false), 'sys mode returns true');
    clear_cache_assert_probes(
        $probes,
        array('image', 'session', 'data', 'cache'),
        array('complile', 'config', 'upgrade'),
        'mode=sys'
    );
    path_delete($run, true);

    echo "=== purge_runtime_cache(delall): wipe including image ===\n";
    $run = clear_cache_tmp_run('delall');
    $probes = clear_cache_seed_probes($run, $allDirs);
    $thumb = $run . '/image/w100_h100_x.jpg';
    file_put_contents($thumb, 'thumb');
    TestAssert::true(purge_runtime_cache($run, 'all', true), 'delall returns true');
    clear_cache_assert_probes(
        $probes,
        array(),
        array('cache', 'complile', 'config', 'upgrade', 'image', 'session', 'data'),
        'delall'
    );
    TestAssert::false(file_exists($thumb), 'delall removes thumbnail file');
    path_delete($run, true);

    echo "=== repeated operations stable ===\n";
    $run = clear_cache_tmp_run('repeat');
    $probes = clear_cache_seed_probes($run, $allDirs);
    for ($i = 0; $i < 3; $i++) {
        TestAssert::true(purge_runtime_cache($run, 'all', false), 'repeat all #' . ($i + 1));
        TestAssert::true(purge_runtime_cache($run, 'sys', false), 'repeat sys #' . ($i + 1));
    }
    clear_cache_assert_probes(
        $probes,
        array('image', 'session', 'data'),
        array('cache', 'complile', 'config', 'upgrade'),
        'repeat'
    );
    path_delete($run, true);
});
