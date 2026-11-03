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
    $posCfg = strpos($body, 'getProviderConfig()');
    $posSsl = strpos($body, "extension_loaded('openssl')");
    TestAssert::true($posSsl !== false && $posCfg !== false && $posSsl < $posCfg, 'openssl check runs before getProviderConfig');

    echo "=== decrypt failure vs empty key ===\n";

    TestAssert::contains($body, "\$cipher = \$this->config('ai_api_key');", 'ping reads cipher before decrypt');
    TestAssert::contains($body, 'API Key 解密失败，请重新保存 API Key', 'ping decrypt-fail message');
    TestAssert::contains($body, '未配置 API Key', 'ping empty-key message');

    echo "=== ping uses adequate max_tokens ===\n";

    TestAssert::contains($body, '), 256, null, $cfg);', 'ping callAi max_tokens is 256 not 32');
    TestAssert::notContains($body, '), 32);', 'ping no longer uses 32-token limit');

    echo "=== truncation is judged by structured reason, not by message text ===\n";

    TestAssert::contains($body, "\$rs['reason'] === 'length'", 'ping checks structured truncation reason');
    TestAssert::notContains($body, "strpos((string) (\$rs['msg']", 'ping no longer matches error text');
    TestAssert::contains($body, 'API 连接正常（响应被截断', 'ping truncation user message');
    TestAssert::notContains($body, '请适当增大', 'ping truncation does not ask for a larger max token');

    echo "=== ping does not require AI enabled ===\n";

    TestAssert::notContains($body, '$this->guard();', 'ping skips enabled check');
    TestAssert::contains($body, '$this->guardConfigAccess();', 'ping keeps config permission');
    TestAssert::contains($body, '$this->consumeRateLimit();', 'ping keeps rate limit');

    echo "=== ping applies form draft without saving ===\n";

    TestAssert::contains($body, 'readPingDraft()', 'ping reads POST draft');
    TestAssert::contains($body, 'mergeAiDraft(', 'ping merges draft over saved config');
    TestAssert::contains($body, ', null, $cfg)', 'ping passes draft cfg into callAi');
    TestAssert::contains($src, 'is_masked_secret($key)', 'draft ignores masked API key');
    TestAssert::notContains($body, 'modValue', 'ping never writes config');
});
