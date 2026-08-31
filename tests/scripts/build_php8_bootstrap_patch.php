<?php

/**
 * 打包 PHP 8 在线升级自举补丁 zip（PHP 7.0+）
 *
 * 用法: php tests/scripts/build_php8_bootstrap_patch.php [输出路径]
 * 默认输出: tests/tmp_php8_bootstrap/php8-online-upgrade-bootstrap.zip
 */

$root = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR;
$manifestFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR
    . 'php8_upgrade_bootstrap' . DIRECTORY_SEPARATOR . 'manifest.txt';
$defaultOut = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp_php8_bootstrap' . DIRECTORY_SEPARATOR
    . 'php8-online-upgrade-bootstrap.zip';
$outPath = isset($argv[1]) ? (string) $argv[1] : $defaultOut;

if (! is_readable($manifestFile)) {
    fwrite(STDERR, "manifest not found: $manifestFile\n");
    exit(1);
}

$paths = array();
foreach (file($manifestFile, FILE_IGNORE_NEW_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
        continue;
    }
    $paths[] = str_replace('/', DIRECTORY_SEPARATOR, $line);
}

if (! class_exists('ZipArchive')) {
    $outDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp_php8_bootstrap' . DIRECTORY_SEPARATOR
        . 'php8-online-upgrade-bootstrap';
    if (! is_dir($outDir) && ! mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
        fwrite(STDERR, "cannot create output dir: $outDir\n");
        exit(1);
    }
    $copied = 0;
    foreach ($paths as $rel) {
        $abs = $root . $rel;
        if (! is_file($abs)) {
            fwrite(STDERR, "missing file: $rel\n");
            exit(1);
        }
        $dest = $outDir . DIRECTORY_SEPARATOR . $rel;
        $destParent = dirname($dest);
        if (! is_dir($destParent) && ! mkdir($destParent, 0755, true) && ! is_dir($destParent)) {
            fwrite(STDERR, "cannot create dir: $destParent\n");
            exit(1);
        }
        if (! copy($abs, $dest)) {
            fwrite(STDERR, "copy failed: $rel\n");
            exit(1);
        }
        $copied++;
    }
    file_put_contents(
        $outDir . DIRECTORY_SEPARATOR . 'README.txt',
        "PHP 8 在线升级自举补丁\n\n详见 doc/php8-online-upgrade-bootstrap.md\n"
    );
    echo "ZipArchive unavailable; copied $copied files to $outDir\n";
    exit(0);
}

$outDir = dirname($outPath);
if (! is_dir($outDir) && ! mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
    fwrite(STDERR, "cannot create output dir: $outDir\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($outPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "cannot open zip: $outPath\n");
    exit(1);
}

$added = 0;
foreach ($paths as $rel) {
    $abs = $root . $rel;
    if (! is_file($abs)) {
        fwrite(STDERR, "missing file: $rel\n");
        $zip->close();
        @unlink($outPath);
        exit(1);
    }
    $zipPath = str_replace('\\', '/', $rel);
    if (! $zip->addFile($abs, $zipPath)) {
        fwrite(STDERR, "add failed: $rel\n");
        $zip->close();
        @unlink($outPath);
        exit(1);
    }
    $added++;
}

$readme = "PHP 8 在线升级自举补丁\n\n"
    . "适用: V3.2.14 及相近旧版在 PHP 8 下无法完成首次在线检查/下载时\n\n"
    . "步骤:\n"
    . "1. 备份站点\n"
    . "2. 将 zip 内文件按路径覆盖到站点根目录\n"
    . "3. 后台「系统 → 在线更新」检查并升级到目标版本\n\n"
    . "详见 doc/php8-online-upgrade-bootstrap.md\n";
$zip->addFromString('README.txt', $readme);

$zip->close();

echo "Built $outPath ($added files)\n";
exit(0);
