<?php

/**
 * 上传目录响应头冒烟探测（需本机 Web 服务可访问站点）
 *
 * 用法：
 *   php tests/scripts/upload_htaccess_headers_probe.php [baseUrl]
 * 例：
 *   php tests/scripts/upload_htaccess_headers_probe.php http://127.0.0.1/PbootCMS-dev
 *
 * 自动化门禁集成测试（同断言）：
 *   set PBOOT_TEST_BASE_URL=http://127.0.0.1/PbootCMS-dev
 *   php tests/run.php --suite=integration --filter=UploadHtaccessHeadersHttp
 *
 * 无 curl 扩展时仅打印建议的 curl -I 命令并退出 0。
 */

define('ROOT_PATH', dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR);

$base = isset($argv[1]) ? rtrim($argv[1], '/') : '';
$probeRel = '/static/upload/.htaccess';
$sampleSvg = null;
$imgDir = ROOT_PATH . 'static/upload/image';
if (is_dir($imgDir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($imgDir));
    foreach ($it as $f) {
        if ($f->isFile() && strtolower($f->getExtension()) === 'svg') {
            $full = str_replace('\\', '/', $f->getPathname());
            $root = str_replace('\\', '/', ROOT_PATH);
            $sampleSvg = '/' . ltrim(substr($full, strlen($root)), '/');
            break;
        }
    }
}

$urls = array();
if ($base !== '') {
    if ($sampleSvg) {
        $urls[] = $base . $sampleSvg;
    }
    $urls[] = $base . '/static/upload/image/';
}

echo "=== upload htaccess / MIME probe ===\n";
echo "Deploy file: " . (is_file(ROOT_PATH . 'static/upload/.htaccess') ? 'OK' : 'MISSING') . "\n";
echo "Template: " . (is_file(ROOT_PATH . 'rewrite/static-upload.htaccess') ? 'OK' : 'MISSING') . "\n";
if ($sampleSvg) {
    echo "Sample SVG: {$sampleSvg}\n";
}

if ($base === '' || ! function_exists('curl_init')) {
    echo "\nSuggested checks (Apache / after nginx snippet applied):\n";
    $path = $sampleSvg ?: '/static/upload/image/your.svg';
    echo "  curl -sI http://your-host{$path}\n";
    echo "  Expect: Content-Type: image/svg+xml\n";
    echo "  Expect: X-Content-Type-Options: nosniff\n";
    exit(0);
}

$ok = true;
foreach ($urls as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
    ));
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "\nURL: {$url}\nHTTP: {$code}\n";
    if (! is_string($raw)) {
        echo "FAIL: no response\n";
        $ok = false;
        continue;
    }
    echo $raw;
    if ($sampleSvg && strpos($url, $sampleSvg) !== false) {
        if (! preg_match('/^Content-Type:\\s*image\\/svg\\+xml/im', $raw)) {
            echo "WARN: missing image/svg+xml (Apache AllowOverride / nginx types?)\n";
            $ok = false;
        }
        if (! preg_match('/^X-Content-Type-Options:\\s*nosniff/im', $raw)) {
            echo "WARN: missing nosniff\n";
            $ok = false;
        }
    }
}

exit($ok ? 0 : 1);
