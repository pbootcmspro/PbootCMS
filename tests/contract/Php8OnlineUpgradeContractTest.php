<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers Issue #179 PHP 8 在线升级：错误可观测、count 安全、getmypid 降级、version.php 延后
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== Response::json 不对非 Countable 对象调用 count() ===\n";

    $response = file_get_contents(CORE_PATH . '/basic/Response.php');
    TestAssert::false($response === false, 'Response.php readable');
    TestAssert::contains($response, '$data instanceof \\Countable', 'rowtotal uses Countable check');
    TestAssert::notContains($response, 'if (is_array($data) || is_object($data))', 'no bare is_object+count path');

    echo "=== process_instance_id + 生产调用点不再裸调 getmypid ===\n";

    $handle = file_get_contents(CORE_PATH . '/function/handle.php');
    TestAssert::false($handle === false, 'handle.php readable');
    TestAssert::contains($handle, 'function process_instance_id()', 'process_instance_id defined');
    TestAssert::contains($handle, "function_exists('getmypid')", 'process_instance_id guards getmypid');

    $visits = file_get_contents(APP_PATH . '/common/VisitsCounter.php');
    TestAssert::false($visits === false, 'VisitsCounter readable');
    TestAssert::contains($visits, '\\process_instance_id()', 'VisitsCounter uses process_instance_id');
    TestAssert::false(strpos($visits, 'getmypid()') !== false, 'VisitsCounter has no bare getmypid()');

    $index = file_get_contents(APP_PATH . '/admin/controller/IndexController.php');
    TestAssert::false($index === false, 'IndexController readable');
    TestAssert::contains($index, '\\process_instance_id()', 'IndexController uses process_instance_id');
    TestAssert::false(preg_match('/(?<!function_exists\\(\')getmypid\\s*\\(/', $index) === 1, 'IndexController has no bare getmypid()');

    echo "=== UpgradeController：无 error_reporting(0)、version.php 延后、类型守卫 ===\n";

    $upgrade = file_get_contents(APP_PATH . '/admin/controller/system/UpgradeController.php');
    TestAssert::false($upgrade === false, 'UpgradeController readable');
    TestAssert::notContains($upgrade, 'error_reporting(0);', 'upgrade does not hide errors with error_reporting(0)');
    TestAssert::contains($upgrade, 'function isVersionStatusFile(', 'version status helper exists');
    TestAssert::contains($upgrade, '$versionFiles', 'version files deferred list');
    TestAssert::contains($upgrade, 'isVersionStatusFile($value)', 'update() partitions version files');
    TestAssert::contains($upgrade, 'base64_decode($rs->data, true)', 'strict base64 decode first');
    TestAssert::contains($upgrade, "is_string(\$rs->data)", 'getServerFile checks data type');
    TestAssert::contains($upgrade, "响应无法解析", 'invalid JSON surfaces actionable message');
    TestAssert::notContains($upgrade, "json(0, '目录写入权限不足，无法正常升级！' . RUN_PATH", 'no RUN_PATH leak in permission error');

    echo "=== Model::insertGetId PHP 8.4+ 隐式 nullable 已修复 ===\n";

    $model = file_get_contents(CORE_PATH . '/basic/Model.php');
    TestAssert::false($model === false, 'Model.php readable');
    TestAssert::notContains(
        $model,
        'function insertGetId(array $data = null',
        'insertGetId no implicit nullable array param'
    );

    echo "=== upgrade.html 展示真实 AJAX 错误 ===\n";

    $tpl = file_get_contents(APP_PATH . '/admin/view/default/system/upgrade.html');
    TestAssert::false($tpl === false, 'upgrade.html readable');
    TestAssert::contains($tpl, 'function upgradeAjaxErrorMsg(', 'AJAX error helper present');
    TestAssert::contains($tpl, 'upgradeAjaxErrorMsg(xhr, "检查更新发生错误!")', 'check uses real error helper');
    TestAssert::contains($tpl, 'upgradeAjaxErrorMsg(xhr, "下载更新文件发生错误!")', 'down uses real error helper');
    TestAssert::contains($tpl, 'upgradeAjaxErrorMsg(xhr, "执行更新文件发生错误!")', 'update uses real error helper');

    echo "=== get_url  hardening ===\n";

    TestAssert::contains($handle, "isset(\$_SERVER['HTTP_USER_AGENT'])", 'get_url guards missing User-Agent');
    TestAssert::contains($handle, 'function curl_safe_close', 'curl_safe_close defined for PHP 8.5');
    TestAssert::contains($handle, 'curl_safe_close($ch)', 'get_url uses curl_safe_close not bare curl_close');
    TestAssert::contains($handle, '响应无效', 'get_url rejects non-string response');
    TestAssert::contains($handle, '://***:***@', 'get_url strips URL credentials from curl errors');

    echo "=== PHP8 自举补丁清单 ===\n";

    $manifest = file_get_contents(TEST_ROOT . 'fixtures/php8_upgrade_bootstrap/manifest.txt');
    TestAssert::false($manifest === false, 'bootstrap manifest readable');
    TestAssert::contains($manifest, 'core/function/handle.php', 'manifest includes handle.php');
    TestAssert::contains($manifest, 'core/basic/Model.php', 'manifest includes Model.php for SQL upgrade path');
    TestAssert::contains($manifest, 'apps/admin/controller/system/UpgradeController.php', 'manifest includes UpgradeController');
    TestAssert::contains($manifest, 'apps/admin/view/default/system/upgrade.html', 'manifest includes upgrade.html');
    TestAssert::contains($manifest, 'apps/common/VisitsCounter.php', 'manifest includes VisitsCounter');
});
