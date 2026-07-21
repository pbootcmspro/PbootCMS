<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers AreaController add/mod 仍走区域编码与域名过滤
 *
 * 审计 #6：防止 add/mod 过滤链路回归
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== AreaController source filter invariants ===\n";

    $src = file_get_contents(APP_PATH . '/admin/controller/system/AreaController.php');
    TestAssert::false($src === false, 'AreaController source readable');

    $posAdd = strpos($src, 'function add(');
    $posMod = strpos($src, 'function mod(');
    $posDel = strpos($src, 'function del(');
    TestAssert::false($posAdd === false, 'add() found');
    TestAssert::false($posMod === false, 'mod() found');
    TestAssert::false($posDel === false, 'del() found');
    TestAssert::true($posAdd < $posMod, 'add before mod');

    $addBody = substr($src, $posAdd, $posMod - $posAdd);
    $modBody = substr($src, $posMod, $posDel > $posMod ? $posDel - $posMod : strlen($src));

    echo "=== add() uses var + filter_area_* ===\n";
    TestAssert::contains($addBody, "post('acode', 'var')", 'add: acode via post var');
    TestAssert::contains($addBody, 'filter_area_acode(', 'add: filter_area_acode');
    TestAssert::contains($addBody, 'filter_area_domain(', 'add: filter_area_domain');
    TestAssert::notContains($addBody, '$_POST[\'acode\']', 'add: no raw $_POST acode');
    TestAssert::notContains($addBody, '$_POST["acode"]', 'add: no raw $_POST acode dq');

    echo "=== mod() uses var + filter_area_* ===\n";
    TestAssert::contains($modBody, "post('acode', 'var')", 'mod: acode via post var');
    TestAssert::contains($modBody, 'filter_area_acode(', 'mod: filter_area_acode');
    TestAssert::contains($modBody, 'filter_area_domain(', 'mod: filter_area_domain');
    TestAssert::notContains($modBody, '$_POST[\'acode\']', 'mod: no raw $_POST acode');

    echo "=== helpers exist ===\n";
    $handle = file_get_contents(CORE_PATH . '/function/handle.php');
    TestAssert::false($handle === false, 'handle.php readable');
    TestAssert::contains($handle, 'function filter_area_acode(', 'filter_area_acode defined');
    TestAssert::contains($handle, 'function filter_area_domain(', 'filter_area_domain defined');
    TestAssert::contains($handle, '/^[a-zA-Z0-9\\-\\.]+$/', 'acode charset regex in handle.php');

    echo "=== helper.php var type still present ===\n";
    $helper = file_get_contents(CORE_PATH . '/function/helper.php');
    TestAssert::false($helper === false, 'helper.php readable');
    TestAssert::contains($helper, "case 'var':", 'var type case present');
    TestAssert::contains($helper, '/^[\\w\\-\\.]+$/', 'var type regex present');
});
