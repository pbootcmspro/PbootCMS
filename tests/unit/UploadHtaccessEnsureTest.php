<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/file.php upload_ensure_htaccess upload_htaccess_content_is_valid
 */

if (! function_exists('upload_ensure_htaccess')) {
    require dirname(__DIR__) . '/bootstrap_upload.php';
}

return TestAssert::runSuite(function () {
    echo "=== template is valid ===\n";

    $tplPath = upload_htaccess_template_path();
    TestAssert::true(is_file($tplPath), 'rewrite/static-upload.htaccess exists');
    $tpl = file_get_contents($tplPath);
    TestAssert::true(upload_htaccess_content_is_valid($tpl), 'template content valid');

    echo "=== ensure deploys to static/upload/.htaccess ===\n";

    $dest = upload_htaccess_deploy_path();
    TestAssert::true($dest !== '', 'deploy path resolved');
    $backup = null;
    if (is_file($dest)) {
        $backup = file_get_contents($dest);
        @unlink($dest);
    }
    unset($GLOBALS['__upload_htaccess_ensured']);
    TestAssert::false(is_file($dest), 'dest removed for redeploy test');
    TestAssert::true(upload_ensure_htaccess() === true, 'upload_ensure_htaccess returns true');
    TestAssert::true(is_file($dest), 'static/upload/.htaccess created');
    $deployed = file_get_contents($dest);
    TestAssert::true(upload_htaccess_content_is_valid($deployed), 'deployed content valid');
    TestAssert::contains($deployed, 'image/svg+xml', 'deployed has svg mime');
    TestAssert::contains($deployed, 'image/avif', 'deployed has avif mime');
    TestAssert::contains($deployed, 'nosniff', 'deployed has nosniff');

    echo "=== ensure is idempotent when already valid ===\n";

    TestAssert::true(upload_ensure_htaccess() === true, 'second ensure still true');
    TestAssert::true(upload_htaccess_content_is_valid(file_get_contents($dest)), 'still valid after second ensure');

    echo "=== ensure does not overwrite custom/broken dest without repair ===\n";

    file_put_contents($dest, "# broken\n");
    unset($GLOBALS['__upload_htaccess_ensured']);
    TestAssert::false(upload_htaccess_content_is_valid(file_get_contents($dest)), 'broken dest invalid');
    TestAssert::true(upload_ensure_htaccess() === true, 'ensure without repair still true');
    TestAssert::false(upload_htaccess_content_is_valid(file_get_contents($dest)), 'broken dest not overwritten');

    echo "=== ensure(true) repairs invalid dest ===\n";

    TestAssert::true(upload_ensure_htaccess(true) === true, 'repair ensure true');
    TestAssert::true(upload_htaccess_content_is_valid(file_get_contents($dest)), 'repaired content valid');

    // 保留有效部署文件；若测试前有备份且不同则不强制还原（以模板为准）
    unset($backup);
});
