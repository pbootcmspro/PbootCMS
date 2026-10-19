<?php

declare(strict_types=1);

/**
 * @suite integration
 * @covers apps/api/model/CmsModel.php
 *
 * API 多栏目 scode 末尾空段应忽略，结果与去掉空段后一致。
 * 只测 getList：API 入口实际调用的 getLists 拼接逻辑与之相同，但其分页查询需先加载
 * core/view/Paging.php，加载后仍会读取 Model::select() 中未初始化的 sql['group']，
 * 在测试错误处理下抛异常，暂未覆盖。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once TEST_ROOT . 'support/DatabaseTestSupport.php';
require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/Model.php';
require_once CORE_PATH . '/database/Builder.php';
require_once APP_PATH . '/common/VisitsCounter.php';
require_once APP_PATH . '/api/model/CmsModel.php';

use app\api\model\CmsModel;

/**
 * @return string|null
 */
function cms_scode_boot_sqlite()
{
    if (! extension_loaded('sqlite3')) {
        return null;
    }

    $rel = '/data/db_error_mode_cms_scode_' . getmypid() . '.db';
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
        status INTEGER NOT NULL DEFAULT 1
    )');
    $sqlite->exec('CREATE UNIQUE INDEX ay_content_sort_scode ON ay_content_sort(scode)');
    $sqlite->exec('CREATE TABLE ay_content (
        id INTEGER PRIMARY KEY,
        acode TEXT NOT NULL DEFAULT \'\',
        scode TEXT NOT NULL DEFAULT \'\',
        subscode TEXT NOT NULL DEFAULT \'\',
        title TEXT NOT NULL DEFAULT \'\',
        filename TEXT NOT NULL DEFAULT \'\',
        status INTEGER NOT NULL DEFAULT 1,
        date TEXT NOT NULL DEFAULT \'\',
        istop INTEGER NOT NULL DEFAULT 0,
        isrecommend INTEGER NOT NULL DEFAULT 0,
        isheadline INTEGER NOT NULL DEFAULT 0
    )');
    $sqlite->exec('CREATE TABLE ay_content_ext (
        extid INTEGER PRIMARY KEY,
        contentid INTEGER NOT NULL DEFAULT 0
    )');
    $sqlite->exec('CREATE INDEX ay_content_ext_contentid ON ay_content_ext(contentid)');
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
function cms_scode_ids($rows)
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
    $sqlitePath = cms_scode_boot_sqlite();
    if ($sqlitePath === null) {
        echo "SKIP: sqlite3 extension unavailable\n";
        return;
    }

    echo "=== CmsModel getList trailing empty scode ===\n";

    $model = new CmsModel();
    $listOne = cms_scode_ids($model->getList('cn', '1', 10, 'a.id ASC'));
    $listBoth = cms_scode_ids($model->getList('cn', '1,2', 10, 'a.id ASC'));
    TestAssert::same(array(10, 11), $listOne, 'list scode=1 expands child');
    TestAssert::same(array(10, 11), $listBoth, 'list scode=1,2 same rows');
    TestAssert::same($listOne, cms_scode_ids($model->getList('cn', '1,', 10, 'a.id ASC')), 'list trailing empty ignored');
    TestAssert::same($listOne, cms_scode_ids($model->getList('cn', '1, ', 10, 'a.id ASC')), 'list trailing blank ignored');
    TestAssert::same($listBoth, cms_scode_ids($model->getList('cn', '1,2,', 10, 'a.id ASC')), 'list multi trailing empty ignored');
    TestAssert::same($listOne, cms_scode_ids($model->getList('cn', ',1', 10, 'a.id ASC')), 'list leading empty ignored');

    if (is_file($sqlitePath)) {
        @unlink($sqlitePath);
    }
});
