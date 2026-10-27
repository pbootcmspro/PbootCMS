<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers apps/、core/ 不得直接调用 curl_close()（PHP 8.5 弃用）
 *
 * 调试模式错误掩码含 E_DEPRECATED。Basic::errorHandler 把该级别走入 error() 并 exit()，
 * 于是 httpPostJson() 与 post_indexnow() 在关闭句柄时丢掉已经取到的结果。
 * 允许保留：curl_safe_close() 内部（PHP 7 resource），以及 remote_fetch.php 在
 * curl_safe_close 尚未加载时的 function_exists 兜底。
 *
 * 运行：
 *   php tests/run.php --suite=contract --filter=CurlCloseDeprecated
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

/**
 * @return array<int, string>
 */
function curl_close_forbidden_calls(): array
{
    $hits = array();
    $roots = array(
        'apps' => APP_PATH,
        'core' => CORE_PATH,
    );
    foreach ($roots as $label => $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (! $file->isFile() || substr($file->getFilename(), -4) !== '.php') {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            if ($src === false || strpos($src, 'curl_close(') === false) {
                continue;
            }
            $rel = $label . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $offset = 0;
            while (($pos = strpos($src, 'curl_close(', $offset)) !== false) {
                if (! curl_close_call_allowed($rel, $src, $pos)) {
                    $hits[] = $rel . ':' . (substr_count(substr($src, 0, $pos), "\n") + 1);
                }
                $offset = $pos + 11;
            }
        }
    }
    return $hits;
}

function curl_close_call_allowed(string $rel, string $src, int $pos): bool
{
    if ($rel === 'core/function/handle.php') {
        $start = strpos($src, 'function curl_safe_close');
        if ($start === false || $pos < $start) {
            return false;
        }
        $end = strpos($src, "\nfunction ", $start);
        if ($end === false) {
            $end = strlen($src);
        }
        return $pos < $end;
    }
    if ($rel === 'core/function/remote_fetch.php') {
        $guard = strpos($src, "if (function_exists('curl_safe_close'))");
        if ($guard === false || $pos < $guard) {
            return false;
        }
        $elsePos = strpos($src, '} else {', $guard);
        $safePos = strpos($src, 'curl_safe_close($ch);', $guard);
        $closePos = strpos($src, 'curl_close(', $guard);
        return $elsePos !== false
            && $safePos !== false
            && $safePos < $elsePos
            && $closePos === $pos
            && $elsePos < $pos;
    }
    return false;
}

return TestAssert::runSuite(function () {
    echo "=== apps/ core/ 不再直接调用 curl_close( ===\n";

    $hits = curl_close_forbidden_calls();
    TestAssert::same(array(), $hits, 'no bare curl_close( outside the two allowed sites');

    $handle = file_get_contents(CORE_PATH . '/function/handle.php');
    TestAssert::false($handle === false, 'handle.php readable');
    TestAssert::contains($handle, 'function curl_safe_close(&$ch)', 'curl_safe_close takes the handle by reference');
    TestAssert::contains($handle, 'is_resource($ch)', 'PHP 7 resource still closed');

    $fetch = file_get_contents(CORE_PATH . '/function/remote_fetch.php');
    TestAssert::false($fetch === false, 'remote_fetch.php readable');
    TestAssert::contains($fetch, "function_exists('curl_safe_close')", 'remote_fetch keeps function_exists fallback');

    echo "=== httpPostJson() 与 post_indexnow() 使用 curl_safe_close ===\n";

    $ai = file_get_contents(APP_PATH . '/admin/controller/content/AiController.php');
    TestAssert::false($ai === false, 'AiController readable');
    $postPos = strpos($ai, 'private function httpPostJson(');
    TestAssert::false($postPos === false, 'httpPostJson() found');
    $postEnd = strpos($ai, 'private function parseJsonReply(', $postPos);
    TestAssert::false($postEnd === false, 'parseJsonReply() after httpPostJson()');
    $postBody = substr($ai, $postPos, $postEnd - $postPos);
    TestAssert::notContains($postBody, 'curl_close(', 'httpPostJson() does not call curl_close(');
    TestAssert::same(2, substr_count($postBody, 'curl_safe_close($ch)'), 'httpPostJson() closes the handle on both paths');

    $common = file_get_contents(APP_PATH . '/common/function.php');
    TestAssert::false($common === false, 'function.php readable');
    $idxPos = strpos($common, 'function post_indexnow(');
    TestAssert::false($idxPos === false, 'post_indexnow() found');
    $idxEnd = strpos($common, 'function get_indexnow_msg(', $idxPos);
    TestAssert::false($idxEnd === false, 'get_indexnow_msg() after post_indexnow()');
    $idxBody = substr($common, $idxPos, $idxEnd - $idxPos);
    TestAssert::notContains($idxBody, 'curl_close(', 'post_indexnow() does not call curl_close(');
    TestAssert::contains($idxBody, 'curl_safe_close($ch)', 'post_indexnow() uses curl_safe_close');

    echo "=== 调试掩码下 curl_safe_close 不触发 E_DEPRECATED ===\n";

    if (! function_exists('curl_init')) {
        echo "SKIP: curl extension missing\n";
    } else {
        $deprecations = 0;
        set_error_handler(static function ($errno) use (&$deprecations) {
            if ($errno === E_DEPRECATED) {
                $deprecations++;
            }
            return true;
        });
        $previous = error_reporting(E_ALL ^ E_WARNING ^ E_NOTICE);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:1/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_exec($ch);
        curl_safe_close($ch);
        error_reporting($previous);
        restore_error_handler();
        TestAssert::same(0, $deprecations, 'curl_safe_close emits no E_DEPRECATED under debug mask');
    }
});
