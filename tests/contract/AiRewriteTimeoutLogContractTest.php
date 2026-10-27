<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers AiController 续写超时、推理模型回退与日志字段
 *
 * 运行：
 *   php tests/run.php --suite=contract --filter=AiRewriteTimeoutLog
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

if (! defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . 'apps');
}

return TestAssert::runSuite(function () {
    echo "=== AiController readable ===\n";

    $src = file_get_contents(APP_PATH . '/admin/controller/content/AiController.php');
    TestAssert::false($src === false, 'AiController readable');

    echo "=== rewrite keeps one input limit and text tasks fall back from reasoning models ===\n";

    $rewritePos = strpos($src, 'public function rewrite()');
    $tdkPos = strpos($src, 'public function tdk()');
    TestAssert::true($rewritePos !== false && $tdkPos !== false && $tdkPos > $rewritePos, 'rewrite() before tdk()');
    $rewrite = substr($src, $rewritePos, $tdkPos - $rewritePos);
    TestAssert::contains($rewrite, "beginAiCall('rewrite', \$mode)", 'rewrite records scene and task');
    TestAssert::contains($rewrite, "safeText(post('text'), 8000)", 'rewrite keeps at most 8000 chars');
    TestAssert::notContains($src, 'function tailText', 'continue does not cut a separate tail');
    TestAssert::notContains($rewrite, 'mode=', 'rewrite log does not use mode=');

    $reasonPos = strpos($src, 'private function isReasoningModel(');
    $nextPos = strpos($src, 'private function shouldUseFlashForText(', $reasonPos);
    TestAssert::true($reasonPos !== false && $nextPos !== false, 'isReasoningModel() found');
    $reason = substr($src, $reasonPos, $nextPos - $reasonPos);
    TestAssert::contains($reason, "strpos(\$model, 'flash')", 'flash does not need a model fallback');
    TestAssert::contains($reason, 'deepseek-v\d.*pro', 'pro models are reasoning models');
    TestAssert::notContains($reason, 'deepseek-v\d/i', 'version match is not every deepseek-vN model');

    TestAssert::contains($src, "in_array(\$this->aiScene, array('generate', 'rewrite', 'tdk'), true)", 'flash fallback is limited to text tasks');
    TestAssert::contains($src, "\$cfg['model'] = 'deepseek-v4-flash';", 'reasoning text calls use flash');

    echo "=== one operation shares a deadline and each call waits at most 60 seconds ===\n";

    TestAssert::contains($src, 'const AI_OPERATION_BUDGET = 75;', 'operation budget stays under the 90 second page wait');
    TestAssert::contains($src, 'const AI_TAIL_MARGIN = 5;', 'tail margin is reserved for logging and the response');
    TestAssert::contains($src, 'const AI_HTTP_MAX = 60;', 'one upstream call waits at most 60 seconds');
    TestAssert::contains($src, 'const AI_CONNECT_MAX = 5;', 'connect wait stays at most 5 seconds');
    TestAssert::contains($src, '$this->aiDeadline = microtime(true) + self::AI_OPERATION_BUDGET;', 'deadline is fixed when the operation starts');
    TestAssert::contains($src, 'curl_setopt($ch, CURLOPT_TIMEOUT, $wait);', 'transfer wait is the computed budget');
    TestAssert::contains($src, 'curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect);', 'connect wait is the computed budget');
    TestAssert::contains($src, 'set_time_limit($seconds)', 'PHP time limit can be raised by a finite value');
    TestAssert::contains($src, "ini_get('max_execution_time')", 'remaining PHP budget is read from max_execution_time');
    TestAssert::notContains($src, 'set_time_limit(0)', 'time limit is never removed');
    TestAssert::notContains($src, 'curl_setopt($ch, CURLOPT_TIMEOUT, 0)', 'transfer wait is never unlimited');
    TestAssert::notContains($src, 'curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0)', 'connect wait is never unlimited');
    TestAssert::notContains($src, 'isWindowsRuntime', 'timeout does not branch on Windows');
    TestAssert::contains($src, '(int) $errno === 28', 'curl timeout has its own message');
    TestAssert::contains($src, 'AI 请求超时，请缩短原文或改用非推理模型后重试', 'timeout message is user facing');

    echo "=== empty length cutoff is one error and ping can still see it ===\n";

    TestAssert::contains($src, 'emptyByLengthMessage($cfg, \'finish_reason\', \'length\')', 'empty length body uses one message');
    TestAssert::contains($src, 'finish_reason=length', 'message keeps finish_reason=length for ping');
    $emptyFnPos = strpos($src, 'private function emptyByLengthMessage(');
    $emptyFnEnd = strpos($src, 'private function readLocalUploadImage(', $emptyFnPos);
    TestAssert::true($emptyFnPos !== false && $emptyFnEnd !== false, 'emptyByLengthMessage() found');
    $emptyFn = substr($src, $emptyFnPos, $emptyFnEnd - $emptyFnPos);
    TestAssert::contains($emptyFn, 'isReasoningModel($model)', 'flash advice only for reasoning models');
    TestAssert::contains($emptyFn, '请改用 deepseek-v4-flash，或增大「最大 Token」', 'reasoning model tells the user to switch');
    TestAssert::contains($emptyFn, '请增大「最大 Token」', 'other models only ask for more tokens');
    $truncPos = strpos($src, "aiWriteLog('truncated', 'finish_reason=length')");
    $emptyPos = strpos($src, 'emptyByLengthMessage($cfg, \'finish_reason\', \'length\')');
    TestAssert::true($emptyPos !== false && $truncPos !== false && $emptyPos < $truncPos, 'empty body is handled before the truncated log');

    echo "=== log line splits model and task ===\n";

    TestAssert::contains($src, "' model=' . \$this->aiLogToken(\$this->aiModel)", 'log has a model field');
    TestAssert::contains($src, "' scene=' . \$this->aiLogToken(\$this->aiScene)", 'log has a scene field');
    TestAssert::contains($src, "' task=' . \$this->aiLogToken(\$this->aiTask)", 'log has a task field');
    TestAssert::contains($src, "' configured=' . \$this->aiLogToken(\$this->aiConfigured)", 'fallback records the configured model');
    TestAssert::notContains($src, 'mode=', 'logs do not use mode=');
    TestAssert::notContains($src, 'error:openai', 'errors use a single error action');
});
