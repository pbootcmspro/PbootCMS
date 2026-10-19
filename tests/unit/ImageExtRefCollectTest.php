<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/admin/model/content/ContentModel.php getImage
 * @covers apps/admin/controller/system/ImageExtController.php buildRedundantMap
 *
 * fix #298：清理冗余图片收集正文任意上传文件引用、附件 enclosure 与内容扩展字段，避免在用文件被迁走
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

function imageext_ref_rmdir(string $dir)
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            imageext_ref_rmdir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function imageext_ref_seed_db(string $sqlitePath, string $prefix)
{
    $up = $prefix . '/static/upload';
    $sqlite = new SQLite3($sqlitePath);
    $sqlite->busyTimeout(5000);
    $sqlite->exec('CREATE TABLE ay_company (id INTEGER PRIMARY KEY, weixin TEXT NOT NULL DEFAULT \'\')');
    $sqlite->exec('CREATE TABLE ay_content_sort (id INTEGER PRIMARY KEY, pic TEXT NOT NULL DEFAULT \'\', ico TEXT NOT NULL DEFAULT \'\')');
    $sqlite->exec('CREATE TABLE ay_link (id INTEGER PRIMARY KEY, logo TEXT NOT NULL DEFAULT \'\')');
    $sqlite->exec('CREATE TABLE ay_member (id INTEGER PRIMARY KEY, headpic TEXT NOT NULL DEFAULT \'\')');
    $sqlite->exec('CREATE TABLE ay_site (id INTEGER PRIMARY KEY, logo TEXT NOT NULL DEFAULT \'\')');
    $sqlite->exec('CREATE TABLE ay_slide (id INTEGER PRIMARY KEY, pic TEXT NOT NULL DEFAULT \'\')');
    $sqlite->exec('CREATE TABLE ay_label (id INTEGER PRIMARY KEY, value TEXT NOT NULL DEFAULT \'\')');
    $sqlite->exec('CREATE TABLE ay_content (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ico TEXT NOT NULL DEFAULT \'\',
        pics TEXT NOT NULL DEFAULT \'\',
        content TEXT NOT NULL DEFAULT \'\',
        enclosure TEXT NOT NULL DEFAULT \'\'
    )');
    $sqlite->exec('CREATE TABLE ay_content_ext (
        extid INTEGER PRIMARY KEY AUTOINCREMENT,
        contentid INTEGER NOT NULL DEFAULT 0,
        ext_pic TEXT NOT NULL DEFAULT \'\',
        ext_pics TEXT NOT NULL DEFAULT \'\',
        ext_file TEXT NOT NULL DEFAULT \'\',
        ext_editor TEXT NOT NULL DEFAULT \'\'
    )');

    $content = '<p><img src="' . $up . '/image/20260101/ok.jpg" alt="ok"></p>'
        . '<p><img src="' . $up . '/image/20260101/svg1.svg"><img src=\'' . $up . '/image/20260101/avif1.avif\'></p>'
        . '<video controls><source src="' . $up . '/video/20260101/v1.mp4" type="video/mp4"></video>'
        . '<video src="http://example.com' . $up . '/video/20260101/v2.mp4"></video>'
        . '<p><img src="data:image/png;base64,' . base64_encode(str_repeat('pboot', 30000)) . '"></p>'
        . '<p><img src="' . $up . '/image/20260101/q.jpg?v=1"></p>'
        . '<p><img src="static/upload/image/20260101/rel.jpg"></p>'
        . '<p><a href="' . $up . '/other/20260101/link.zip#dl">下载</a></p>';

    $stmt = $sqlite->prepare('INSERT INTO ay_content (id, ico, pics, content, enclosure) VALUES (:id, :ico, :pics, :content, :enclosure)');
    $stmt->bindValue(':id', 1, SQLITE3_INTEGER);
    $stmt->bindValue(':ico', $up . '/image/20260101/ico.jpg', SQLITE3_TEXT);
    $stmt->bindValue(':pics', $up . '/image/20260101/pic1.jpg,' . $up . '/image/20260101/pic2.jpg', SQLITE3_TEXT);
    $stmt->bindValue(':content', htmlspecialchars($content, ENT_QUOTES), SQLITE3_TEXT);
    $stmt->bindValue(':enclosure', $up . '/other/20260101/enc.zip', SQLITE3_TEXT);
    $stmt->execute();
    $stmt->close();

    $sqlite->exec('BEGIN');
    for ($i = 2; $i <= 500; $i++) {
        $sqlite->exec("INSERT INTO ay_content (id) VALUES ($i)");
    }
    $sqlite->exec('COMMIT');

    $stmt = $sqlite->prepare('INSERT INTO ay_content (id, content) VALUES (501, :content)');
    $stmt->bindValue(':content', htmlspecialchars('<img src="' . $up . '/image/20260101/last.svg">', ENT_QUOTES), SQLITE3_TEXT);
    $stmt->execute();
    $stmt->close();

    $stmt = $sqlite->prepare('INSERT INTO ay_content_ext (contentid, ext_pic, ext_pics, ext_file, ext_editor) VALUES (:cid, :pic, :pics, :file, :editor)');
    $stmt->bindValue(':cid', 1, SQLITE3_INTEGER);
    $stmt->bindValue(':pic', $up . '/image/20260101/ext1.jpg', SQLITE3_TEXT);
    $stmt->bindValue(':pics', $up . '/image/20260101/ext2.png,' . $up . '/image/20260101/ext3.png', SQLITE3_TEXT);
    $stmt->bindValue(':file', $up . '/other/20260101/extfile.rar', SQLITE3_TEXT);
    $stmt->bindValue(':editor', htmlspecialchars('<p><img src="' . $up . '/image/20260101/exted.gif"></p>', ENT_QUOTES), SQLITE3_TEXT);
    $stmt->execute();
    $stmt->reset();
    $stmt->bindValue(':cid', 501, SQLITE3_INTEGER);
    $stmt->bindValue(':pic', $up . '/image/20260101/lastext.jpg', SQLITE3_TEXT);
    $stmt->bindValue(':pics', '', SQLITE3_TEXT);
    $stmt->bindValue(':file', '', SQLITE3_TEXT);
    $stmt->bindValue(':editor', '', SQLITE3_TEXT);
    $stmt->execute();
    $stmt->close();
    $sqlite->close();
}

function imageext_ref_run(string $siteDir): array
{
    $tmpRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot_imageext_ref_' . uniqid('', true);
    $uploadDir = $tmpRoot . str_replace('/', DIRECTORY_SEPARATOR, $siteDir . '/static/upload');
    $files = array(
        'image/20260101/ok.jpg',
        'image/20260101/svg1.svg',
        'image/20260101/avif1.avif',
        'image/20260101/q.jpg',
        'image/20260101/rel.jpg',
        'image/20260101/ico.jpg',
        'image/20260101/pic1.jpg',
        'image/20260101/pic2.jpg',
        'image/20260101/ext1.jpg',
        'image/20260101/ext2.png',
        'image/20260101/ext3.png',
        'image/20260101/exted.gif',
        'image/20260101/last.svg',
        'image/20260101/lastext.jpg',
        'image/20260101/orphan.png',
        'video/20260101/v1.mp4',
        'video/20260101/v2.mp4',
        'video/20260101/orphan.mp4',
        'other/20260101/enc.zip',
        'other/20260101/link.zip',
        'other/20260101/extfile.rar',
    );
    foreach ($files as $rel) {
        $abs = $uploadDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_dir(dirname($abs))) {
            mkdir(dirname($abs), 0777, true);
        }
        file_put_contents($abs, 'x');
    }

    $dbRel = '/data/imageext_ref_collect_' . getmypid() . '_' . md5($siteDir) . '.db';
    $dbPath = ROOT_PATH . ltrim($dbRel, '/');
    if (is_file($dbPath)) {
        @unlink($dbPath);
    }
    imageext_ref_seed_db($dbPath, $siteDir);

    $payload = base64_encode(json_encode(array(
        'doc_path' => $tmpRoot,
        'db_rel' => $dbRel,
    ), JSON_UNESCAPED_SLASHES));
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'imageext_ref_collect_probe.php';
    $cmd = $php . ' -d pcre.jit=0 ' . implode(' ', array_map('escapeshellarg', array($script, $siteDir, $payload)));
    $output = shell_exec($cmd);

    @unlink($dbPath);
    imageext_ref_rmdir($tmpRoot);

    $data = is_string($output) ? json_decode(trim($output), true) : null;
    if (!is_array($data) || !isset($data['result'])) {
        echo 'probe output: ' . (is_string($output) ? $output : '') . "\n";
        return array('ok' => false, 'keys' => array());
    }
    return array('ok' => true, 'keys' => $data['result']);
}

return TestAssert::runSuite(function () {
    if (!extension_loaded('sqlite3')) {
        echo "SKIP: sqlite3 extension unavailable\n";
        return;
    }

    $expected = array(
        '/static/upload/image/20260101/orphan.png',
        '/static/upload/video/20260101/orphan.mp4',
    );

    foreach (array('' => 'root deploy', '/cms' => 'subdir deploy') as $siteDir => $label) {
        echo "=== buildRedundantMap(): {$label} ===\n";
        $run = imageext_ref_run((string) $siteDir);
        TestAssert::true($run['ok'], "{$label}: probe returned result");
        TestAssert::same(
            $expected,
            $run['keys'],
            "{$label}: only unreferenced files are redundant (svg/avif/video/enclosure/ext/query/relative/paging/base64 without pcre.jit all kept)"
        );
    }
});
