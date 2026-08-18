<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers get_server_info() PHP 版本提示链路 + PHP 版本三道声明的分工
 *
 * Issue #134：后台 PHP 版本低于 7.4 时给出软提示。
 * 本测试守护四条容易被后人"顺手改坏"的不变量：
 *  1. 判定必须走 PHP_VERSION_ID 整数比较，不得回退到 version_compare（预发布版会误判）
 *  2. $data['php_version'] 必须是裸 PHP_VERSION，不得夹带 HTML（该值会被拼进 /upgrade/check 上报）
 *  3. core/basic/Check.php 必须保持 7.0，入口文件必须保持 7.4（见 #125）
 *  4. 本版本只在两处「PHP版本」行内联提示，首页顶部提醒块不接入（暂缓，见 #134）
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    $handle = file_get_contents(CORE_PATH . '/function/handle.php');
    TestAssert::false($handle === false, 'handle.php readable');

    echo "=== get_server_info: php_version 保持裸 PHP_VERSION ===\n";
    TestAssert::contains($handle, "\$data['php_version'] = PHP_VERSION;", 'php_version is raw PHP_VERSION');
    TestAssert::notContains($handle, "\$data['php_version'] = PHP_VERSION .", 'php_version not concatenated');
    TestAssert::notContains($handle, "\$data['php_version'] .=", 'php_version not appended to');

    echo "=== get_server_info: 用 PHP_VERSION_ID 判定，不用 version_compare ===\n";
    TestAssert::contains(
        $handle,
        "\$data['php_version_low'] = PHP_VERSION_ID < 70400;",
        'php_version_low uses PHP_VERSION_ID < 70400'
    );
    TestAssert::notContains($handle, "\$data['php_version_low'] = version_compare", 'php_version_low avoids version_compare');
    TestAssert::contains($handle, "\$data['php_min_version'] = '7.4';", 'php_min_version exposed for templates');

    echo "=== version_compare 会把预发布版误判为过低，故不可回退 ===\n";
    TestAssert::true(
        version_compare('7.4.0-dev', '7.4.0', '<'),
        'version_compare misjudges 7.4.0-dev as lower than 7.4.0'
    );
    TestAssert::true(
        version_compare('7.4.0RC1', '7.4.0', '<'),
        'version_compare misjudges 7.4.0RC1 as lower than 7.4.0'
    );

    echo "=== 三道 PHP 版本声明分工未被打乱（见 #125）===\n";
    $check = file_get_contents(CORE_PATH . '/basic/Check.php');
    TestAssert::false($check === false, 'Check.php readable');
    TestAssert::contains(
        $check,
        "version_compare(phpversion(),'7.0.0','<')",
        'Check.php keeps 7.0 as the runtime floor for online-upgraded sites'
    );

    foreach (array('index.php', 'admin.php', 'api.php') as $entry) {
        $src = file_get_contents(ROOT_PATH . $entry);
        TestAssert::false($src === false, $entry . ' readable');
        TestAssert::contains(
            $src,
            "version_compare(PHP_VERSION,'7.4.0','<')",
            $entry . ' keeps 7.4 gate for new install / full package upgrade'
        );
    }

    echo "=== 后台模板：标签文案、提示条件、上报参数 ===\n";
    $views = array(
        'home' => APP_PATH . '/admin/view/default/system/home.html',
        'server' => APP_PATH . '/admin/view/default/system/server.html',
    );
    foreach ($views as $name => $path) {
        $tpl = file_get_contents($path);
        TestAssert::false($tpl === false, $name . '.html readable');
        TestAssert::contains($tpl, '<th>PHP版本</th>', $name . '.html label is PHP版本');
        TestAssert::notContains($tpl, '<th>PHP版</th>', $name . '.html has no truncated PHP版 label');
        TestAssert::contains(
            $tpl,
            '{if([$server->php_version_low])}',
            $name . '.html renders hint under php_version_low'
        );
        TestAssert::same(
            preg_match_all('/\{if\(/', $tpl),
            preg_match_all('/\{\/if\}/', $tpl),
            $name . '.html if/endif balanced'
        );
    }

    $home = file_get_contents($views['home']);
    TestAssert::contains(
        $home,
        '&php={$server->php_version}',
        'home.html still reports raw php_version to /upgrade/check (#118)'
    );

    echo "=== 本版本不接入首页顶部提醒块（暂缓）===\n";
    TestAssert::contains(
        $home,
        '{if(![$dbsecurity]||![$session.pwsecurity])}',
        'home.html top notice block condition left untouched'
    );
    TestAssert::same(
        1,
        preg_match_all('/\{if\(\[\$server->php_version_low\]\)\}/', $home),
        'home.html hints on php_version_low exactly once (inline row only)'
    );
});
