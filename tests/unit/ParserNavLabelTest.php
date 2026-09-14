<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/home/controller/ParserController.php parserNavLabel()
 *
 * Issue #235：num 与 scode 同时使用时应先按 scode 定范围，再按 num 截断。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * getSortsTree 调用 stub
 */
final class NavSortModelStub
{
    /** @var array */
    public $tree;

    public function getSortsTree()
    {
        return $this->tree;
    }

    public function getSortRows($scode)
    {
        return 0;
    }

    public function resolveSortPic($scode)
    {
        return '';
    }
}

/**
 * @param string $scode
 * @param string $name
 * @param string $pcode
 * @return array
 */
function nav_sort_row($scode, $name, $pcode = '')
{
    return array(
        'scode' => (string) $scode,
        'name' => $name,
        'pcode' => (string) $pcode,
        'outlink' => '',
        'type' => 1,
        'urlname' => 'list',
        'filename' => '',
        'ico' => '',
        'pic' => '',
    );
}

/**
 * 一级栏目 1..5；栏目 1 下挂子栏目 11,12,13,14,15
 *
 * @return array
 */
function nav_fixture_tree()
{
    $top = array();
    $tree = array();
    for ($i = 1; $i <= 5; $i++) {
        $row = nav_sort_row($i, 'Nav' . $i, '');
        $top[] = $row;
        $tree[(string) $i] = $row;
    }
    $sons = array();
    for ($i = 11; $i <= 15; $i++) {
        $row = nav_sort_row($i, 'Son' . $i, '1');
        $sons[] = $row;
        $tree[(string) $i] = $row;
    }
    $tree['1']['son'] = $sons;

    return array(
        'top' => $top,
        'tree' => $tree,
    );
}

/**
 * @param NavSortModelStub $model
 * @return \app\home\controller\ParserController
 */
function nav_parser_harness(NavSortModelStub $model)
{
    $refClass = new ReflectionClass(\app\home\controller\ParserController::class);
    $parser = $refClass->newInstanceWithoutConstructor();

    $modelProp = $refClass->getProperty('model');
    if (PHP_VERSION_ID < 80100) {
        $modelProp->setAccessible(true);
    }
    $modelProp->setValue($parser, $model);

    return $parser;
}

/**
 * @param string $out
 * @return string
 */
function nav_names_csv($out)
{
    $out = preg_replace('/\s+/', '', $out);
    return (string) $out;
}

return TestAssert::runSuite(function () {
    $model = new NavSortModelStub();
    $model->tree = nav_fixture_tree();
    $parser = nav_parser_harness($model);

    echo "=== num=2 scode=4,5 => Nav4,Nav5 ===\n";

    $out = $parser->parserNavLabel('{pboot:nav num=2 scode=4,5}[nav:name]{/pboot:nav}');
    TestAssert::same('Nav4Nav5', nav_names_csv($out), 'acceptance: range then num');

    echo "=== only num=2 => Nav1,Nav2 ===\n";

    $outNum = $parser->parserNavLabel('{pboot:nav num=2}[nav:name]{/pboot:nav}');
    TestAssert::same('Nav1Nav2', nav_names_csv($outNum), 'only num unchanged');

    echo "=== only scode=4,5 => Nav4,Nav5 ===\n";

    $outScode = $parser->parserNavLabel('{pboot:nav scode=4,5}[nav:name]{/pboot:nav}');
    TestAssert::same('Nav4Nav5', nav_names_csv($outScode), 'only scode unchanged');

    echo "=== parent=1 scode=14,15 => Son14,Son15 ===\n";

    $outParent = $parser->parserNavLabel('{pboot:nav parent=1 scode=14,15}[nav:name]{/pboot:nav}');
    TestAssert::same('Son14Son15', nav_names_csv($outParent), 'parent+scode unchanged');

    echo "=== parent=1 scode=14,15 num=1 => Son14 ===\n";

    $outParentNum = $parser->parserNavLabel('{pboot:nav parent=1 scode=14,15 num=1}[nav:name]{/pboot:nav}');
    TestAssert::same('Son14', nav_names_csv($outParentNum), 'parent+scode then num');

    echo "=== scode outside first-N still reachable ===\n";

    $outFar = $parser->parserNavLabel('{pboot:nav num=1 scode=5}[nav:name]{/pboot:nav}');
    TestAssert::same('Nav5', nav_names_csv($outFar), 'scode beyond old slice still outputs');

    echo "=== empty intersection => empty ===\n";

    $outEmpty = $parser->parserNavLabel('{pboot:nav num=2 scode=9,10}[nav:name]{/pboot:nav}');
    TestAssert::same('', nav_names_csv($outEmpty), 'no match yields empty');
});
