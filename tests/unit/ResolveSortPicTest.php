<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/home/model/ParserModel.php resolveSortPic
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once CORE_PATH . '/basic/Model.php';
require_once APP_PATH . '/home/model/ParserModel.php';

/**
 * @param array<string, array<string, mixed>> $sorts
 */
function resolve_sort_pic_with(array $sorts, string $scode): string
{
    $ref = new ReflectionClass('app\home\model\ParserModel');
    $model = $ref->newInstanceWithoutConstructor();
    $prop = $ref->getProperty('sorts');
    if (PHP_VERSION_ID < 80100) {
        $prop->setAccessible(true);
    }
    $prop->setValue($model, $sorts);
    return $model->resolveSortPic($scode);
}

return TestAssert::runSuite(function () {
    $tree = array(
        'A' => array('scode' => 'A', 'pcode' => '', 'pic' => '/static/upload/a.jpg'),
        'B' => array('scode' => 'B', 'pcode' => 'A', 'pic' => ''),
        'C' => array('scode' => 'C', 'pcode' => 'B', 'pic' => ''),
        'D' => array('scode' => 'D', 'pcode' => 'B', 'pic' => '/static/upload/d.jpg'),
        'E' => array('scode' => 'E', 'pcode' => 'A', 'pic' => ''),
        'F' => array('scode' => 'F', 'pcode' => 'E', 'pic' => ''),
    );
    // E 无图、F 无图；父级 E 无图，顶级 A 有图 → 回退顶级
    // B 作为 C 的父级无图时，应回退顶级 A

    echo "=== resolveSortPic current / parent / top fallback ===\n";

    TestAssert::same(
        '/static/upload/d.jpg',
        resolve_sort_pic_with($tree, 'D'),
        'current pic preferred'
    );

    TestAssert::same(
        '/static/upload/a.jpg',
        resolve_sort_pic_with($tree, 'C'),
        'no current/parent pic → top'
    );

    $treeParentHas = $tree;
    $treeParentHas['B']['pic'] = '/static/upload/b.jpg';
    TestAssert::same(
        '/static/upload/b.jpg',
        resolve_sort_pic_with($treeParentHas, 'C'),
        'no current → parent pic'
    );

    TestAssert::same(
        '/static/upload/a.jpg',
        resolve_sort_pic_with($tree, 'F'),
        'parent empty → top pic'
    );

    TestAssert::same(
        '/static/upload/a.jpg',
        resolve_sort_pic_with($tree, 'A'),
        'top itself uses own pic'
    );

    $emptyTree = array(
        'X' => array('scode' => 'X', 'pcode' => '', 'pic' => ''),
        'Y' => array('scode' => 'Y', 'pcode' => 'X', 'pic' => ''),
    );
    TestAssert::same(
        '',
        resolve_sort_pic_with($emptyTree, 'Y'),
        'all empty → empty string'
    );

    TestAssert::same(
        '',
        resolve_sort_pic_with($tree, ''),
        'empty scode → empty'
    );

    TestAssert::same(
        '',
        resolve_sort_pic_with($tree, 'MISSING'),
        'unknown scode → empty'
    );
});
