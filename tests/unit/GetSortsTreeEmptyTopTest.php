<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/home/model/ParserModel.php getSortsTree()
 * @covers apps/home/controller/ParserController.php parserNavLabel()
 *
 * Issue #275：无启用顶级栏目但有启用子栏目时，top 必须为空数组，nav 不得 TypeError。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once TEST_ROOT . 'support/DatabaseTestSupport.php';
require_once CORE_PATH . '/function/file.php';
require_once CORE_PATH . '/basic/Basic.php';
require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/Model.php';
require_once CORE_PATH . '/database/Builder.php';
require_once APP_PATH . '/home/model/ParserModel.php';

spl_autoload_register('core\\basic\\Basic::autoLoad', true, true);

use app\home\model\ParserModel;

if (! function_exists('get_lg')) {
    function get_lg()
    {
        return 'cn';
    }
}

/**
 * 只有子栏目启用、父级停用的最小栏目表
 *
 * @return string|null 库路径；sqlite3 扩展不可用时 null
 */
function empty_top_boot_sqlite()
{
    if (! extension_loaded('sqlite3')) {
        return null;
    }
    $rel = '/data/db_error_mode_empty_top_' . getmypid() . '.db';
    $sqlitePath = ROOT_PATH . $rel;
    if (is_file($sqlitePath)) {
        @unlink($sqlitePath);
    }
    $sqlite = new SQLite3($sqlitePath);
    $sqlite->busyTimeout(5000);
    $sqlite->exec('CREATE TABLE ay_content_sort (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        acode TEXT NOT NULL DEFAULT \'\',
        mcode TEXT NOT NULL DEFAULT \'\',
        pcode TEXT NOT NULL DEFAULT \'\',
        scode TEXT NOT NULL DEFAULT \'\',
        name TEXT NOT NULL DEFAULT \'\',
        status INTEGER NOT NULL DEFAULT 1,
        outlink TEXT NOT NULL DEFAULT \'\',
        filename TEXT NOT NULL DEFAULT \'\',
        sorting INTEGER NOT NULL DEFAULT 255
    )');
    $sqlite->exec('CREATE TABLE ay_model (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        mcode TEXT NOT NULL DEFAULT \'\',
        type INTEGER NOT NULL DEFAULT 1,
        urlname TEXT NOT NULL DEFAULT \'\'
    )');
    $sqlite->exec("INSERT INTO ay_model (mcode, type, urlname) VALUES ('2', 2, 'list')");
    $sqlite->exec("INSERT INTO ay_content_sort (acode, mcode, pcode, scode, name, status) VALUES ('cn', '2', '0', '1', 'Top', 0)");
    $sqlite->exec("INSERT INTO ay_content_sort (acode, mcode, pcode, scode, name, status) VALUES ('cn', '2', '1', '11', 'Son11', 1)");
    $sqlite->close();

    db_set_database_config(array(
        'type' => 'sqlite',
        'dbname' => $rel
    ));
    db_reset_sqlite_singleton();
    return $sqlitePath;
}

/**
 * @param ParserModel $model
 * @return \app\home\controller\ParserController
 */
function empty_top_nav_parser_harness(ParserModel $model)
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

return TestAssert::runSuite(function () {
    $sqlitePath = empty_top_boot_sqlite();
    if ($sqlitePath === null) {
        echo "SKIP: sqlite3 extension unavailable\n";
        return;
    }

    echo "=== getSortsTree(): no enabled root -> top is empty array, tree keeps child ===\n";

    $model = new ParserModel();
    $data = $model->getSortsTree();
    TestAssert::true(isset($data['top']) && is_array($data['top']), 'top is array');
    TestAssert::same(0, count($data['top']), 'top empty when no enabled root');
    TestAssert::true(isset($data['tree']['11']), 'tree keeps enabled child');
    TestAssert::true(isset($data['tree']['1']['son']), 'disabled parent still receives son list');

    echo "=== parserNavLabel parent=0 num=10: empty nav, no TypeError ===\n";

    $parser = empty_top_nav_parser_harness($model);

    $out = $parser->parserNavLabel('{pboot:nav num=10 parent=0}[nav:name]{/pboot:nav}');
    TestAssert::same('', trim((string) $out), 'empty top yields empty nav');

    $outNoParent = $parser->parserNavLabel('{pboot:nav num=10}[nav:name]{/pboot:nav}');
    TestAssert::same('', trim((string) $outNoParent), 'missing parent same as parent=0');

    $outChild = $parser->parserNavLabel('{pboot:nav num=10 parent=1}[nav:name]{/pboot:nav}');
    TestAssert::same('Son11', trim((string) $outChild), 'parent=1 still lists enabled child');

    db_reset_sqlite_singleton();
    @unlink($sqlitePath);
});
