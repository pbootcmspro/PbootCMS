<?php

declare(strict_types=1);

/**
 * @suite integration
 * @covers apps/home/model/ParserModel.php
 *
 * SQLite 上栏目别名、内容 filename、多栏目展开与注入字面量比较。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once TEST_ROOT . 'support/DatabaseTestSupport.php';
require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/Model.php';
require_once CORE_PATH . '/database/Builder.php';
require_once APP_PATH . '/home/model/ParserModel.php';

use app\home\model\ParserModel;

if (! function_exists('get_lg')) {
    function get_lg()
    {
        return 'cn';
    }
}

/**
 * @return string|null
 */
function parser_bind_boot_sqlite()
{
    if (! extension_loaded('sqlite3')) {
        return null;
    }

    $rel = '/data/db_error_mode_parser_bind_' . getmypid() . '.db';
    $sqlitePath = ROOT_PATH . $rel;
    if (is_file($sqlitePath)) {
        @unlink($sqlitePath);
    }
    $dir = dirname($sqlitePath);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $sqlite = new SQLite3($sqlitePath);
    $sqlite->busyTimeout(5000);
    $sqlite->exec('CREATE TABLE ay_model (
        id INTEGER PRIMARY KEY,
        mcode TEXT NOT NULL DEFAULT \'\',
        type INTEGER NOT NULL DEFAULT 2,
        urlname TEXT NOT NULL DEFAULT \'\',
        name TEXT NOT NULL DEFAULT \'\'
    )');
    $sqlite->exec('CREATE UNIQUE INDEX ay_model_mcode ON ay_model(mcode)');
    $sqlite->exec('CREATE TABLE ay_content_sort (
        id INTEGER PRIMARY KEY,
        acode TEXT NOT NULL DEFAULT \'\',
        mcode TEXT NOT NULL DEFAULT \'\',
        pcode TEXT NOT NULL DEFAULT \'\',
        scode TEXT NOT NULL DEFAULT \'\',
        name TEXT NOT NULL DEFAULT \'\',
        filename TEXT NOT NULL DEFAULT \'\',
        outlink TEXT NOT NULL DEFAULT \'\',
        status INTEGER NOT NULL DEFAULT 1,
        pic TEXT NOT NULL DEFAULT \'\',
        gid INTEGER NOT NULL DEFAULT 0
    )');
    $sqlite->exec('CREATE UNIQUE INDEX ay_content_sort_scode ON ay_content_sort(scode)');
    $sqlite->exec('CREATE TABLE ay_content (
        id INTEGER PRIMARY KEY,
        acode TEXT NOT NULL DEFAULT \'\',
        scode TEXT NOT NULL DEFAULT \'\',
        subscode TEXT NOT NULL DEFAULT \'\',
        title TEXT NOT NULL DEFAULT \'\',
        filename TEXT NOT NULL DEFAULT \'\',
        subtitle TEXT NOT NULL DEFAULT \'\',
        author TEXT NOT NULL DEFAULT \'\',
        visits INTEGER NOT NULL DEFAULT 0,
        likes INTEGER NOT NULL DEFAULT 0,
        oppose INTEGER NOT NULL DEFAULT 0,
        outlink TEXT NOT NULL DEFAULT \'\',
        date TEXT NOT NULL DEFAULT \'\',
        ico TEXT NOT NULL DEFAULT \'\',
        pics TEXT NOT NULL DEFAULT \'\',
        content TEXT NOT NULL DEFAULT \'\',
        enclosure TEXT NOT NULL DEFAULT \'\',
        keywords TEXT NOT NULL DEFAULT \'\',
        description TEXT NOT NULL DEFAULT \'\',
        istop INTEGER NOT NULL DEFAULT 0,
        isrecommend INTEGER NOT NULL DEFAULT 0,
        isheadline INTEGER NOT NULL DEFAULT 0,
        status INTEGER NOT NULL DEFAULT 1,
        gid INTEGER NOT NULL DEFAULT 0
    )');
    $sqlite->exec('CREATE TABLE ay_content_ext (
        extid INTEGER PRIMARY KEY,
        contentid INTEGER NOT NULL DEFAULT 0
    )');
    $sqlite->exec('CREATE INDEX ay_content_ext_contentid ON ay_content_ext(contentid)');
    $sqlite->exec('CREATE TABLE ay_member_group (
        id INTEGER PRIMARY KEY,
        gcode TEXT NOT NULL DEFAULT \'\'
    )');
    $sqlite->exec("INSERT INTO ay_model (id, mcode, type, urlname, name) VALUES (1, '1', 2, 'news', 'News')");
    $sqlite->exec("INSERT INTO ay_content_sort (id, acode, mcode, pcode, scode, name, filename, outlink, status) VALUES (1, 'cn', '1', '', '1', 'News', 'news', '', 1)");
    $sqlite->exec("INSERT INTO ay_content_sort (id, acode, mcode, pcode, scode, name, filename, outlink, status) VALUES (2, 'cn', '1', '1', '2', 'Product', 'product', '', 1)");
    $sqlite->exec("INSERT INTO ay_content (id, acode, scode, subscode, title, filename, status, date) VALUES (10, 'cn', '1', '', 'Hello', 'hello', 1, '2000-01-01 00:00:00')");
    $sqlite->exec("INSERT INTO ay_content (id, acode, scode, subscode, title, filename, status, date) VALUES (11, 'cn', '2', '', 'World', 'world', 1, '2000-01-01 00:00:00')");
    $sqlite->close();

    db_set_database_config(array(
        'type' => 'sqlite',
        'dbname' => $rel,
        'prefix' => 'ay_'
    ));
    db_reset_sqlite_singleton();
    return $sqlitePath;
}

/**
 * @param array $rows
 * @return array
 */
function parser_bind_ids($rows)
{
    $ids = array();
    if (! is_array($rows)) {
        return $ids;
    }
    foreach ($rows as $row) {
        $ids[] = (int) $row->id;
    }
    sort($ids);
    return $ids;
}

return TestAssert::runSuite(function () {
    $sqlitePath = parser_bind_boot_sqlite();
    if ($sqlitePath === null) {
        echo "SKIP: sqlite3 extension unavailable\n";
        return;
    }

    echo "=== sort by scode and filename ===\n";

    $model = new ParserModel();
    $byScode = $model->getSort('1');
    $byName = $model->getSort('news');
    TestAssert::true(is_object($byScode), 'getSort scode hits');
    TestAssert::true(is_object($byName), 'getSort filename hits');
    TestAssert::same('1', (string) $byScode->scode, 'scode row');
    TestAssert::same((string) $byScode->id, (string) $byName->id, 'alias resolves same sort');

    echo "=== content by id and filename ===\n";

    $byId = $model->getContent('10');
    $byFile = $model->getContent('hello');
    TestAssert::true(is_object($byId), 'getContent digit id hits');
    TestAssert::true(is_object($byFile), 'getContent filename hits');
    TestAssert::same(10, (int) $byId->id, 'digit id row');
    TestAssert::same(10, (int) $byFile->id, 'filename row');

    echo "=== scode tree count and list ===\n";

    TestAssert::same(array('1', '2'), (new ParserModel())->getSubScodes('1'), 'sub scodes parent then child');
    TestAssert::same(2, $model->getSortRows('1'), 'rows include child sort');
    TestAssert::same(2, $model->getSortRows('1,2'), 'multi scode same expanded count');
    TestAssert::same(1, $model->getSortRows('2'), 'child sort counts only itself');
    TestAssert::same($model->getSortRows('1'), $model->getSortRows('1,'), 'trailing empty segment ignored');
    TestAssert::same($model->getSortRows('1,2'), $model->getSortRows('1,2,'), 'multi scode trailing empty ignored');
    TestAssert::same($model->getSortRows('1'), $model->getSortRows(',1'), 'leading empty segment ignored');

    $listOne = parser_bind_ids($model->getList('1', 10, 'a.id ASC'));
    $listBoth = parser_bind_ids($model->getList('1,2', 10, 'a.id ASC'));
    TestAssert::same(array(10, 11), $listOne, 'list scode=1 expands child');
    TestAssert::same(array(10, 11), $listBoth, 'list scode=1,2 same rows');
    TestAssert::same($listOne, parser_bind_ids($model->getList('1,', 10, 'a.id ASC')), 'list trailing empty ignored');
    TestAssert::same($listOne, parser_bind_ids($model->getList('1, ', 10, 'a.id ASC')), 'list trailing blank ignored');
    TestAssert::same($listBoth, parser_bind_ids($model->getList('1,2,', 10, 'a.id ASC')), 'list multi trailing empty ignored');

    echo "=== injection stays literal ===\n";

    $payloads = array("1' OR '1'='1", '1) OR 1=1--');
    foreach ($payloads as $payload) {
        $sort = (new ParserModel())->getSort($payload);
        $content = (new ParserModel())->getContent($payload);
        $rows = (new ParserModel())->getSortRows($payload);
        $subs = (new ParserModel())->getSubScodes($payload);
        $list = (new ParserModel())->getList($payload, 10, 'a.id ASC');
        TestAssert::false((bool) $sort, 'getSort literal ' . $payload);
        TestAssert::false((bool) $content, 'getContent literal ' . $payload);
        TestAssert::same(0, $rows, 'getSortRows literal ' . $payload);
        TestAssert::same(array($payload), $subs, 'getSubScodes keeps literal ' . $payload);
        TestAssert::same(array(), parser_bind_ids($list), 'getList literal ' . $payload);
    }

    if (is_file($sqlitePath)) {
        @unlink($sqlitePath);
    }
});
