<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers AiController::ping() OpenSSL/解密失败/Token/截断容错
 *
 * 运行：
 *   php tests/run.php --suite=contract --filter=AiPingPing
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . 'apps');
}

return TestAssert::runSuite(function () {
    echo "=== AiController::ping() readable ===\n";

    $src = file_get_contents(APP_PATH . '/admin/controller/content/AiController.php');
    TestAssert::false($src === false, 'AiController readable');

    $pos = strpos($src, 'public function ping()');
    TestAssert::false($pos === false, 'ping() found');
    $next = strpos($src, 'public function generate()', $pos);
    TestAssert::false($next === false, 'generate() after ping()');
    $body = substr($src, $pos, $next - $pos);

    echo "=== OpenSSL guard before getProviderConfig ===\n";

    TestAssert::contains($body, "extension_loaded('openssl')", 'ping checks openssl extension');
    TestAssert::contains($body, '服务端 OpenSSL 扩展不可用', 'ping openssl error message');
    $posCfg = strpos($body, '$cfg = $this->getProviderConfig();');
    $posSsl = strpos($body, "extension_loaded('openssl')");
    TestAssert::true($posSsl !== false && $posCfg !== false && $posSsl < $posCfg, 'openssl check runs before getProviderConfig');

    echo "=== decrypt failure vs empty key ===\n";

    TestAssert::contains($body, "\$cipher = \$this->config('ai_api_key');", 'ping reads cipher before decrypt');
    TestAssert::contains($body, 'API Key 解密失败，请重新保存 API Key', 'ping decrypt-fail message');
    TestAssert::contains($body, '未配置 API Key', 'ping empty-key message');

    echo "=== ping uses adequate max_tokens ===\n";

    TestAssert::contains($body, '), 256);', 'ping callAi max_tokens is 256 not 32');
    TestAssert::notContains($body, '), 32);', 'ping no longer uses 32-token limit');

    echo "=== finish_reason=length treated as connectivity ok ===\n";

    TestAssert::contains($body, 'finish_reason=length', 'ping handles truncated response');
    TestAssert::contains($body, '$this->jsonOk(array(\'reply\' =>', 'ping jsonOk on length truncation');
    TestAssert::contains($body, 'API 连接正常（响应被截断', 'ping truncation user message');
});
