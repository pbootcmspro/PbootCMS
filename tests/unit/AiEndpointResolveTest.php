<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers apps/common/function.php ai_resolve_endpoint() / ai_effective_config() / ai_endpoint_same_host() / ai_input_unescape()（#330）
 *
 * 运行：
 *   php tests/run.php --suite=unit --filter=AiEndpointResolve
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once APP_PATH . '/common/function.php';

return TestAssert::runSuite(function () {
    $resolve = function ($url) {
        return ai_resolve_endpoint($url);
    };

    echo "=== base URL and full endpoints resolve to one request URL ===\n";
    $cases = array(
        'https://gateway.example/v1?a=1&b=2' => array('https://gateway.example/v1/chat/completions?a=1&b=2', 'openai_compat'),
        'https://gateway.example/v1/' => array('https://gateway.example/v1/chat/completions', 'openai_compat'),
        'https://gateway.example' => array('https://gateway.example/chat/completions', 'openai_compat'),
        'https://gateway.example/v1#frag' => array('https://gateway.example/v1/chat/completions', 'openai_compat'),
        'https://api.anthropic.com' => array('https://api.anthropic.com/v1/messages', 'anthropic'),
        'https://api.anthropic.com/' => array('https://api.anthropic.com/v1/messages', 'anthropic'),
        'https://API.anthropic.com/v1/' => array('https://API.anthropic.com/v1/messages', 'anthropic'),
        'https://api.anthropic.com/v1?beta=true' => array('https://api.anthropic.com/v1/messages?beta=true', 'anthropic'),
        'https://api.anthropic.com/v1/messages?beta=true' => array('https://api.anthropic.com/v1/messages?beta=true', 'anthropic'),
        'https://gateway.example/v1/messages' => array('https://gateway.example/v1/messages', 'anthropic'),
        'http://10.0.0.5:8080/v1' => array('http://10.0.0.5:8080/v1/chat/completions', 'openai_compat'),
        'http://ai_gw:8000/v1' => array('http://ai_gw:8000/v1/chat/completions', 'openai_compat'),
        'http://[::1]:8000/v1' => array('http://[::1]:8000/v1/chat/completions', 'openai_compat'),
    );
    foreach ($cases as $in => $want) {
        $ep = $resolve($in);
        TestAssert::true($ep['ok'], $in . ' is valid');
        TestAssert::same($want[0], $ep['url'], $in . ' request URL');
        TestAssert::same($want[1], $ep['protocol'], $in . ' protocol');
        $again = $resolve($ep['url']);
        TestAssert::same($ep['url'], $again['url'], $in . ' resolves idempotently');
        TestAssert::same($ep['protocol'], $again['protocol'], $in . ' keeps protocol when resolved again');
    }

    echo "=== legacy full endpoints keep the request they had before ===\n";
    $legacy = array(
        'https://api.deepseek.com/chat/completions' => array('https://api.deepseek.com/chat/completions', 'openai_compat'),
        'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions' => array('https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions', 'openai_compat'),
        'https://api.anthropic.com/v1/messages' => array('https://api.anthropic.com/v1/messages', 'anthropic'),
        'https://gw.example/v1/chat/completions/' => array('https://gw.example/v1/chat/completions', 'openai_compat'),
        'https://gw.example/V1/Chat/Completions' => array('https://gw.example/V1/Chat/Completions', 'openai_compat'),
        'https://gw.example/v1/MESSAGES/' => array('https://gw.example/v1/MESSAGES', 'anthropic'),
        'https://gw.example/custom#chat/completions' => array('https://gw.example/custom', 'openai_compat'),
        'https://gw.example/custom/#/messages' => array('https://gw.example/custom/', 'anthropic'),
        'https://gw.example/v1/chat/completions?key=1' => array('https://gw.example/v1/chat/completions?key=1', 'openai_compat'),
    );
    foreach ($legacy as $in => $want) {
        $ep = $resolve($in);
        TestAssert::same($want[0], $ep['url'], $in . ' legacy request URL');
        TestAssert::same($want[1], $ep['protocol'], $in . ' legacy protocol');
        TestAssert::same($ep, $resolve($in), $in . ' same input resolves the same way');
        if (strpos($in, '#') === false) {
            TestAssert::same($ep['url'], $resolve($ep['url'])['url'], $in . ' legacy URL is idempotent');
        }
    }

    echo "=== invalid URLs fail before any request ===\n";
    foreach (array('', 'ftp://gw.example/v1', 'file:///etc/passwd#chat/completions', 'gw.example/v1', 'https:///v1',
        'https://gw.example:0/v1', 'https://gw.example:70000/v1', 'https://gw .example/v1', "https://gw.example/v\n1",
        'https://gw.example\\v1', 'javascript:alert(1)//chat/completions', 'https://user:secret@/v1') as $bad) {
        $ep = $resolve($bad);
        TestAssert::false($ep['ok'], json_encode($bad) . ' rejected');
        TestAssert::true($ep['error'] !== '', json_encode($bad) . ' has a message');
        TestAssert::same('地址无效', $ep['summary'], json_encode($bad) . ' summary is a safe placeholder');
        TestAssert::notContains($ep['error'], 'secret', json_encode($bad) . ' error does not echo the input');
    }

    echo "=== summary hides userinfo, query and fragment ===\n";
    $ep = $resolve('https://user:pass@Gateway.example:8443/v1?token=abc#x');
    TestAssert::same('https://gateway.example:8443/v1/chat/completions', $ep['summary'], 'summary keeps port and path only');
    $ep = $resolve('https://gateway.example/v1');
    TestAssert::same('https://gateway.example/v1/chat/completions', $ep['summary'], 'implicit port omitted from summary');

    echo "=== effective config: defaults and required fields ===\n";
    $eff = ai_effective_config('deepseek', '', '');
    TestAssert::true($eff['ok'], 'deepseek defaults valid');
    TestAssert::same('https://api.deepseek.com/chat/completions', $eff['endpoint']['url'], 'deepseek default URL');
    TestAssert::same('deepseek-v4-flash', $eff['model'], 'deepseek default model');
    $eff = ai_effective_config('claude', '', '');
    TestAssert::same('anthropic', $eff['endpoint']['protocol'], 'claude default uses anthropic');
    $eff = ai_effective_config('openai', '', 'gpt-4o');
    TestAssert::false($eff['ok'], 'openai requires URL');
    TestAssert::contains($eff['error'], 'API URL', 'openai URL message');
    $eff = ai_effective_config('openai', 'https://gw.example/v1', '');
    TestAssert::false($eff['ok'], 'openai requires model');
    TestAssert::contains($eff['error'], '模型', 'openai model message');
    TestAssert::true($eff['endpoint']['ok'], 'missing model still reports a resolved address');
    $eff = ai_effective_config('openai', 'https://gw.example/v1', 'gpt-4.1-mini');
    TestAssert::true($eff['ok'], 'complete openai config valid');
    $eff = ai_effective_config('nope', 'https://gw.example/v1', 'm');
    TestAssert::false($eff['ok'], 'unknown provider rejected');

    echo "=== same host comparison: host case-insensitive, effective port ===\n";
    $same = function ($a, $b) {
        return ai_endpoint_same_host(ai_resolve_endpoint($a), ai_resolve_endpoint($b));
    };
    TestAssert::true($same('https://API.deepseek.com/v1', 'https://api.deepseek.com/chat/completions'), 'host case ignored, path ignored');
    TestAssert::true($same('https://gw.example/v1', 'https://gw.example:443/other?x=1'), 'implicit 443 equals explicit 443');
    TestAssert::true($same('http://gw.example:8080/v1', 'https://gw.example:8080/v1'), 'protocol change with same explicit port allowed');
    TestAssert::false($same('https://gw.example/v1', 'https://evil.example/v1'), 'host change detected');
    TestAssert::false($same('https://gw.example/v1', 'https://a.gw.example/v1'), 'subdomain change detected');
    TestAssert::false($same('https://gw.example/v1', 'https://gw.example:8443/v1'), 'port change detected');
    TestAssert::false($same('https://gw.example/v1', 'http://gw.example/v1'), 'default port change detected');
    TestAssert::false($same('', 'https://gw.example/v1'), 'unresolvable saved address never matches');

    echo "=== input unescape reverses post()/storage escaping exactly once ===\n";
    $raw = 'https://gw.example/v1?a=1&b="2"&c=\'3\'';
    $escaped = escape_string($raw);
    TestAssert::same($raw, ai_input_unescape($escaped), 'escaped value restored');
    TestAssert::same('a=1&amp;b=2', ai_input_unescape('a=1&amp;amp;b=2'), 'only one level is restored');
    TestAssert::same('', ai_input_unescape(null), 'null becomes empty');
});
