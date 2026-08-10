<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/home/controller/ParserController.php parserSlideLabel()
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

defined('SITE_DIR') || define('SITE_DIR', '');

/**
 * getSlides 调用记录 mock
 */
final class SlideModelStub
{
    /** @var array */
    public $calls = array();

    /** @var array|false */
    public $result = array();

    public function getSlides($gid, $num, $start = 1)
    {
        $this->calls[] = array(
            'gid' => $gid,
            'num' => $num,
            'start' => $start,
        );
        return $this->result;
    }
}

function slide_parser_harness(SlideModelStub $model)
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

function slide_row($title, $link = '')
{
    $row = new stdClass();
    $row->title = $title;
    $row->link = $link;
    $row->pic = '';
    $row->id = 1;
    $row->subtitle = '';
    return $row;
}

return TestAssert::runSuite(function () {
    echo "=== bare {pboot:slide} uses default gid=1 ===\n";

    $model = new SlideModelStub();
    $model->result = array(slide_row('Banner A'));
    $parser = slide_parser_harness($model);

    $tpl = "{pboot:slide}\n<img alt=\"[slide:title]\">\n{/pboot:slide}";
    $out = $parser->parserSlideLabel($tpl);

    TestAssert::same(1, count($model->calls), 'bare: one getSlides call');
    TestAssert::same('1', (string) $model->calls[0]['gid'], 'bare: default gid=1');
    TestAssert::same('5', (string) $model->calls[0]['num'], 'bare: default num=5');
    TestAssert::same(1, $model->calls[0]['start'], 'bare: default start=1');
    TestAssert::contains($out, 'Banner A', 'bare: renders title');
    TestAssert::notContains($out, '{pboot:slide}', 'bare: no open tag residue');
    TestAssert::notContains($out, '{/pboot:slide}', 'bare: no close tag residue');
    TestAssert::notContains($out, '[slide:', 'bare: no inner label residue');

    echo "=== explicit gid keeps query target ===\n";

    $model2 = new SlideModelStub();
    $model2->result = array(slide_row('Group2'));
    $parser2 = slide_parser_harness($model2);

    $tpl2 = "{pboot:slide gid=2 num=3}\n[slide:title]\n{/pboot:slide}";
    $out2 = $parser2->parserSlideLabel($tpl2);

    TestAssert::same('2', (string) $model2->calls[0]['gid'], 'explicit: gid=2');
    TestAssert::same('3', (string) $model2->calls[0]['num'], 'explicit: num=3');
    TestAssert::contains($out2, 'Group2', 'explicit: renders title');

    echo "=== empty group removes whole block ===\n";

    $model3 = new SlideModelStub();
    $model3->result = false;
    $parser3 = slide_parser_harness($model3);

    $tpl3 = "before{pboot:slide gid=9}[slide:title]{/pboot:slide}after";
    $out3 = $parser3->parserSlideLabel($tpl3);

    TestAssert::same('beforeafter', $out3, 'empty: whole block removed');
    TestAssert::notContains($out3, '{pboot:slide}', 'empty: no open residue');
    TestAssert::notContains($out3, '[slide:', 'empty: no inner residue');

    echo "=== num/start and illegal start fallback ===\n";

    $model4 = new SlideModelStub();
    $model4->result = array(slide_row('S'));
    $parser4 = slide_parser_harness($model4);
    $parser4->parserSlideLabel('{pboot:slide gid=1 num=2 start=3}[slide:title]{/pboot:slide}');
    TestAssert::same('2', (string) $model4->calls[0]['num'], 'start: num=2');
    TestAssert::same(3, (int) $model4->calls[0]['start'], 'start: start=3');

    $model5 = new SlideModelStub();
    $model5->result = array(slide_row('S'));
    $parser5 = slide_parser_harness($model5);
    $parser5->parserSlideLabel('{pboot:slide start=0}[slide:title]{/pboot:slide}');
    TestAssert::same(1, (int) $model5->calls[0]['start'], 'illegal start=0 falls back to 1');

    $model6 = new SlideModelStub();
    $model6->result = array(slide_row('S'));
    $parser6 = slide_parser_harness($model6);
    $parser6->parserSlideLabel('{pboot:slide start=abc}[slide:title]{/pboot:slide}');
    TestAssert::same(1, (int) $model6->calls[0]['start'], 'illegal start=abc falls back to 1');
});
