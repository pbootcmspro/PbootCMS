<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers ContentSortController::mod 列表改单页守卫与子栏补建
 * @covers ContentSortModel::getSortAndSubScodes / getListSortContentCounts
 * @covers ModelModel::getType
 *
 * 运行：php tests/contract/ContentSortListToSingleGuardContractTest.php
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * 截取类方法体（到下一 public function 或文件尾）
 */
function sort_single_guard_method_body($src, $method)
{
    $needle = 'function ' . $method . '(';
    $pos = strpos($src, $needle);
    if ($pos === false) {
        return null;
    }
    $next = null;
    if (preg_match('/\n\s*public function\s+\w+\s*\(/', $src, $nm, PREG_OFFSET_CAPTURE, $pos + strlen($needle))) {
        $next = $nm[0][1];
    }
    if ($next !== null && $next > $pos) {
        return substr($src, $pos, $next - $pos);
    }
    return substr($src, $pos);
}

return TestAssert::runSuite(function () {
    echo "=== ModelModel::getType ===\n";
    $model = file_get_contents(APP_PATH . '/admin/model/content/ModelModel.php');
    TestAssert::false($model === false, 'ModelModel readable');
    TestAssert::contains($model, 'function getType($mcode)', 'getType defined');
    TestAssert::contains($model, "value('type')", 'getType returns type');

    echo "=== ContentSortModel helpers ===\n";
    $sortModel = file_get_contents(APP_PATH . '/admin/model/content/ContentSortModel.php');
    TestAssert::false($sortModel === false, 'ContentSortModel readable');
    TestAssert::contains($sortModel, 'function getSortAndSubScodes($scode)', 'getSortAndSubScodes defined');
    TestAssert::contains($sortModel, 'function getListSortContentCounts($scodes)', 'getListSortContentCounts defined');
    TestAssert::contains($sortModel, 'getSortAndSubScodes($scode)', 'modSort uses getSortAndSubScodes');
    TestAssert::contains($sortModel, "select count(*) from ay_content", 'batch count via subquery');
    TestAssert::contains($sortModel, 'b.type=2', 'batch only list models');

    echo "=== ContentSortController::mod guard ===\n";
    $ctrl = file_get_contents(APP_PATH . '/admin/controller/content/ContentSortController.php');
    TestAssert::false($ctrl === false, 'ContentSortController readable');
    $modBody = sort_single_guard_method_body($ctrl, 'mod');
    TestAssert::true($modBody !== null && $modBody !== '', 'mod() body found');

    TestAssert::contains($modBody, "model('admin.content.Model')->getType(\$mcode)", 'mod resolves type from mcode');
    TestAssert::notContains($modBody, "post('type')", 'mod does not trust post type');
    TestAssert::contains($modBody, 'getListSortContentCounts($checkScodes)', 'mod batch-counts list content before convert');
    TestAssert::contains($modBody, '请先移动或删除后再改为单页模型', 'mod refuses list-to-single with articles');
    TestAssert::true(
        strpos($modBody, '请先移动或删除后再改为单页模型') !== false
        && strpos($modBody, '请先移动或删除后再改为单页模型') < strpos($modBody, 'modSort($scode, $data, $modsub)'),
        'refuse happens before modSort'
    );
    TestAssert::contains($modBody, 'getSortAndSubScodes($scode)', 'mod syncs sub single create via sub scodes');
    TestAssert::contains($modBody, '$this->addSingle($subScode, $subSort->name)', 'mod addSingle for empty sub sorts');
    TestAssert::contains($modBody, 'delContent($scode)', 'mod still deletes when outlink set');
});
