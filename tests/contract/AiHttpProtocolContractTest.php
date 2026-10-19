<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers AiController::httpPostJson() 仅允许 HTTP/HTTPS 协议
 *
 * 背景：API URL 写成 file:///路径#chat/completions 可通过 validateAiConfig()，
 * 未限制协议时 cURL 读取本地文件并把内容拼进报错返回后台（#315）。
 *
 * 运行：
 *   php tests/run.php --suite=contract --filter=AiHttpProtocol
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . 'apps');
}

return TestAssert::runSuite(function () {
    echo "=== AiController::httpPostJson() readable ===\n";

    $src = file_get_contents(APP_PATH . '/admin/controller/content/AiController.php');
    TestAssert::false($src === false, 'AiController readable');

    $pos = strpos($src, 'private function httpPostJson(');
    TestAssert::false($pos === false, 'httpPostJson() found');
    $exec = strpos($src, 'curl_exec($ch)', $pos);
    TestAssert::false($exec === false, 'curl_exec() in httpPostJson()');
    $body = substr($src, $pos, $exec - $pos);

    echo "=== protocols limited to HTTP/HTTPS before curl_exec ===\n";

    TestAssert::contains($body, 'curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);', 'request protocols limited');
    TestAssert::contains($body, 'curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);', 'redirect protocols limited');
});
