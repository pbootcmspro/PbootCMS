<?php

declare(strict_types=1);

/**
 * @suite integration
 * @covers AiController 测试连接草稿、旧 Key 保护、请求地址、提示与日志、OpenAI 兼容服务商（#330）
 *
 * 每个场景在子进程中调用真实入口，cURL 桩记录实际请求，不连接外部服务。
 *
 * 运行：
 *   php tests/run.php --suite=integration --filter=AiRequestBehavior
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

if (! extension_loaded('openssl') || ! defined('CURLOPT_URL')) {
    echo "SKIP: openssl / cURL unavailable\n";
    return 0;
}

function ai_request_probe(array $scenario): array
{
    $file = tempnam(sys_get_temp_dir(), 'aireq');
    $payload = $scenario;
    unset($payload['ini']);
    file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE));
    $php = PHP_BINARY ?: 'php';
    $iniArgs = '';
    if (isset($scenario['ini']) && is_array($scenario['ini'])) {
        foreach ($scenario['ini'] as $k => $v) {
            $iniArgs .= ' -d ' . escapeshellarg($k . '=' . $v);
        }
    }
    $cmd = $php . $iniArgs . ' ' . escapeshellarg(TEST_ROOT . 'scripts' . DIRECTORY_SEPARATOR . 'ai_request_probe.php') . ' ' . escapeshellarg($file);
    $out = (string) shell_exec($cmd);
    @unlink($file);
    $data = json_decode($out, true);
    TestAssert::true(is_array($data), 'probe returns JSON: ' . substr($out, 0, 300));
    if (! is_array($data)) {
        return array('response' => array('code' => -1, 'msg' => '', 'data' => null), 'requests' => array(), 'log' => '', 'raw' => $out);
    }
    if (! is_array($data['response'])) {
        $data['response'] = array('code' => -1, 'msg' => (string) $data['raw'], 'data' => null);
    }
    return $data;
}

function ai_chat_reply($content, $finish = 'stop'): array
{
    return array('status' => 200, 'body' => array(
        'choices' => array(array('message' => array('content' => $content), 'finish_reason' => $finish)),
    ));
}

return TestAssert::runSuite(function () {
    $savedKey = 'saved-key-ABCDEFGH1234';
    $masked = 'save****1234';
    $deepseek = array('ai_enabled' => '1', 'ai_provider' => 'deepseek', 'ai_api_base' => '', 'ai_model' => '');
    $ping = function (array $saved, array $post, array $extra = array()) use ($savedKey) {
        return ai_request_probe(array_merge(array(
            'action' => 'ping',
            'saved' => $saved,
            'saved_key' => $savedKey,
            'post' => $post,
        ), $extra));
    };

    echo "=== query string survives post() escaping in draft and saved config ===\n";
    $rs = $ping($deepseek, array(
        'ai_provider' => 'openai', 'ai_api_base' => 'https://gateway.example/v1?a=1&b=2',
        'ai_model' => 'gpt-4o-mini', 'ai_api_key' => 'draft-key-ZYXWVUT9876',
    ));
    TestAssert::same(1, $rs['response']['code'], 'draft ping succeeds');
    TestAssert::same('https://gateway.example/v1/chat/completions?a=1&b=2', $rs['requests'][0]['url'], 'draft request keeps a=1&b=2');
    $rs = ai_request_probe(array(
        'action' => 'generate',
        'saved' => array('ai_enabled' => '1', 'ai_provider' => 'openai', 'ai_api_base' => 'https://gateway.example/v1?a=1&b=2', 'ai_model' => 'gpt-4o-mini'),
        'saved_key' => $savedKey,
        'post' => array('title' => '标题'),
        'replies' => array(ai_chat_reply('<p>正文</p>')),
    ));
    TestAssert::same(1, $rs['response']['code'], 'saved generate succeeds');
    TestAssert::same('https://gateway.example/v1/chat/completions?a=1&b=2', $rs['requests'][0]['url'], 'saved request keeps a=1&b=2');

    echo "=== draft and saved config give the same URL and protocol ===\n";
    foreach (array('https://api.anthropic.com' => 'https://api.anthropic.com/v1/messages', 'https://gw.example/v1/messages?beta=true' => 'https://gw.example/v1/messages?beta=true') as $base => $url) {
        $draft = $ping(array_merge($deepseek, array('ai_provider' => 'claude', 'ai_api_base' => $base)), array(
            'ai_provider' => 'claude', 'ai_api_base' => $base, 'ai_model' => '', 'ai_api_key' => $masked,
        ), array('replies' => array(array('status' => 200, 'body' => array('content' => array(array('type' => 'text', 'text' => 'ok')), 'stop_reason' => 'end_turn')))));
        $saved = ai_request_probe(array(
            'action' => 'tdk', 'saved' => array_merge($deepseek, array('ai_provider' => 'claude', 'ai_api_base' => $base)), 'saved_key' => $savedKey,
            'post' => array('title' => 't'),
            'replies' => array(array('status' => 200, 'body' => array('content' => array(array('type' => 'text', 'text' => '{"title":"a","description":"b","keywords":"c"}')), 'stop_reason' => 'end_turn'))),
        ));
        TestAssert::same($url, $draft['requests'][0]['url'], $base . ' draft URL');
        TestAssert::same($url, $saved['requests'][0]['url'], $base . ' saved URL');
        TestAssert::true(isset($draft['requests'][0]['payload']['messages']) && ! isset($draft['requests'][0]['payload']['stream']), $base . ' draft uses Anthropic body');
        TestAssert::false(isset($saved['requests'][0]['payload']['stream']), $base . ' saved uses Anthropic body');
    }

    echo "=== ping works while AI is disabled; formal entries stay disabled ===\n";
    $off = array_merge($deepseek, array('ai_enabled' => '0'));
    $rs = $ping($off, array('ai_provider' => 'deepseek', 'ai_api_base' => '', 'ai_model' => '', 'ai_api_key' => $masked));
    TestAssert::same(1, $rs['response']['code'], 'disabled AI can still be tested');
    TestAssert::same(1, count($rs['requests']), 'ping sends one request');
    foreach (array('generate' => array('title' => 't'), 'rewrite' => array('text' => 'x', 'mode' => 'rewrite'), 'tdk' => array('title' => 't'), 'alt' => array('title' => 't', 'image_src' => '/static/upload/image/probe.png')) as $action => $post) {
        $rs = ai_request_probe(array('action' => $action, 'saved' => $off, 'saved_key' => $savedKey, 'post' => $post));
        TestAssert::same('AI 功能未启用', $rs['response']['msg'], $action . ' refused while disabled');
        TestAssert::same(0, count($rs['requests']), $action . ' sends nothing while disabled');
    }

    echo "=== ping keeps permission and rate limit ===\n";
    $rs = $ping($deepseek, array('ai_api_key' => $masked), array('session' => array('id' => 2, 'levels' => array())));
    TestAssert::same('您的账号权限不足', $rs['response']['msg'], 'no config permission refused');
    TestAssert::same(0, count($rs['requests']), 'no request without permission');
    $rs = $ping($deepseek, array('ai_api_key' => $masked), array('session' => array('id' => 2, 'levels' => array('/admin/Config/index'))));
    TestAssert::same(1, $rs['response']['code'], 'config permission is enough');
    $rs = $ping($deepseek, array('ai_api_key' => $masked), array('rate_exhausted' => true));
    TestAssert::same('调用过于频繁，请稍后重试', $rs['response']['msg'], 'rate limit applies to ping');
    TestAssert::same(0, count($rs['requests']), 'no request over rate limit');

    echo "=== reusing the saved key on another host or port is refused before any request ===\n";
    $blocked = array(
        'other host' => 'https://evil.example/chat/completions',
        'subdomain' => 'https://x.api.deepseek.com/chat/completions',
        'explicit port' => 'https://api.deepseek.com:8443/chat/completions',
        'http default port' => 'http://api.deepseek.com/chat/completions',
    );
    foreach ($blocked as $label => $base) {
        foreach (array($masked, '') as $key) {
            $rs = $ping($deepseek, array('ai_provider' => 'deepseek', 'ai_api_base' => $base, 'ai_model' => '', 'ai_api_key' => $key));
            TestAssert::same(0, $rs['response']['code'], "$label ($key) refused");
            TestAssert::contains($rs['response']['msg'], '重新输入完整 API Key', "$label asks for the full key");
            TestAssert::contains($rs['response']['msg'], '未发出请求', "$label says no request was sent");
            TestAssert::same(0, count($rs['requests']), "$label sends zero HTTP requests");
            TestAssert::false($rs['response']['data']['requested'], "$label summary marks not requested");
        }
    }
    $rs = $ping($deepseek, array('ai_provider' => 'qwen', 'ai_api_base' => '', 'ai_model' => '', 'ai_api_key' => $masked));
    TestAssert::same(0, count($rs['requests']), 'switching provider to another official host is refused');
    $rs = $ping(array_merge($deepseek, array('ai_provider' => 'openai', 'ai_api_base' => 'https://gw.example/v1', 'ai_model' => 'm')), array(
        'ai_provider' => 'openai', 'ai_api_base' => '', 'ai_model' => 'm', 'ai_api_key' => $masked,
    ));
    TestAssert::same(0, count($rs['requests']), 'clearing the URL is not exempt');

    echo "=== same host and effective port may reuse the saved key ===\n";
    $allowed = array(
        'host case' => 'HTTPS://API.DEEPSEEK.COM/chat/completions',
        'explicit 443' => 'https://api.deepseek.com:443/chat/completions',
        'other path and query' => 'https://api.deepseek.com/v1?tenant=1',
    );
    foreach ($allowed as $label => $base) {
        $rs = $ping($deepseek, array('ai_provider' => 'deepseek', 'ai_api_base' => $base, 'ai_model' => '', 'ai_api_key' => $masked));
        TestAssert::same(1, $rs['response']['code'], "$label allowed");
        TestAssert::same(1, count($rs['requests']), "$label sends one request");
        TestAssert::contains(implode("\n", $rs['requests'][0]['headers']), $savedKey, "$label uses the saved key");
    }
    $rs = $ping(array_merge($deepseek, array('ai_provider' => 'openai', 'ai_api_base' => 'http://gw.local:8080/v1', 'ai_model' => 'm')), array(
        'ai_provider' => 'openai', 'ai_api_base' => 'https://gw.local:8080/v1', 'ai_model' => 'm', 'ai_api_key' => $masked,
    ));
    TestAssert::same(1, count($rs['requests']), 'protocol change with the same explicit port is allowed');
    $rs = $ping(array_merge($deepseek, array('ai_provider' => 'qwen', 'ai_api_base' => 'https://api.deepseek.com/chat/completions')), array(
        'ai_provider' => 'deepseek', 'ai_api_base' => '', 'ai_model' => '', 'ai_api_key' => $masked,
    ));
    TestAssert::same(1, count($rs['requests']), 'switching provider on the same final host is allowed');

    echo "=== incomplete saved config can be repaired by the draft ===\n";
    $broken = array('ai_enabled' => '0', 'ai_provider' => 'openai', 'ai_api_base' => '', 'ai_model' => '');
    $rs = $ping($broken, array('ai_provider' => 'openai', 'ai_api_base' => 'https://gw.example/v1', 'ai_model' => 'gpt-4.1-mini', 'ai_api_key' => $masked));
    TestAssert::contains($rs['response']['msg'], '重新输入完整 API Key', 'unresolvable saved address requires the full key');
    TestAssert::same(0, count($rs['requests']), 'no request while reusing the key of an unresolvable address');
    $rs = $ping($broken, array('ai_provider' => 'openai', 'ai_api_base' => 'https://gw.example/v1', 'ai_model' => 'gpt-4.1-mini', 'ai_api_key' => 'new-full-key-1234567'));
    TestAssert::same(1, $rs['response']['code'], 'full new key repairs the broken config');
    $rs = ai_request_probe(array('action' => 'ping', 'saved' => $deepseek, 'post' => array('ai_provider' => 'deepseek', 'ai_api_key' => '')));
    TestAssert::contains($rs['response']['msg'], '未配置 API Key', 'no key at all keeps the missing-key message');
    TestAssert::same(0, count($rs['requests']), 'missing key sends nothing');
    $rs = ai_request_probe(array('action' => 'ping', 'saved' => $deepseek, 'saved_cipher' => 'bm90LWEtcmVhbC1jaXBoZXItdGV4dC0xMjM0NTY3OA==', 'post' => array('ai_api_key' => '')));
    TestAssert::contains($rs['response']['msg'], 'API Key 解密失败', 'undecryptable key keeps the decrypt message');

    echo "=== invalid draft config: no request, safe summary ===\n";
    $rs = $ping($deepseek, array('ai_provider' => 'openai', 'ai_api_base' => 'ftp://user:topsecret@gw.example/v1', 'ai_model' => 'm', 'ai_api_key' => 'new-full-key-1234567'));
    TestAssert::same(0, count($rs['requests']), 'non-HTTP URL sends nothing');
    TestAssert::contains($rs['response']['msg'], '未发出请求', 'invalid URL says no request was sent');
    TestAssert::same('地址无效', $rs['response']['data']['endpoint'], 'invalid URL summary is a placeholder');
    TestAssert::notContains($rs['raw'], 'topsecret', 'invalid URL credentials are never echoed');
    TestAssert::contains($rs['log'], 'provider=openai', 'invalid URL log uses the draft provider');
    $rs = $ping($deepseek, array('ai_provider' => 'openai', 'ai_api_base' => 'https://gw.example/v1', 'ai_model' => '', 'ai_api_key' => 'new-full-key-1234567'));
    TestAssert::contains($rs['response']['msg'], '模型', 'openai without model refused');
    TestAssert::same(0, count($rs['requests']), 'openai without model sends nothing');
    TestAssert::same('https://gw.example/v1/chat/completions', $rs['response']['data']['endpoint'], 'missing model still shows the resolved address');
    $rs = $ping($deepseek, array('ai_provider' => 'bogus', 'ai_api_key' => 'new-full-key-1234567'));
    TestAssert::same(0, count($rs['requests']), 'unknown provider sends nothing');
    TestAssert::contains($rs['log'], 'provider=deepseek', 'unknown draft provider falls back to the saved provider in the log');

    echo "=== results carry provider, request model and redacted address ===\n";
    $draft = array('ai_provider' => 'openai', 'ai_api_base' => 'https://u:p4ssw0rd@gw.example:8443/v1?token=supersecret123', 'ai_model' => 'gpt-4.1-mini', 'ai_api_key' => 'live-key-QWERTY12345');
    $cases = array(
        'success' => array(ai_chat_reply("<script>alert(1)</script>\n&lt;b&gt;")),
        'http 401' => array(array('status' => 401, 'body' => '{"error":"bad key live-key-QWERTY12345 token=supersecret123 p4ssw0rd"}')),
        'http 500 html' => array(array('status' => 500, 'body' => '<html><b>boom</b> live-key-QWERTY12345</html>')),
        'network' => array(array('errno' => 6, 'error' => 'Could not resolve host: gw.example?token=supersecret123')),
        'business error' => array(array('status' => 200, 'body' => array('error' => array('message' => 'model gpt-4.1-mini not found for live-key-QWERTY12345')))),
    );
    foreach ($cases as $label => $replies) {
        $rs = $ping($deepseek, $draft, array('replies' => $replies));
        $data = $rs['response']['data'];
        TestAssert::same('openai', $data['provider'], "$label provider");
        TestAssert::same('OpenAI 兼容（其他服务）', $data['provider_name'], "$label provider name");
        TestAssert::same('gpt-4.1-mini', $data['model'], "$label request model");
        TestAssert::same('https://gw.example:8443/v1/chat/completions', $data['endpoint'], "$label redacted address");
        TestAssert::true($data['requested'], "$label marked requested");
        foreach (array('live-key-QWERTY12345', 'supersecret123', 'p4ssw0rd') as $secret) {
            TestAssert::notContains($rs['raw'], $secret, "$label response hides $secret");
            TestAssert::notContains($rs['log'], $secret, "$label log hides $secret");
        }
        TestAssert::contains($rs['log'], 'provider=openai', "$label log provider follows the draft");
        TestAssert::contains($rs['log'], 'model=gpt-4.1-mini', "$label log model");
        TestAssert::contains($rs['log'], 'scene=ping', "$label log scene");
    }
    $rs = $ping($deepseek, $draft, array('replies' => array(array('status' => 401, 'body' => str_repeat('x', 190) . 'live-key-QWERTY12345'))));
    TestAssert::notContains($rs['raw'], 'live-key', 'key cut by the HTTP snippet limit is still hidden in the response');
    TestAssert::notContains($rs['log'], 'live-key', 'key cut by the HTTP snippet limit is still hidden in the log');
    $rs = $ping($deepseek, $draft, array('replies' => $cases['success']));
    TestAssert::same("<script>alert(1)</script>\n&lt;b&gt;", $rs['response']['data']['reply'], 'reply returned as plain text without backend HTML escaping');
    TestAssert::false(isset($rs['requests'][0]['payload']['temperature']), 'openai sends no temperature');
    TestAssert::false(isset($rs['requests'][0]['payload']['thinking']), 'openai sends no thinking parameter');
    TestAssert::same(false, $rs['requests'][0]['payload']['stream'], 'openai uses non-streaming request');
    TestAssert::same(256, $rs['requests'][0]['payload']['max_tokens'], 'ping uses its fixed output limit');
    TestAssert::contains(implode("\n", $rs['requests'][0]['headers']), 'Authorization: Bearer live-key-QWERTY12345', 'openai uses Bearer auth');

    echo "=== JSON slash/Unicode-escaped keys are redacted in response and log ===\n";
    $escapeKey = 'fake/Key+Review331';
    $escapeDraft = array('ai_provider' => 'openai', 'ai_api_base' => 'https://gw.example/v1', 'ai_model' => 'gpt-4.1-mini', 'ai_api_key' => $escapeKey);
    $escapeCases = array(
        'slash escape' => array(array('status' => 401, 'body' => '{"error":{"message":"Incorrect key fake\\/Key+Review331"}}')),
        'unicode escape' => array(array('status' => 401, 'body' => '{"error":{"message":"\\u0066ake\\/Key+Review331"}}')),
        'business error without message' => array(array('status' => 200, 'body' => array('error' => array('code' => $escapeKey)))),
    );
    foreach ($escapeCases as $label => $replies) {
        $rs = $ping($deepseek, $escapeDraft, array('replies' => $replies));
        TestAssert::notContains($rs['raw'], $escapeKey, "$label response hides key");
        TestAssert::notContains($rs['raw'], 'fake\\/Key+Review331', "$label response hides slash-escaped key");
        TestAssert::notContains($rs['log'], $escapeKey, "$label log hides key");
        TestAssert::notContains($rs['log'], 'fake\\/Key+Review331', "$label log hides slash-escaped key");
    }

    echo "=== URL-decoded userinfo and short query secrets stay redacted ===\n";
    $urlDraft = array(
        'ai_provider' => 'openai',
        'ai_api_base' => 'https://alice:p%40ssword@gw.example/v1?token=secret1&tenant=a',
        'ai_model' => 'gpt-4.1-mini',
        'ai_api_key' => 'draft-key-ZYXWVUT9876',
    );
    $urlCases = array(
        'labeled echo' => array(array('status' => 401, 'body' => 'Invalid credentials: password=p@ssword; token=secret1')),
        'bare values' => array(array('status' => 401, 'body' => 'bad p@ssword secret1')),
        'business error' => array(array('status' => 200, 'body' => array('error' => array('message' => 'rejected p@ssword secret1')))),
    );
    foreach ($urlCases as $label => $replies) {
        $rs = $ping($deepseek, $urlDraft, array('replies' => $replies));
        TestAssert::same('https://gw.example/v1/chat/completions', $rs['response']['data']['endpoint'], "$label endpoint");
        foreach (array('p@ssword', 'p%40ssword', 'secret1') as $secret) {
            TestAssert::notContains($rs['raw'], $secret, "$label response hides $secret");
            TestAssert::notContains($rs['log'], $secret, "$label log hides $secret");
        }
    }

    echo "=== credential echoes with tags or spaces stay redacted before cleanup ===\n";
    $mutateCases = array(
        'tag chars' => array(
            'ai_api_base' => 'https://alice:prefix%3Ctag%3Esuffix331@gw.example/v1',
            'body' => 'Invalid credentials: prefix<tag>suffix331',
            'leaks' => array('prefix<tag>suffix331', 'prefix%3Ctag%3Esuffix331', 'prefixsuffix331'),
        ),
        'double spaces' => array(
            'ai_api_base' => 'https://alice:prefix%20%20suffix331@gw.example/v1',
            'body' => 'Invalid credentials: prefix  suffix331',
            'leaks' => array('prefix  suffix331', 'prefix%20%20suffix331', 'prefix suffix331'),
        ),
    );
    foreach ($mutateCases as $label => $case) {
        $rs = $ping($deepseek, array(
            'ai_provider' => 'openai',
            'ai_api_base' => $case['ai_api_base'],
            'ai_model' => 'gpt-4.1-mini',
            'ai_api_key' => 'draft-key-ZYXWVUT9876',
        ), array('replies' => array(array('status' => 401, 'body' => $case['body']))));
        foreach ($case['leaks'] as $secret) {
            TestAssert::notContains($rs['raw'], $secret, "$label response hides $secret");
            TestAssert::notContains($rs['log'], $secret, "$label log hides $secret");
        }
    }

    echo "=== PHP budget exhaustion before curl marks not requested ===\n";
    $rs = $ping($deepseek, array(
        'ai_provider' => 'openai',
        'ai_api_base' => 'https://gw.example/v1',
        'ai_model' => 'gpt-4.1-mini',
        'ai_api_key' => 'draft-key-ZYXWVUT9876',
    ), array(
        'ini' => array(
            'max_execution_time' => '4',
            'disable_functions' => 'set_time_limit',
        ),
    ));
    TestAssert::same(0, count($rs['requests']), 'budget exhaustion sends zero HTTP requests');
    TestAssert::false($rs['response']['data']['requested'], 'budget exhaustion summary marks not requested');
    TestAssert::contains($rs['response']['msg'], '未发出请求', 'budget exhaustion says no request was sent');

    echo "=== truncation: only a real empty length cutoff counts as connected ===\n";
    $rs = $ping($deepseek, $draft, array('replies' => array(ai_chat_reply(null, 'length'))));
    TestAssert::same(1, $rs['response']['code'], 'empty length cutoff still proves connectivity');
    TestAssert::contains($rs['response']['data']['reply'], '响应被截断', 'truncation reply');
    TestAssert::notContains($rs['response']['data']['reply'], '增大', 'truncation reply does not ask for a larger max token');
    $rs = $ping($deepseek, $draft, array('replies' => array(array('status' => 400, 'body' => '{"error":"finish_reason=length is invalid"}'))));
    TestAssert::same(0, $rs['response']['code'], 'HTTP error mentioning finish_reason=length is still a failure');
    $rs = $ping($deepseek, array_merge($draft, array('ai_provider' => 'claude', 'ai_api_base' => 'https://gw.example:8443/v1/messages')), array('replies' => array(array('status' => 200, 'body' => array('content' => array(), 'stop_reason' => 'max_tokens')))));
    TestAssert::same(1, $rs['response']['code'], 'Anthropic max_tokens cutoff also proves connectivity');

    echo "=== empty-body hints: fixed-limit scenes never point at max token ===\n";
    $openaiSaved = array('ai_enabled' => '1', 'ai_provider' => 'openai', 'ai_api_base' => 'https://gw.example/v1', 'ai_model' => 'gpt-4.1-mini');
    $rs = ai_request_probe(array('action' => 'alt', 'saved' => $openaiSaved, 'saved_key' => $savedKey,
        'post' => array('title' => '标题', 'image_src' => '/static/upload/image/probe.png'),
        'replies' => array(ai_chat_reply(null, 'length'))));
    TestAssert::same(1, count($rs['requests']), 'non-vision openai model goes straight to text');
    TestAssert::notContains($rs['response']['msg'], '最大 Token', 'Alt empty body does not mention max token');
    $rs = ai_request_probe(array('action' => 'generate', 'saved' => $openaiSaved, 'saved_key' => $savedKey,
        'post' => array('title' => '标题'), 'replies' => array(ai_chat_reply(null, 'length'))));
    TestAssert::contains($rs['response']['msg'], '最大 Token', 'generate empty body keeps the max token hint');

    echo "=== OpenAI-compatible Alt: name heuristic, never another provider's vision model ===\n";
    $rs = ai_request_probe(array('action' => 'alt', 'saved' => array_merge($openaiSaved, array('ai_model' => 'gpt-4o')), 'saved_key' => $savedKey,
        'post' => array('title' => '标题', 'image_src' => '/static/upload/image/probe.png'),
        'replies' => array(ai_chat_reply('图片描述'))));
    TestAssert::same(1, $rs['response']['code'], 'gpt-4o Alt succeeds');
    TestAssert::same('gpt-4o', $rs['requests'][0]['payload']['model'], 'gpt-4o vision uses the configured model');
    TestAssert::same('image_url', $rs['requests'][0]['payload']['messages'][1]['content'][0]['type'], 'gpt-4o sends the image');
    TestAssert::contains($rs['log'], 'provider=openai', 'Alt log provider');
    $rs = ai_request_probe(array('action' => 'alt', 'saved' => array_merge($openaiSaved, array('ai_model' => 'gpt-4o')), 'saved_key' => $savedKey,
        'post' => array('title' => '标题', 'image_src' => '/static/upload/image/probe.png'),
        'replies' => array(array('status' => 400, 'body' => 'no vision'), ai_chat_reply('文本描述'))));
    TestAssert::same(2, count($rs['requests']), 'vision failure falls back to text');
    TestAssert::same('gpt-4o', $rs['requests'][1]['payload']['model'], 'text fallback keeps the configured model');
    TestAssert::true(is_string($rs['requests'][1]['payload']['messages'][1]['content']), 'text fallback sends no image');
    $rs = ai_request_probe(array('action' => 'alt', 'saved' => $openaiSaved, 'saved_key' => $savedKey,
        'post' => array('title' => '标题', 'image_src' => '/static/upload/image/probe.png'),
        'replies' => array(ai_chat_reply('文本描述'))));
    TestAssert::same(1, count($rs['requests']), 'gpt-4.1-mini skips vision');
    TestAssert::same('gpt-4.1-mini', $rs['requests'][0]['payload']['model'], 'gpt-4.1-mini is not swapped for qwen-vl-max');
    TestAssert::false(isset($rs['requests'][0]['payload']['temperature']), 'Alt openai sends no temperature');

    echo "=== OpenAI-compatible formal calls require URL and model ===\n";
    foreach (array(array('ai_api_base' => ''), array('ai_model' => '')) as $over) {
        $rs = ai_request_probe(array('action' => 'generate', 'saved' => array_merge($openaiSaved, $over), 'saved_key' => $savedKey, 'post' => array('title' => 't')));
        TestAssert::contains($rs['response']['msg'], 'AI 配置无效', 'incomplete openai config refused: ' . json_encode($over));
        TestAssert::contains($rs['response']['msg'], '未发出请求', 'refusal says no request was sent');
        TestAssert::same(0, count($rs['requests']), 'incomplete openai config sends nothing');
    }

    echo "=== DeepSeek regressions: Flash fallback, thinking off, ping keeps model ===\n";
    $pro = array_merge($deepseek, array('ai_model' => 'deepseek-v4-pro'));
    $rs = ai_request_probe(array('action' => 'generate', 'saved' => $pro, 'saved_key' => $savedKey, 'post' => array('title' => 't'), 'replies' => array(ai_chat_reply('<p>x</p>'))));
    TestAssert::same('deepseek-v4-flash', $rs['requests'][0]['payload']['model'], 'generate switches to Flash');
    TestAssert::same(array('type' => 'disabled'), $rs['requests'][0]['payload']['thinking'], 'generate disables thinking');
    TestAssert::contains($rs['log'], 'provider=deepseek', 'deepseek log provider');
    $rs = $ping($pro, array('ai_provider' => 'deepseek', 'ai_api_base' => '', 'ai_model' => 'deepseek-v4-pro', 'ai_api_key' => $masked));
    TestAssert::same('deepseek-v4-pro', $rs['requests'][0]['payload']['model'], 'ping keeps the configured model');
    TestAssert::same('https://api.deepseek.com/chat/completions', $rs['requests'][0]['url'], 'deepseek default URL unchanged');
});
