<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/home/controller/ParserController.php parserSortLabel / parserSpecifyContentLabel
 *
 * fix #233：父栏目外链时 {sort:parentlink} 读 parent_sort；
 * {pboot:content} 上下篇传入栏目对象，避免 Notice/Warning
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

defined('SITE_DIR') || define('SITE_DIR', '');
defined('SITE_INDEX_DIR') || define('SITE_INDEX_DIR', '');
defined('STATIC_DIR') || define('STATIC_DIR', '/static');

/**
 * 栏目/内容查询 stub
 */
final class SortParentlinkModelStub
{
    /** @var array<string, object> */
    public $sorts = array();

    /** @var object|false */
    public $content = false;

    /** @var array<int|string, object> id => content row（多块场景） */
    public $contents = array();

    /** @var object|false */
    public $pre = false;

    /** @var object|false */
    public $next = false;

    /** @var array<string, object|false> "scode-id" => row */
    public $pres = array();

    /** @var array<string, object|false> "scode-id" => row */
    public $nexts = array();

    /** @var array */
    public $getSortCalls = array();

    /** @var array */
    public $preCalls = array();

    /** @var array */
    public $nextCalls = array();

    public function getSort($scode)
    {
        $this->getSortCalls[] = $scode;
        if (isset($this->sorts[$scode])) {
            return $this->sorts[$scode];
        }
        return false;
    }

    public function getSortTopScode($scode)
    {
        return '1';
    }

    public function getSortName($scode)
    {
        return isset($this->sorts[$scode]) ? $this->sorts[$scode]->name : '';
    }

    public function getSortRows($scode)
    {
        return 0;
    }

    public function getContent($id)
    {
        if (isset($this->contents[$id])) {
            return $this->contents[$id];
        }
        return $this->content;
    }

    public function getAbout($scode)
    {
        return $this->content;
    }

    public function getContentPre($scode, $id)
    {
        $this->preCalls[] = array($scode, $id);
        $key = $scode . '-' . $id;
        if (array_key_exists($key, $this->pres)) {
            return $this->pres[$key];
        }
        return $this->pre;
    }

    public function getContentNext($scode, $id)
    {
        $this->nextCalls[] = array($scode, $id);
        $key = $scode . '-' . $id;
        if (array_key_exists($key, $this->nexts)) {
            return $this->nexts[$key];
        }
        return $this->next;
    }
}

/**
 * 覆盖 parserLink，避免 Url/Config 依赖
 */
final class SortParentlinkParserDouble extends \app\home\controller\ParserController
{
    public function parserLink($type, $urlname, $pagetype, $scode, $sortfilename, $id = '', $contentfilename = '')
    {
        return 'LINK:' . $scode . ($id !== '' && $id !== null ? '/' . $id : '');
    }
}

/**
 * @return object
 */
function sort_row($scode, $pcode, $outlink, $name = '')
{
    $row = new stdClass();
    $row->scode = $scode;
    $row->pcode = $pcode;
    $row->outlink = $outlink;
    $row->name = $name !== '' ? $name : $scode;
    $row->parentname = 'parent-' . $pcode;
    $row->type = 2;
    $row->urlname = 'list';
    $row->filename = '';
    $row->ico = '';
    $row->pic = '';
    return $row;
}

/**
 * @return object
 */
function content_row($id, $scode)
{
    $row = new stdClass();
    $row->id = $id;
    $row->scode = $scode;
    $row->title = 'Doc-' . $id;
    $row->type = 2;
    $row->outlink = '';
    $row->filename = '';
    $row->sortfilename = '';
    $row->urlname = 'list';
    $row->ico = '';
    $row->pics = '';
    $row->content = '';
    $row->keywords = '';
    $row->description = '';
    $row->subscode = '';
    $row->subsortname = '';
    $row->sortname = 'Sort-' . $scode;
    $row->sortoutlink = '';
    $row->subfilename = '';
    return $row;
}

/**
 * @return SortParentlinkParserDouble
 */
function sort_parentlink_harness(SortParentlinkModelStub $model)
{
    $refClass = new ReflectionClass(SortParentlinkParserDouble::class);
    $parser = $refClass->newInstanceWithoutConstructor();

    $modelProp = $refClass->getProperty('model');
    if (PHP_VERSION_ID < 80100) {
        $modelProp->setAccessible(true);
    }
    $modelProp->setValue($parser, $model);

    $preProp = $refClass->getProperty('pre');
    if (PHP_VERSION_ID < 80100) {
        $preProp->setAccessible(true);
    }
    $preProp->setValue($parser, array());

    $varProp = $refClass->getProperty('var');
    if (PHP_VERSION_ID < 80100) {
        $varProp->setAccessible(true);
    }
    $varProp->setValue($parser, array());

    return $parser;
}

/**
 * @param callable $fn
 * @return array{0:mixed,1:array<int,string>}
 */
function with_error_capture($fn)
{
    $errors = array();
    set_error_handler(function ($errno, $errstr) use (&$errors) {
        $errors[] = $errno . ':' . $errstr;
        return true;
    });
    $prev = error_reporting(E_ALL);
    try {
        $result = $fn();
    } finally {
        error_reporting($prev);
        restore_error_handler();
    }
    return array($result, $errors);
}

return TestAssert::runSuite(function () {
    // 使用数字编码：PHP7 下非数字字符串 == 0 为真，与线上 scode 一致
    $tree = array(
        '1' => sort_row('1', 0, 'https://top.example/', 'Top'),
        '2' => sort_row('2', '1', 'https://parent.example/', 'Parent'),
        '3' => sort_row('3', '2', '', 'Child'),
    );
    $current = $tree['3'];

    echo "=== parent outlink: parentlink alone ===\n";

    $model = new SortParentlinkModelStub();
    $model->sorts = $tree;
    $parser = sort_parentlink_harness($model);
    list($out, $errors) = with_error_capture(function () use ($parser, $current) {
        return $parser->parserSortLabel('{sort:parentlink}', $current);
    });
    TestAssert::same('https://parent.example/', $out, 'alone: parent outlink');
    TestAssert::same(0, count($errors), 'alone: no Notice/Warning');
    TestAssert::true(in_array('2', $model->getSortCalls, true), 'alone: loaded parent 2');

    echo "=== parent outlink: toplink then parentlink ===\n";

    $model2 = new SortParentlinkModelStub();
    $model2->sorts = $tree;
    $parser2 = sort_parentlink_harness($model2);
    list($out2, $errors2) = with_error_capture(function () use ($parser2, $current) {
        return $parser2->parserSortLabel('{sort:toplink}|{sort:parentlink}', $current);
    });
    TestAssert::same('https://top.example/|https://parent.example/', $out2, 'order: top then parent outlinks');
    TestAssert::same(0, count($errors2), 'order: no Notice/Warning');

    echo "=== parent outlink: parentlink then toplink ===\n";

    $model3 = new SortParentlinkModelStub();
    $model3->sorts = $tree;
    $parser3 = sort_parentlink_harness($model3);
    list($out3, $errors3) = with_error_capture(function () use ($parser3, $current) {
        return $parser3->parserSortLabel('{sort:parentlink}|{sort:toplink}', $current);
    });
    TestAssert::same('https://parent.example/|https://top.example/', $out3, 'reverse order: parent then top');
    TestAssert::same(0, count($errors3), 'reverse: no Notice/Warning');

    echo "=== parent non-outlink: behavior unchanged ===\n";

    $treeNoOut = $tree;
    $treeNoOut['2'] = sort_row('2', '1', '', 'Parent');
    $model4 = new SortParentlinkModelStub();
    $model4->sorts = $treeNoOut;
    $parser4 = sort_parentlink_harness($model4);
    list($out4, $errors4) = with_error_capture(function () use ($parser4, $current) {
        return $parser4->parserSortLabel('{sort:parentlink}', $current);
    });
    TestAssert::same('LINK:2', $out4, 'non-outlink: parserLink with parent scode');
    TestAssert::same(0, count($errors4), 'non-outlink: no Notice/Warning');

    echo "=== pboot:content prev/next: no warning + correct scode ===\n";

    $model5 = new SortParentlinkModelStub();
    $model5->sorts = $tree;
    $model5->content = content_row(10, '2');
    $pre = new stdClass();
    $pre->id = 9;
    $pre->title = 'Prev';
    $pre->type = 2;
    $pre->urlname = 'list';
    $pre->scode = '2';
    $pre->sortfilename = '';
    $pre->filename = '';
    $pre->ico = '';
    $model5->pre = $pre;
    $next = clone $pre;
    $next->id = 11;
    $next->title = 'Next';
    $model5->next = $next;

    $parser5 = sort_parentlink_harness($model5);
    $tpl = "{pboot:content id=10}"
        . "[content:precontent]|[content:nextcontent]|[content:prelink]|[content:nextlink]"
        . "{/pboot:content}";
    list($out5, $errors5) = with_error_capture(function () use ($parser5, $tpl) {
        return $parser5->parserSpecifyContentLabel($tpl);
    });

    TestAssert::same(0, count($errors5), 'content prev/next: no Notice/Warning'
        . (count($errors5) ? ' (' . implode('; ', $errors5) . ')' : ''));
    TestAssert::true(count($model5->preCalls) >= 1, 'content: getContentPre called');
    TestAssert::true(count($model5->nextCalls) >= 1, 'content: getContentNext called');
    TestAssert::same('2', (string) $model5->preCalls[0][0], 'content: pre uses sort scode 2');
    TestAssert::same('2', (string) $model5->nextCalls[0][0], 'content: next uses sort scode 2');
    TestAssert::contains($out5, 'Prev', 'content: pre title rendered');
    TestAssert::contains($out5, 'Next', 'content: next title rendered');
    TestAssert::contains($out5, 'LINK:2/9', 'content: prelink');
    TestAssert::contains($out5, 'LINK:2/11', 'content: nextlink');
    TestAssert::notContains($out5, '没有了！', 'content: not fallback empty text');
    TestAssert::true(in_array('2', $model5->getSortCalls, true), 'content: getSort(data->scode)');

    echo "=== pboot:content: no inner tags skips getSort ===\n";

    $model6 = new SortParentlinkModelStub();
    $model6->sorts = $tree;
    $model6->content = content_row(10, '2');
    $parser6 = sort_parentlink_harness($model6);
    $tplEmpty = '{pboot:content id=10}plain{/pboot:content}';
    list($out6, $errors6) = with_error_capture(function () use ($parser6, $tplEmpty) {
        return $parser6->parserSpecifyContentLabel($tplEmpty);
    });
    TestAssert::same('plain', $out6, 'no inner tags: body kept');
    TestAssert::same(0, count($model6->getSortCalls), 'no inner tags: getSort skipped');
    TestAssert::same(0, count($errors6), 'no inner tags: no Notice/Warning');

    echo "=== pboot:content: two blocks keep distinct prev ===\n";

    $treeMulti = $tree;
    $treeMulti['4'] = sort_row('4', '1', '', 'News');
    $treeMulti['6'] = sort_row('6', '1', '', 'Product');

    $preA = new stdClass();
    $preA->id = 5;
    $preA->title = 'Prev-A-5';
    $preA->type = 2;
    $preA->urlname = 'list';
    $preA->scode = '4';
    $preA->sortfilename = '';
    $preA->filename = '';
    $preA->ico = '';

    $preB = clone $preA;
    $preB->id = 10;
    $preB->title = 'Prev-B-10';
    $preB->scode = '6';

    $model7 = new SortParentlinkModelStub();
    $model7->sorts = $treeMulti;
    $model7->contents = array(
        6 => content_row(6, '4'),
        11 => content_row(11, '6'),
    );
    $model7->pres = array(
        '4-6' => $preA,
        '6-11' => $preB,
    );

    $parser7 = sort_parentlink_harness($model7);
    $tplMulti = 'A:{pboot:content id=6}[content:precontent]{/pboot:content}'
        . 'B:{pboot:content id=11}[content:precontent]{/pboot:content}';
    list($out7, $errors7) = with_error_capture(function () use ($parser7, $tplMulti) {
        return $parser7->parserSpecifyContentLabel($tplMulti);
    });

    TestAssert::same(0, count($errors7), 'two blocks: no Notice/Warning'
        . (count($errors7) ? ' (' . implode('; ', $errors7) . ')' : ''));
    TestAssert::same(2, count($model7->preCalls), 'two blocks: getContentPre twice');
    TestAssert::same(array('4', 6), array((string) $model7->preCalls[0][0], (int) $model7->preCalls[0][1]), 'block A args');
    TestAssert::same(array('6', 11), array((string) $model7->preCalls[1][0], (int) $model7->preCalls[1][1]), 'block B args');
    TestAssert::contains($out7, 'Prev-A-5', 'block A prev title');
    TestAssert::contains($out7, 'Prev-B-10', 'block B prev title');
    TestAssert::true(
        strpos($out7, 'Prev-A-5') !== false && strpos($out7, 'Prev-B-10') !== false
            && strpos($out7, 'Prev-A-5') < strpos($out7, 'Prev-B-10'),
        'two blocks: distinct titles in order'
    );
});
