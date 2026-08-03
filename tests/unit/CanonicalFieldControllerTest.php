<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers ParserController field allowlist canonicalization
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

use app\home\controller\ParserController;

final class CanonicalFieldControllerModelDouble
{
    public $picsField;
    public $checkboxField;

    public function getExtFields()
    {
        return array('ext_color');
    }

    public function getContentPics($id, $field)
    {
        $this->picsField = $field;
        $row = new stdClass();
        $row->$field = 'first.jpg';
        $row->picstitle = '第一张';
        return $row;
    }

    public function getContentCheckbox($id, $field)
    {
        $this->checkboxField = $field;
        return 'red,blue';
    }
}

function make_canonical_field_parser($model)
{
    $refClass = new ReflectionClass(ParserController::class);
    $parser = $refClass->newInstanceWithoutConstructor();
    $modelProperty = $refClass->getProperty('model');
    if (PHP_VERSION_ID < 80100) {
        $modelProperty->setAccessible(true);
    }
    $modelProperty->setValue($parser, $model);
    return $parser;
}

return TestAssert::runSuite(function () {
    echo "=== pboot:pics 扩展字段使用规范拼写 ===\n";

    $model = new CanonicalFieldControllerModelDouble();
    $parser = make_canonical_field_parser($model);
    TestAssert::same(
        'photo',
        $parser->parserContentPicsLabel('{pboot:pics id=1 field=EXT_COLOR}photo{/pboot:pics}'),
        '大写扩展字段标签正常解析'
    );
    TestAssert::same('ext_color', $model->picsField, 'pics 查询使用表结构规范字段名');

    echo "=== pboot:checkbox 扩展字段使用规范拼写 ===\n";

    $model = new CanonicalFieldControllerModelDouble();
    $parser = make_canonical_field_parser($model);
    TestAssert::same(
        'choicechoice',
        $parser->parserContentCheckboxLabel('{pboot:checkbox id=1 field=EXT_COLOR}choice{/pboot:checkbox}'),
        '大写扩展字段标签正常解析'
    );
    TestAssert::same('ext_color', $model->checkboxField, 'checkbox 查询使用表结构规范字段名');
});
