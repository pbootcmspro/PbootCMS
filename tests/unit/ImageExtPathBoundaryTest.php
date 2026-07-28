<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers app/admin/controller/system/ImageExtController path helpers
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

function imageext_probe(string $siteDir, string $action, array $payload = array()): array
{
    $php = PHP_BINARY ?: 'php';
    $script = TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'imageext_path_probe.php';
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $encoded = base64_encode($json);
    $cmd = escapeshellarg($php) . ' '
        . escapeshellarg($script) . ' '
        . escapeshellarg($siteDir) . ' '
        . escapeshellarg($action) . ' '
        . escapeshellarg($encoded);
    $output = shell_exec($cmd);
    if (!is_string($output) || $output === '') {
        return array();
    }
    $data = json_decode(trim($output), true);
    return is_array($data) ? $data : array();
}

function imageext_normalize(string $siteDir, string $path): string
{
    $data = imageext_probe($siteDir, 'normalize', array('path' => $path));
    return isset($data['result']) ? (string) $data['result'] : '';
}

function imageext_fstkey(string $siteDir, string $fsPath, string $docPath = ''): string
{
    $payload = array('fs_path' => $fsPath);
    if ($docPath !== '') {
        $payload['doc_path'] = $docPath;
    }
    $data = imageext_probe($siteDir, 'fstkey', $payload);
    return isset($data['result']) ? (string) $data['result'] : '';
}

function imageext_resolve(string $siteDir, string $path, string $docPath): bool
{
    $data = imageext_probe($siteDir, 'resolve', array(
        'path' => $path,
        'doc_path' => $docPath,
    ));
    return !empty($data['result']['ok']);
}

return TestAssert::runSuite(function () {
    echo "=== normalizeUploadKey: root deploy ===\n";

    TestAssert::same(
        '/static/upload/a.jpg',
        imageext_normalize('', '/static/upload/a.jpg'),
        'root relative path'
    );
    TestAssert::same(
        '/static/upload/a.jpg',
        imageext_normalize('', 'http://example.com/static/upload/a.jpg'),
        'root absolute URL'
    );

    echo "=== normalizeUploadKey: single-level subdir ===\n";

    TestAssert::same(
        '/static/upload/a.jpg',
        imageext_normalize('/cms', '/cms/static/upload/a.jpg'),
        'subdir relative path'
    );
    TestAssert::same(
        '/static/upload/a.jpg',
        imageext_normalize('/cms', 'http://example.com/cms/static/upload/a.jpg'),
        'subdir absolute URL'
    );

    echo "=== normalizeUploadKey: multi-level subdir ===\n";

    TestAssert::same(
        '/static/upload/a.jpg',
        imageext_normalize('/a/b', '/a/b/static/upload/a.jpg'),
        'multi-level subdir path'
    );

    echo "=== normalizeUploadKey: legacy / malicious paths ===\n";

    TestAssert::same(
        '/static/upload/a.jpg',
        imageext_normalize('/cms', '/cms/static/upload/../upload/a.jpg'),
        'legacy redundant segment folded'
    );
    TestAssert::same(
        '',
        imageext_normalize('/cms', '/cms/static/upload/../../../config/database.php'),
        'traversal beyond upload rejected'
    );
    TestAssert::same(
        '',
        imageext_normalize('/cms', 'C:/windows/system32/config/sam'),
        'non-upload absolute path rejected'
    );
    TestAssert::same(
        '',
        imageext_normalize('/cms', '/etc/passwd'),
        'forged absolute path rejected'
    );

    echo "=== fsToKey: upload base prefix (subdir) ===\n";

    $doc = rtrim(str_replace('\\', '/', ROOT_PATH), '/') . '/';
    $fsPath = $doc . 'cms/static/upload/2024/a.jpg';
    TestAssert::same(
        '/static/upload/2024/a.jpg',
        imageext_fstkey('/cms', $fsPath, $doc),
        'disk path under upload base'
    );

    echo "=== resolveInsideUpload: boundary checks ===\n";

    $tmpRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot_imageext_' . uniqid('', true);
    $uploadDir = $tmpRoot . DIRECTORY_SEPARATOR . 'cms' . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'upload';
    $outsideFile = $tmpRoot . DIRECTORY_SEPARATOR . 'outside.txt';
    mkdir($uploadDir, 0777, true);
    file_put_contents($uploadDir . DIRECTORY_SEPARATOR . 'allowed.jpg', 'ok');
    file_put_contents($outsideFile, 'no');
    $docPath = $tmpRoot . DIRECTORY_SEPARATOR;

    TestAssert::true(
        imageext_resolve(
            '/cms',
            $uploadDir . DIRECTORY_SEPARATOR . 'allowed.jpg',
            $docPath
        ),
        'file inside upload accepted'
    );
    TestAssert::false(
        imageext_resolve('/cms', $outsideFile, $docPath),
        'file outside upload rejected'
    );
    TestAssert::false(
        imageext_resolve(
            '/cms',
            $uploadDir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'outside.txt',
            $docPath
        ),
        'directory traversal rejected'
    );

    @unlink($uploadDir . DIRECTORY_SEPARATOR . 'allowed.jpg');
    @unlink($outsideFile);
    @rmdir($uploadDir);
    @rmdir(dirname($uploadDir));
    @rmdir(dirname(dirname($uploadDir)));
    @rmdir($tmpRoot);
});
