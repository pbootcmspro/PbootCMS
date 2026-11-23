<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers UpgradeController check() 缓存状态判定与清理
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== check() 不再整目录清空；update() 仍清理 ===\n";

    $upgrade = file_get_contents(APP_PATH . '/admin/controller/system/UpgradeController.php');
    TestAssert::false($upgrade === false, 'UpgradeController readable');
    TestAssert::contains($upgrade, 'function syncUpgradeCache(', 'syncUpgradeCache helper exists');
    TestAssert::contains($upgrade, 'function planUpgradeCache(', 'planUpgradeCache helper exists');
    TestAssert::contains($upgrade, "目录写入权限不足，无法正常升级：runtime/upgrade", 'permission error message unchanged');
    TestAssert::contains($upgrade, "\$item->status = '已下载'", 'marks ready files as downloaded');
    TestAssert::contains($upgrade, "\$files[\$key]->status = '待下载'", 'default status pending');

    // check() 不得再 path_delete 整个 upgrade；update() 成功后仍须清理
    $checkPos = strpos($upgrade, 'public function check()');
    $downPos = strpos($upgrade, 'public function down()');
    $updatePos = strpos($upgrade, 'public function update()');
    TestAssert::true($checkPos !== false && $downPos !== false && $updatePos !== false, 'methods located');
    $checkBody = substr($upgrade, $checkPos, $downPos - $checkPos);
    $updateBody = substr($upgrade, $updatePos);
    TestAssert::false(
        strpos($checkBody, "path_delete(RUN_PATH . '/upgrade'") !== false,
        'check() does not path_delete upgrade dir'
    );
    TestAssert::true(
        strpos($updateBody, "path_delete(RUN_PATH . '/upgrade'") !== false,
        'update() still clears upgrade cache'
    );

    echo "=== planUpgradeCache / upgradeCacheRel / syncUpgradeCache ===\n";

    require_once APP_PATH . '/admin/controller/system/UpgradeController.php';
    $ref = new ReflectionClass('app\\admin\\controller\\system\\UpgradeController');
    $controller = $ref->newInstanceWithoutConstructor();

    $plan = $ref->getMethod('planUpgradeCache');
    $rel = $ref->getMethod('upgradeCacheRel');
    $sync = $ref->getMethod('syncUpgradeCache');
    if (PHP_VERSION_ID < 80100) {
        $plan->setAccessible(true);
        $rel->setAccessible(true);
        $sync->setAccessible(true);
    }

    $goodMd5 = md5('ok-content');
    $badMd5 = md5('truncated');
    $result = $plan->invoke($controller, array(
        '/apps/a.php' => $goodMd5,
        '/apps/b.php' => $goodMd5,
    ), array(
        array('raw' => '/tmp/upgrade/apps/a.php', 'rel' => '/apps/a.php', 'md5' => $goodMd5),
        array('raw' => '/tmp/upgrade/apps/b.php', 'rel' => '/apps/b.php', 'md5' => $badMd5),
        array('raw' => '/tmp/upgrade/apps/c.php', 'rel' => '/apps/c.php', 'md5' => $goodMd5),
        array('raw' => '/tmp/upgrade/apps/d.php', 'rel' => '/apps/d.php', 'md5' => false),
    ));
    TestAssert::true(isset($result['ready']['/apps/a.php']), 'matching md5 marked ready');
    TestAssert::false(isset($result['ready']['/apps/b.php']), 'mismatched md5 not ready');
    TestAssert::same(
        array('/tmp/upgrade/apps/b.php', '/tmp/upgrade/apps/c.php', '/tmp/upgrade/apps/d.php'),
        $result['delete'],
        'mismatch, extra, and false md5 queued for delete with raw paths'
    );

    $root = str_replace('\\', '/', sys_get_temp_dir() . '/pboot_up_rel');
    $full = $root . '/apps/中文.php';
    TestAssert::same('/apps/中文.php', $rel->invoke($controller, $root, $full), 'relative path strips root');

    // 非法 UTF-8 路径：转换后比对清单，delete 仍保留原始 raw
    $rawGbkish = $root . "/apps/\xD6\xD0.php";
    $convertedRel = $rel->invoke($controller, $root, $rawGbkish);
    $planEnc = $plan->invoke($controller, array(
        $convertedRel => 'abc',
    ), array(
        array('raw' => $rawGbkish, 'rel' => $convertedRel, 'md5' => 'different'),
    ));
    TestAssert::same(array($rawGbkish), $planEnc['delete'], 'delete uses original raw path after encoding convert');

    echo "=== syncUpgradeCache 文件系统：保留一致、删除截断/清单外/空清单 ===\n";

    $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot_upgrade_cache_' . mt_rand(10000, 99999);
    $cacheRoot = $base . DIRECTORY_SEPARATOR . 'upgrade';
    $appsDir = $cacheRoot . DIRECTORY_SEPARATOR . 'apps';
    if (! @mkdir($appsDir, 0755, true)) {
        echo "SKIP: cannot create temp dir\n";
        return;
    }

    $keepPath = $appsDir . DIRECTORY_SEPARATOR . 'keep.php';
    $truncPath = $appsDir . DIRECTORY_SEPARATOR . 'trunc.php';
    $extraPath = $appsDir . DIRECTORY_SEPARATOR . 'extra.php';
    file_put_contents($keepPath, 'keep-body');
    file_put_contents($truncPath, 'truncated');
    file_put_contents($extraPath, 'extra');
    $keepMd5 = md5_file($keepPath);
    $wantMd5 = md5('full-body');

    $ready = $sync->invoke($controller, $cacheRoot, array(
        '/apps/keep.php' => $keepMd5,
        '/apps/trunc.php' => $wantMd5,
    ));
    TestAssert::true(isset($ready['/apps/keep.php']), 'sync keeps matching file in ready');
    TestAssert::true(is_file($keepPath), 'matching cache file retained');
    TestAssert::false(is_file($truncPath), 'truncated cache file deleted');
    TestAssert::false(is_file($extraPath), 'out-of-manifest cache file deleted');

    // 空清单：清空全部缓存文件，根目录保留
    file_put_contents($keepPath, 'keep-body');
    $readyEmpty = $sync->invoke($controller, $cacheRoot, array());
    TestAssert::same(array(), $readyEmpty, 'empty manifest yields no ready');
    TestAssert::false(is_file($keepPath), 'empty manifest deletes all cache files');
    TestAssert::true(is_dir($cacheRoot), 'cache root directory retained');

    @rmdir($appsDir);
    @rmdir($cacheRoot);
    @rmdir($base);

    echo "=== upgrade.html 状态列与跳过已下载 ===\n";

    $tpl = file_get_contents(APP_PATH . '/admin/view/default/system/upgrade.html');
    TestAssert::false($tpl === false, 'upgrade.html readable');
    TestAssert::contains($tpl, '>状态</th>', 'status column header');
    TestAssert::contains($tpl, 'class="up-status"', 'status cell class');
    TestAssert::contains($tpl, "\$st.text() === '已下载'", 'down skips downloaded rows');
    TestAssert::contains($tpl, "\$st.text('下载中')", 'down sets downloading status');
    TestAssert::contains($tpl, "\$st.text('已下载')", 'down sets downloaded status');
    TestAssert::contains($tpl, "\$st.text('失败')", 'down sets failed status');
    TestAssert::contains($tpl, 'pending.length === 0', 'all-downloaded short circuit');
});
