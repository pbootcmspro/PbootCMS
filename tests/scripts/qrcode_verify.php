<?php

declare(strict_types=1);

/**
 * 验证 QRcode 在 display_errors=On + E_DEPRECATED 下输出有效 PNG。
 * 用法：php -d display_errors=1 -d error_reporting=30719 tests/scripts/qrcode_verify.php [direct|entry|all]
 */

$root = dirname(__DIR__, 2);
$mode = $argv[1] ?? 'all';

function verify_png_output(string $label, string $output): bool
{
    $len = strlen($output);
    $magic = $len >= 4 ? bin2hex(substr($output, 0, 4)) : 'too-short';
    $hasDeprecated = stripos($output, 'Deprecated') !== false;
    $validPng = strncmp($output, "\x89PNG", 4) === 0;

    echo "[$label]\n";
    echo "  bytes: $len\n";
    echo "  PNG magic: $magic " . ($validPng ? '(OK)' : '(FAIL)') . "\n";
    echo "  Deprecated text: " . ($hasDeprecated ? 'YES (FAIL)' : 'NO (OK)') . "\n";

    return $validPng && !$hasDeprecated;
}

function run_subprocess(string $scriptMode): int
{
    $php = PHP_BINARY;
    $script = __FILE__;
    $cmd = escapeshellarg($php)
        . ' -d display_errors=1 -d error_reporting=30719 '
        . escapeshellarg($script) . ' ' . escapeshellarg($scriptMode);
    passthru($cmd, $code);
    return (int) $code;
}

if ($mode === 'all') {
    // 分进程跑，避免 phpqrcode.php 重复声明类/常量
    $ok = run_subprocess('direct') === 0;
    $ok = run_subprocess('entry') === 0 && $ok;
    exit($ok ? 0 : 1);
}

$ok = true;

if ($mode === 'direct') {
    ob_start();
    require $root . '/core/extend/qrcode/phpqrcode.php';
    QRcode::png('test', false, 'M', 6, 1);
    $ok = verify_png_output('phpqrcode direct', (string) ob_get_clean()) && $ok;
}

if ($mode === 'entry') {
    ob_start();
    $_GET['string'] = 'test';
    include $root . '/core/qrcode.php';
    $ok = verify_png_output('core/qrcode.php entry', (string) ob_get_clean()) && $ok;
}

exit($ok ? 0 : 1);
