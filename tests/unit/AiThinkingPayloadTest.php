<?php

declare(strict_types=1);

namespace app\admin\controller\content {
    // 捕获真实调用链最终交给 cURL 的 JSON，不连接外部 AI 服务。
    function curl_init()
    {
        return new \stdClass();
    }

    function curl_setopt($handle, $option, $value)
    {
        if ($option === CURLOPT_POSTFIELDS) {
            $handle->payload = $value;
        }
        return true;
    }

    function curl_exec($handle)
    {
        $GLOBALS['ai_thinking_payload'] = json_decode($handle->payload, true);
        return json_encode(array(
            'choices' => array(array('message' => array('content' => '生成结果'), 'finish_reason' => 'stop')),
            'content' => array(array('type' => 'text', 'text' => '生成结果')),
            'stop_reason' => 'end_turn'
        ));
    }

    function curl_getinfo($handle, $option)
    {
        return 200;
    }
}

namespace {
    /**
     * @suite unit
     * @covers AiController DeepSeek 非推理请求与 Alt 输出额度
     */
    if (! defined('TEST_ROOT')) {
        require dirname(__DIR__) . '/bootstrap.php';
    }

    if (! defined('CURLOPT_POSTFIELDS')) {
        echo "SKIP: cURL constants unavailable\n";
        return 0;
    }

    require_once APP_PATH . '/admin/controller/content/AiController.php';

    return TestAssert::runSuite(function () {
        $ref = new ReflectionClass('app\\admin\\controller\\content\\AiController');
        $controller = $ref->newInstanceWithoutConstructor();
        $invoke = function ($name, array $args) use ($ref, $controller) {
            $method = $ref->getMethod($name);
            if (PHP_VERSION_ID < 80100) {
                $method->setAccessible(true);
            }
            return $method->invokeArgs($controller, $args);
        };
        $request = function ($scene, array $cfg, $maxTokens = null, $modelOverride = null) use ($invoke) {
            $invoke('beginAiCall', array($scene));
            $GLOBALS['ai_thinking_payload'] = array();
            $result = $invoke('callAi', array(
                array(array('role' => 'user', 'content' => '生成文本')),
                $maxTokens, $modelOverride, $cfg
            ));
            TestAssert::same(1, $result['code'], $scene . ' returns mock response');
            return $GLOBALS['ai_thinking_payload'];
        };
        $cfg = array(
            'provider' => 'deepseek',
            'base' => 'https://ai.invalid/v1/chat/completions',
            'path' => '',
            'api_key' => 'test-key',
            'model' => 'deepseek-v4-pro',
            'max_tokens' => 2048
        );

        echo "=== DeepSeek text requests disable thinking in both protocols ===\n";
        foreach (array('chat/completions', 'messages') as $endpoint) {
            $cfg['base'] = 'https://ai.invalid/v1/' . $endpoint;
            foreach (array('deepseek-v4-pro', 'deepseek-v4-flash') as $model) {
                $cfg['model'] = $model;
                foreach (array('generate', 'rewrite', 'tdk') as $scene) {
                    $payload = $request($scene, $cfg);
                    TestAssert::same('deepseek-v4-flash', $payload['model'], $scene . ' uses Flash');
                    TestAssert::same(array('type' => 'disabled'), isset($payload['thinking']) ? $payload['thinking'] : null, $scene . ' disables thinking');
                    TestAssert::same(2048, $payload['max_tokens'], $scene . ' keeps configured token limit');
                }
            }

            echo "=== DeepSeek Alt keeps Flash output allowance ===\n";
            foreach (array('deepseek-v4-flash', 'deepseek-v4-pro', 'deepseek-reasoner', 'deepseek-chat') as $model) {
                $cfg['model'] = $model;
                $alt = $invoke('resolveAltTextCall', array($cfg));
                $payload = $request('alt', $cfg, $alt['max_tokens'], $alt['model']);
                TestAssert::same($model === 'deepseek-chat' ? 'deepseek-chat' : 'deepseek-v4-flash', $payload['model'], $model . ' Alt model');
                TestAssert::same($model === 'deepseek-chat' ? 256 : 1024, $payload['max_tokens'], $model . ' Alt token limit');
                TestAssert::same(array('type' => 'disabled'), isset($payload['thinking']) ? $payload['thinking'] : null, $model . ' Alt disables thinking');
            }

            echo "=== ping keeps configured model and thinking defaults ===\n";
            $cfg['model'] = 'deepseek-v4-pro';
            $payload = $request('ping', $cfg, 256);
            TestAssert::same('deepseek-v4-pro', $payload['model'], 'ping keeps configured Pro model');
            TestAssert::false(isset($payload['thinking']), 'ping does not override thinking');
        }

        echo "=== other providers do not receive DeepSeek thinking parameters ===\n";
        foreach (array('qwen' => 'qwen-plus-latest', 'claude' => 'claude-sonnet-4-20250514') as $provider => $model) {
            $cfg['provider'] = $provider;
            $cfg['model'] = $model;
            $cfg['base'] = 'https://ai.invalid/v1/' . ($provider === 'claude' ? 'messages' : 'chat/completions');
            foreach (array('generate', 'rewrite', 'tdk', 'alt', 'ping') as $scene) {
                $payload = $request($scene, $cfg);
                TestAssert::same($model, $payload['model'], $provider . ' keeps configured model');
                TestAssert::false(isset($payload['thinking']), $provider . ' does not receive thinking parameter');
            }
            $alt = $invoke('resolveAltTextCall', array($cfg));
            TestAssert::same(256, $alt['max_tokens'], $provider . ' keeps Alt token limit');
        }
        unset($GLOBALS['ai_thinking_payload']);
    });
}
