<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/admin/model/content/ContentModel.php copyContent
 *
 * fix #223：批量复制内容时清空自定义URL名称，副本不与原文链接冲突
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

defined('CONF_PATH') ?: define('CONF_PATH', ROOT_PATH . 'config');
defined('RUN_PATH') ?: define('RUN_PATH', ROOT_PATH . 'runtime');

require_once TEST_ROOT . 'support/DatabaseTestSupport.php';
require_once CORE_PATH . '/function/file.php';
require_once CORE_PATH . '/basic/Basic.php';
require_once CORE_PATH . '/basic/Config.php';
require_once CORE_PATH . '/basic/Model.php';
require_once CORE_PATH . '/database/Builder.php';
require_once APP_PATH . '/admin/model/content/ContentModel.php';

spl_autoload_register('core\\basic\\Basic::autoLoad', true, true);

use app\admin\model\content\ContentModel;

function content_copy_boot_sqlite()
{
    if (! extension_loaded('sqlite3')) {
        return null;
    }
    $rel = '/data/db_error_mode_content_copy_' . getmypid() . '.db';
    $sqlitePath = ROOT_PATH . $rel;
    if (is_file($sqlitePath)) {
        @unlink($sqlitePath);
    }
    $sqlite = new SQLite3($sqlitePath);
    $sqlite->busyTimeout(5000);
    $sqlite->exec('CREATE TABLE ay_content (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        scode TEXT NOT NULL DEFAULT \'\',
        title TEXT NOT NULL DEFAULT \'\',
        filename TEXT NOT NULL DEFAULT \'\'
    )');
    $sqlite->exec('CREATE TABLE ay_content_ext (
        extid INTEGER PRIMARY KEY AUTOINCREMENT,
        contentid INTEGER NOT NULL DEFAULT 0,
        ext_note TEXT NOT NULL DEFAULT \'\'
    )');
    $sqlite->exec("INSERT INTO ay_content (id, scode, title, filename) VALUES (1, '1', 'with-url', 'aabbcc')");
    $sqlite->exec("INSERT INTO ay_content (id, scode, title, filename) VALUES (2, '1', 'no-url', '')");
    $sqlite->exec("INSERT INTO ay_content_ext (contentid, ext_note) VALUES (1, 'ext-1')");
    $sqlite->close();

    db_set_database_config(array(
        'type' => 'sqlite',
        'dbname' => $rel
    ));
    db_reset_sqlite_singleton();
    return $sqlitePath;
}

function content_copy_rows(string $sqlitePath, string $sql): array
{
    db_commit_sqlite();
    $sqlite = new SQLite3($sqlitePath, SQLITE3_OPEN_READONLY);
    $rows = array();
    $rs = $sqlite->query($sql);
    while ($row = $rs->fetchArray(SQLITE3_ASSOC)) {
        $rows[] = $row;
    }
    $sqlite->close();
    return $rows;
}

return TestAssert::runSuite(function () {
    $sqlitePath = content_copy_boot_sqlite();
    if ($sqlitePath === null) {
        echo "SKIP: sqlite3 extension unavailable\n";
        return;
    }

    echo "=== copyContent(): copy clears custom URL name, source untouched ===\n";
    $model = new ContentModel();
    TestAssert::true((bool) $model->copyContent(array(1, 2), '2'), 'copy returns truthy');

    $rows = content_copy_rows($sqlitePath, 'SELECT id, scode, title, filename FROM ay_content ORDER BY id');
    TestAssert::same(4, count($rows), 'two copies inserted');
    TestAssert::same('aabbcc', $rows[0]['filename'], 'source filename unchanged');
    TestAssert::same('1', $rows[0]['scode'], 'source scode unchanged');
    TestAssert::same('', $rows[2]['filename'], 'copy of content with url name has empty filename');
    TestAssert::same('2', $rows[2]['scode'], 'copy moved to target scode');
    TestAssert::same('with-url', $rows[2]['title'], 'copy keeps other fields');
    TestAssert::same('', $rows[3]['filename'], 'copy of content without url name stays empty');

    $dup = content_copy_rows($sqlitePath, "SELECT count(*) AS n FROM ay_content WHERE filename='aabbcc'");
    TestAssert::same(1, (int) $dup[0]['n'], 'filename aabbcc appears exactly once');

    $ext = content_copy_rows($sqlitePath, 'SELECT contentid, ext_note FROM ay_content_ext ORDER BY extid');
    TestAssert::same(2, count($ext), 'ext row copied for content with ext');
    TestAssert::same(3, (int) $ext[1]['contentid'], 'copied ext points to new content id');

    db_reset_sqlite_singleton();
    @unlink($sqlitePath);
});
