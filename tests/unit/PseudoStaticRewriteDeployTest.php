<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers deploy_pseudo_static_rewrite() / ConfigController url_rule_type=2
 *
 * Apache 须按 .htaccess 是否存在决定是否拷贝；IIS 仍按 web.config。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * @return string 临时根目录绝对路径
 */
function pseudo_static_make_root()
{
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot_rewrite_' . uniqid('', true);
    if (! mkdir($root . DIRECTORY_SEPARATOR . 'rewrite', 0777, true) && ! is_dir($root . '/rewrite')) {
        throw new \RuntimeException('mkdir failed: ' . $root);
    }
    $stockHt = "# stock htaccess\nRewriteEngine On\n";
    $stockWc = "<?xml version=\"1.0\"?><configuration/>\n";
    file_put_contents($root . '/rewrite/.htaccess', $stockHt);
    file_put_contents($root . '/rewrite/web.config', $stockWc);
    return $root;
}

/**
 * @param string $root
 */
function pseudo_static_rm_root($root)
{
    $files = array(
        $root . '/.htaccess',
        $root . '/web.config',
        $root . '/rewrite/.htaccess',
        $root . '/rewrite/web.config',
    );
    foreach ($files as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
    @rmdir($root . '/rewrite');
    @rmdir($root);
}

return TestAssert::runSuite(function () {
    echo "=== source contract ===\n";

    $ctrl = file_get_contents(APP_PATH . '/admin/controller/system/ConfigController.php');
    TestAssert::false($ctrl === false, 'ConfigController readable');
    $pos = strpos($ctrl, "if (\$key == 'url_rule_type' && \$value == 2)");
    TestAssert::false($pos === false, 'url_rule_type==2 block found');
    $posEnd = strpos($ctrl, '// 模板目录修改', $pos);
    TestAssert::false($posEnd === false, 'block end marker found');
    $block = substr($ctrl, $pos, $posEnd - $pos);
    TestAssert::contains($block, 'deploy_pseudo_static_rewrite(get_server_soft())', 'controller calls deploy helper');
    TestAssert::notContains($block, "\$soft == 'iis'", 'controller no longer inlines IIS branch');
    TestAssert::notContains($block, "\$soft == 'apache'", 'controller no longer inlines Apache branch');

    $src = file_get_contents(CORE_PATH . '/function/handle.php');
    TestAssert::false($src === false, 'handle.php readable');
    $pos = strpos($src, 'function deploy_pseudo_static_rewrite(');
    TestAssert::false($pos === false, 'deploy_pseudo_static_rewrite found');
    $posEnd = strpos($src, '// 创建会话层级目录', $pos);
    TestAssert::false($posEnd === false, 'function end marker found');
    $fn = substr($src, $pos, $posEnd - $pos);

    TestAssert::contains($fn, "\$soft == 'iis'", 'IIS branch present');
    TestAssert::contains($fn, "\$soft == 'apache'", 'Apache branch present');

    $posIis = strpos($fn, "\$soft == 'iis'");
    $posApache = strpos($fn, "\$soft == 'apache'");
    TestAssert::false($posIis === false, 'IIS soft compare found');
    TestAssert::false($posApache === false, 'Apache soft compare found');
    TestAssert::true($posIis < $posApache, 'IIS branch before Apache');

    $iisBody = substr($fn, $posIis, $posApache - $posIis);
    $apacheBody = substr($fn, $posApache);

    TestAssert::contains($iisBody, "file_exists(\$root . '/web.config')", 'IIS: guard on web.config');
    TestAssert::contains($iisBody, "copy(\$root . '/rewrite/web.config', \$root . '/web.config')", 'IIS: copy web.config');
    TestAssert::notContains($iisBody, "file_exists(\$root . '/.htaccess')", 'IIS: no .htaccess guard');

    TestAssert::contains($apacheBody, "file_exists(\$root . '/.htaccess')", 'Apache: guard on .htaccess');
    TestAssert::contains($apacheBody, "copy(\$root . '/rewrite/.htaccess', \$root . '/.htaccess')", 'Apache: copy .htaccess');
    TestAssert::notContains($apacheBody, "file_exists(\$root . '/web.config')", 'Apache: must not guard on web.config');

    echo "=== get_server_soft detection ===\n";

    // 详细回归见 GetServerSoftTest；此处覆盖伪静态部署会用到的识别结果
    $prev = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : null;
    $_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4.58 (Win64)';
    TestAssert::same('apache', get_server_soft(), 'Apache at string start');
    $_SERVER['SERVER_SOFTWARE'] = 'Win64 Apache/2.4.58';
    TestAssert::same('apache', get_server_soft(), 'Apache mid-string');
    $_SERVER['SERVER_SOFTWARE'] = 'Microsoft-IIS/10.0';
    TestAssert::same('iis', get_server_soft(), 'IIS SERVER_SOFTWARE');
    if ($prev === null) {
        unset($_SERVER['SERVER_SOFTWARE']);
    } else {
        $_SERVER['SERVER_SOFTWARE'] = $prev;
    }

    echo "=== Apache: existing .htaccess not overwritten ===\n";

    $root = pseudo_static_make_root();
    try {
        $custom = "# custom 301\nRewriteRule ^old$ /new [R=301,L]\n";
        file_put_contents($root . '/.htaccess', $custom);
        deploy_pseudo_static_rewrite('apache', $root);
        TestAssert::same($custom, file_get_contents($root . '/.htaccess'), 'Apache preserves custom .htaccess');
        TestAssert::false(file_exists($root . '/web.config'), 'Apache does not create web.config');
    } finally {
        pseudo_static_rm_root($root);
    }

    echo "=== Apache: missing .htaccess is generated ===\n";

    $root = pseudo_static_make_root();
    try {
        TestAssert::false(file_exists($root . '/.htaccess'), 'precondition: no root .htaccess');
        deploy_pseudo_static_rewrite('apache', $root);
        TestAssert::true(file_exists($root . '/.htaccess'), 'Apache generates .htaccess');
        TestAssert::same(
            file_get_contents($root . '/rewrite/.htaccess'),
            file_get_contents($root . '/.htaccess'),
            'Apache copies stock rewrite/.htaccess'
        );
    } finally {
        pseudo_static_rm_root($root);
    }

    echo "=== IIS: existing web.config not overwritten ===\n";

    $root = pseudo_static_make_root();
    try {
        $customWc = "<?xml version=\"1.0\"?><configuration><!--custom--></configuration>\n";
        file_put_contents($root . '/web.config', $customWc);
        // 根目录无 .htaccess，旧 bug 在 Apache 误判下会覆盖；IIS 不应动 .htaccess
        deploy_pseudo_static_rewrite('iis', $root);
        TestAssert::same($customWc, file_get_contents($root . '/web.config'), 'IIS preserves custom web.config');
        TestAssert::false(file_exists($root . '/.htaccess'), 'IIS does not create .htaccess');
    } finally {
        pseudo_static_rm_root($root);
    }

    echo "=== IIS: missing web.config is generated ===\n";

    $root = pseudo_static_make_root();
    try {
        TestAssert::false(file_exists($root . '/web.config'), 'precondition: no root web.config');
        // 即使存在无关的 .htaccess，IIS 仍应部署 web.config（与 Apache 对称、且无回归）
        file_put_contents($root . '/.htaccess', "# unrelated\n");
        deploy_pseudo_static_rewrite('iis', $root);
        TestAssert::true(file_exists($root . '/web.config'), 'IIS generates web.config');
        TestAssert::same(
            file_get_contents($root . '/rewrite/web.config'),
            file_get_contents($root . '/web.config'),
            'IIS copies stock rewrite/web.config'
        );
        TestAssert::same("# unrelated\n", file_get_contents($root . '/.htaccess'), 'IIS leaves .htaccess alone');
    } finally {
        pseudo_static_rm_root($root);
    }

    echo "=== regression: Apache must not skip deploy when only web.config exists ===\n";

    $root = pseudo_static_make_root();
    try {
        file_put_contents($root . '/web.config', "<?xml version=\"1.0\"?><configuration/>\n");
        TestAssert::false(file_exists($root . '/.htaccess'), 'precondition: no .htaccess');
        deploy_pseudo_static_rewrite('apache', $root);
        TestAssert::true(file_exists($root . '/.htaccess'), 'Apache still deploys .htaccess despite web.config');
        TestAssert::same(
            file_get_contents($root . '/rewrite/.htaccess'),
            file_get_contents($root . '/.htaccess'),
            'deployed content is stock .htaccess'
        );
    } finally {
        pseudo_static_rm_root($root);
    }
});
