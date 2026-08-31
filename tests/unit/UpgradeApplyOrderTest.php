<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers upgrade_apply_file_copies()
 * @covers UpgradeController update() version.php 延后写入
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require TEST_ROOT . 'support/UpgradeApplyHarness.php';

return TestAssert::runSuite(function () {
    echo "=== isVersionStatusFile 与控制器正则一致 ===\n";

    $upgrade = file_get_contents(APP_PATH . '/admin/controller/system/UpgradeController.php');
    TestAssert::false($upgrade === false, 'UpgradeController readable');
    TestAssert::contains($upgrade, 'function isVersionStatusFile(', 'controller helper exists');
    TestAssert::contains($upgrade, 'apps/common/version\\.php', 'controller version path pattern');

    TestAssert::true(upgrade_is_version_status_file('/apps/common/version.php'), 'matches version.php');
    TestAssert::true(upgrade_is_version_status_file('apps/common/version.php'), 'matches without leading slash');
    TestAssert::false(upgrade_is_version_status_file('/apps/common/version.php.bak'), 'rejects backup name');

    echo "=== 成功时 version.php 最后写入 ===\n";

    $list = array(
        '/apps/common/version.php',
        '/core/function/handle.php',
        '/apps/admin/controller/system/UpgradeController.php',
    );
    $order = array();
    $ok = upgrade_apply_file_copies($list, static function ($rel) {
        return true;
    }, $order);
    TestAssert::true($ok, 'apply succeeds');
    TestAssert::same('/apps/common/version.php', end($order), 'version.php copied last');

    echo "=== 普通文件 copy 失败时 version.php 不写入 ===\n";

    $order = array();
    $versionTouched = false;
    $ok = upgrade_apply_file_copies(
        array(
            '/core/function/handle.php',
            '/apps/common/version.php',
        ),
        static function ($rel) use (&$order, &$versionTouched) {
            if ($rel === '/core/function/handle.php') {
                return false;
            }
            if ($rel === '/apps/common/version.php') {
                $versionTouched = true;
            }
            return true;
        },
        $order
    );
    TestAssert::false($ok, 'apply aborts on middle failure');
    TestAssert::false($versionTouched, 'version copy callback never run');
    TestAssert::same(array(), $order, 'no successful copies recorded');

    echo "=== 文件系统：中途失败后 version 目标不存在 ===\n";

    $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot_upgrade_' . mt_rand(10000, 99999);
    if (! @mkdir($base, 0755, true)) {
        echo "SKIP: cannot create temp dir\n";
        return;
    }

    $runUpgrade = $base . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'upgrade';
    $coreDir = $base . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'function';
    $verDir = $base . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . 'common';
    mkdir($runUpgrade, 0755, true);
    mkdir($coreDir, 0755, true);
    mkdir($verDir, 0755, true);

    file_put_contents($runUpgrade . DIRECTORY_SEPARATOR . 'handle.php', 'new-handle');
    file_put_contents($runUpgrade . DIRECTORY_SEPARATOR . 'version.php', 'new-version');
    file_put_contents($coreDir . DIRECTORY_SEPARATOR . 'handle.php', 'old-handle');
    file_put_contents($verDir . DIRECTORY_SEPARATOR . 'version.php', 'old-version');

    $failDest = $coreDir . DIRECTORY_SEPARATOR . 'handle.php';
    $verDest = $verDir . DIRECTORY_SEPARATOR . 'version.php';

    $order = array();
    $ok = upgrade_apply_file_copies(
        array(
            '/core/function/handle.php',
            '/apps/common/version.php',
        ),
        static function ($rel) use ($runUpgrade, $base, $failDest, $verDest, &$order) {
            $map = array(
                '/core/function/handle.php' => array(
                    $runUpgrade . DIRECTORY_SEPARATOR . 'handle.php',
                    $failDest,
                ),
                '/apps/common/version.php' => array(
                    $runUpgrade . DIRECTORY_SEPARATOR . 'version.php',
                    $verDest,
                ),
            );
            if (! isset($map[$rel])) {
                return false;
            }
            list($src, $dest) = $map[$rel];
            if ($rel === '/core/function/handle.php') {
                return false;
            }
            if (@copy($src, $dest)) {
                $order[] = $rel;
                return true;
            }
            return false;
        },
        $order
    );

    TestAssert::false($ok, 'filesystem apply fails');
    TestAssert::same('old-version', file_get_contents($verDest), 'version.php unchanged on failure');
    TestAssert::same('old-handle', file_get_contents($failDest), 'ordinary file unchanged on failed copy');

    @unlink($runUpgrade . DIRECTORY_SEPARATOR . 'handle.php');
    @unlink($runUpgrade . DIRECTORY_SEPARATOR . 'version.php');
    @unlink($coreDir . DIRECTORY_SEPARATOR . 'handle.php');
    @unlink($verDir . DIRECTORY_SEPARATOR . 'version.php');
    @rmdir($coreDir);
    @rmdir(dirname($coreDir));
    @rmdir($verDir);
    @rmdir(dirname($verDir));
    @rmdir(dirname($runUpgrade));
    @rmdir($base);
});
