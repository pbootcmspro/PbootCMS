<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers static/backup/sql/pbootcms.sql 与 data/pbootcms.db 索引集合一致
 * @covers ParserModel getList() 不再 FORCE INDEX(ay_content_unique)
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== MySQL 初始化 SQL 与随包 SQLite 的索引集合一致 ===\n";

    $dump = file_get_contents(ROOT_PATH . 'static/backup/sql/pbootcms.sql');
    TestAssert::false($dump === false, 'pbootcms.sql readable');
    preg_match_all('/^\s*(?:UNIQUE\s+|FULLTEXT\s+|SPATIAL\s+)?KEY\s+`?([A-Za-z0-9_]+)`?/m', $dump, $m);
    // 扩展字段索引只在 MySQL 上由 ExtFieldController 运行时创建，随包 SQLite 本来就没有，属已知差异
    $mysqlOnly = array('ay_content_ext_color_index', 'ay_content_ext_price_index', 'ay_content_ext_type_index');
    TestAssert::same($mysqlOnly, array_values(array_intersect($m[1], $mysqlOnly)), 'dump contains the three MySQL-only ext indexes');
    $dumpIndexes = array_values(array_diff($m[1], $mysqlOnly));
    sort($dumpIndexes);

    $pdo = new PDO('sqlite:' . ROOT_PATH . 'data/pbootcms.db', null, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    $sqliteIndexes = $pdo->query("SELECT name FROM sqlite_master WHERE type='index' AND sql IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    sort($sqliteIndexes);

    TestAssert::same($dumpIndexes, $sqliteIndexes, 'dump and sqlite index sets identical');

    echo "=== ay_content 目标索引：有 title 索引、无 unique 索引 ===\n";

    TestAssert::true(in_array('ay_content_title_index', $sqliteIndexes, true), 'sqlite has ay_content_title_index');
    TestAssert::false(in_array('ay_content_unique', $sqliteIndexes, true), 'sqlite has no ay_content_unique');
    TestAssert::notContains($dump, 'ay_content_unique', 'dump has no ay_content_unique');

    echo "=== getList 不再 FORCE INDEX(ay_content_unique) ===\n";

    $parser = file_get_contents(APP_PATH . '/home/model/ParserModel.php');
    TestAssert::notContains($parser, 'ay_content_unique', 'ParserModel no longer references ay_content_unique');
    TestAssert::contains($parser, "parent::table('ay_content a')", 'getList uses plain table alias');
});
