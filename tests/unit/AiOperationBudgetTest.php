<?php

declare(strict_types=1);

namespace app\admin\controller\content {
    function microtime($get_as_float = false)
    {
        if (! isset($GLOBALS['ai_budget_clock'])) {
            return \microtime($get_as_float);
        }
        $now = (float) $GLOBALS['ai_budget_clock'];
        if ($get_as_float) {
            return $now;
        }
        $sec = (int) $now;
        $usec = (int) (($now - $sec) * 1000000);
        return sprintf('%06d %d', $usec, $sec);
    }

    function ini_get($varname)
    {
        if (isset($GLOBALS['ai_budget_ini']) && array_key_exists($varname, $GLOBALS['ai_budget_ini'])) {
            return $GLOBALS['ai_budget_ini'][$varname];
        }
        return \ini_get($varname);
    }

    function set_time_limit($seconds)
    {
        $seconds = (int) $seconds;
        $GLOBALS['ai_budget_time_limits'][] = $seconds;
        $result = isset($GLOBALS['ai_budget_time_limit_result']) ? $GLOBALS['ai_budget_time_limit_result'] : true;
        if ($result === 'once') {
            $GLOBALS['ai_budget_time_limit_result'] = false;
            return true;
        }
        return $result === true;
    }

    function check_dir($path, $create = false)
    {
        if (empty($GLOBALS['ai_budget_check_dir'])) {
            return false;
        }
        if (is_dir($path)) {
            return true;
        }
        if ($create) {
            return mkdir($path, 0777, true) || is_dir($path);
        }
        return false;
    }

    function curl_init($url = null)
    {
        $GLOBALS['ai_budget_curl_inits'] = (isset($GLOBALS['ai_budget_curl_inits']) ? $GLOBALS['ai_budget_curl_inits'] : 0) + 1;
        $handle = new \stdClass();
        $handle->opts = array();
        return $handle;
    }

    function curl_setopt($handle, $option, $value)
    {
        if (! isset($handle->opts) || ! is_array($handle->opts)) {
            $handle->opts = array();
        }
        $handle->opts[$option] = $value;
        return true;
    }

    function curl_exec($handle)
    {
        if (! isset($handle->opts) || ! is_array($handle->opts)) {
            $handle->opts = array();
        }
        $GLOBALS['ai_budget_exec_opts'][] = $handle->opts;
        if (isset($GLOBALS['ai_budget_exec_mode']) && $GLOBALS['ai_budget_exec_mode'] === 'success') {
            return '{"choices":[{"message":{"content":"<p>ok</p>"},"finish_reason":"stop"}]}';
        }
        $GLOBALS['ai_budget_errno'] = 28;
        $GLOBALS['ai_budget_error'] = 'Timeout was reached';
        return false;
    }

    function curl_errno($handle)
    {
        return isset($GLOBALS['ai_budget_errno']) ? $GLOBALS['ai_budget_errno'] : 0;
    }

    function curl_error($handle)
    {
        return isset($GLOBALS['ai_budget_error']) ? $GLOBALS['ai_budget_error'] : '';
    }

    function curl_getinfo($handle, $option = 0)
    {
        return 200;
    }
}

namespace {
    /**
     * @suite unit
     * @covers AiController 一次 AI 操作的共享截止时间与 PHP 执行预算
     *
     * 运行：
     *   php tests/run.php --suite=unit --filter=AiOperationBudget
     */
    if (! defined('TEST_ROOT')) {
        require dirname(__DIR__) . '/bootstrap.php';
    }

    if (! defined('CURLOPT_TIMEOUT') || ! defined('CURLOPT_CONNECTTIMEOUT')) {
        echo "SKIP: cURL constants unavailable\n";
        return 0;
    }

    require_once APP_PATH . '/admin/controller/content/AiController.php';

    $aiBudgetRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot-ai-budget-' . getmypid();
    if (! is_dir($aiBudgetRoot) && ! mkdir($aiBudgetRoot, 0777, true)) {
        echo "FAIL: cannot create temp log dir\n";
        return 1;
    }
    if (! defined('DOC_PATH')) {
        define('DOC_PATH', $aiBudgetRoot . DIRECTORY_SEPARATOR);
    }
    if (! defined('DATA_DIR')) {
        define('DATA_DIR', 'data');
    }

    function ai_budget_rrmdir($dir)
    {
        if (! is_string($dir) || $dir === '' || strpos($dir, 'pboot-ai-budget-') === false || ! is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if (! is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                ai_budget_rrmdir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    function ai_budget_log_file()
    {
        return DOC_PATH . DATA_DIR . '/log/ai/' . date('Y/Ym') . '/' . date('Ymd') . '.log';
    }

    function ai_budget_log_text()
    {
        $file = ai_budget_log_file();
        if (! is_file($file)) {
            return '';
        }
        $text = file_get_contents($file);
        return is_string($text) ? $text : '';
    }

    function ai_budget_clear_log()
    {
        $file = ai_budget_log_file();
        if (is_file($file)) {
            unlink($file);
        }
    }

    function ai_budget_error_lines($text)
    {
        $lines = preg_split('/\r\n|\n|\r/', (string) $text);
        $out = array();
        if (! is_array($lines)) {
            return $out;
        }
        foreach ($lines as $line) {
            if ($line !== '' && strpos($line, '[error]') !== false) {
                $out[] = $line;
            }
        }
        return $out;
    }

    function ai_budget_prepare($clock, $phpLimit, $disable, $extend)
    {
        $GLOBALS['ai_budget_clock'] = $clock;
        $GLOBALS['ai_budget_ini'] = array(
            'max_execution_time' => (string) $phpLimit,
            'disable_functions' => $disable,
        );
        $GLOBALS['ai_budget_time_limit_result'] = $extend;
        $GLOBALS['ai_budget_time_limits'] = array();
        $GLOBALS['ai_budget_curl_inits'] = 0;
        $GLOBALS['ai_budget_exec_mode'] = 'timeout';
        $GLOBALS['ai_budget_exec_opts'] = array();
        $GLOBALS['ai_budget_errno'] = 0;
        $GLOBALS['ai_budget_error'] = '';
        $GLOBALS['ai_budget_check_dir'] = true;
        $_SERVER['REQUEST_TIME_FLOAT'] = $clock;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['username'] = 'tester';

    return TestAssert::runSuite(function () use ($aiBudgetRoot) {
        $timeoutMsg = 'AI 请求超时，请缩短原文或改用非推理模型后重试';
        $ref = new ReflectionClass('app\\admin\\controller\\content\\AiController');
        $controller = $ref->newInstanceWithoutConstructor();
        $invoke = function ($name, array $args) use ($ref, $controller) {
            $method = $ref->getMethod($name);
            if (PHP_VERSION_ID < 80100) {
                $method->setAccessible(true);
            }
            return $method->invokeArgs($controller, $args);
        };
        $deadlineOf = function () use ($ref, $controller) {
            $prop = $ref->getProperty('aiDeadline');
            if (PHP_VERSION_ID < 80100) {
                $prop->setAccessible(true);
            }
            return $prop->getValue($controller);
        };
        $setTask = function ($task) use ($ref, $controller) {
            $prop = $ref->getProperty('aiTask');
            if (PHP_VERSION_ID < 80100) {
                $prop->setAccessible(true);
            }
            $prop->setValue($controller, $task);
        };
        $post = function () use ($invoke) {
            return $invoke('httpPostJson', array(
                'http://ai.invalid/v1/chat/completions',
                array('model' => 'budget-probe'),
                array('Content-Type: application/json', 'Authorization: Bearer sk-testsecret'),
            ));
        };
        $assertFailure = function ($result, $label) use ($timeoutMsg) {
            TestAssert::same(0, $result['code'], $label . ' returns business failure');
            TestAssert::same($timeoutMsg, $result['msg'], $label . ' keeps the timeout message');
            TestAssert::same(null, $result['data'], $label . ' keeps the failure payload');
        };
        $assertWait = function ($timeout, $connect, $label) {
            TestAssert::true($timeout >= 1 && $timeout <= 60, $label . ' transfer wait stays inside 1..60');
            TestAssert::true($connect >= 1 && $connect <= 5, $label . ' connect wait stays inside 1..5');
            TestAssert::true($connect <= $timeout, $label . ' connect wait does not exceed transfer wait');
        };
        $lastOpts = function () {
            $opts = $GLOBALS['ai_budget_exec_opts'];
            return $opts[count($opts) - 1];
        };

        try {
            ConfigStub::set(array('ai_provider' => 'deepseek'));

            echo "=== adjustable PHP limit lets curl finish inside the operation budget ===\n";
            ai_budget_prepare(1000, 30, '', true);
            ai_budget_clear_log();
            $invoke('beginAiCall', array('rewrite', 'continue'));
            $deadline = $deadlineOf();
            TestAssert::same(1075.0, $deadline, 'operation deadline is 75 seconds from the start');
            $invoke('rememberAiModel', array('deepseek-v4-flash', 'deepseek-v4-pro'));
            $result = $post();
            $assertFailure($result, 'hung upstream');
            $opts = $lastOpts();
            TestAssert::same(60, $opts[CURLOPT_TIMEOUT], 'adjusted request waits the 60 second cap');
            TestAssert::same(5, $opts[CURLOPT_CONNECTTIMEOUT], 'adjusted request keeps the 5 second connect cap');
            $assertWait($opts[CURLOPT_TIMEOUT], $opts[CURLOPT_CONNECTTIMEOUT], 'adjusted request');
            TestAssert::same(array(65), $GLOBALS['ai_budget_time_limits'], 'PHP limit is curl wait plus the 5 second tail');
            TestAssert::same($deadline, $deadlineOf(), 'raising the PHP limit does not move the operation deadline');
            $errors = ai_budget_error_lines(ai_budget_log_text());
            TestAssert::same(1, count($errors), 'one hung call writes one error');
            TestAssert::contains($errors[0], '[error]', 'timeout is an error record');
            TestAssert::contains($errors[0], 'provider=deepseek', 'error keeps provider');
            TestAssert::contains($errors[0], 'model=deepseek-v4-flash', 'error keeps the actual model');
            TestAssert::contains($errors[0], 'scene=rewrite', 'error keeps scene');
            TestAssert::contains($errors[0], 'task=continue', 'error keeps task');
            TestAssert::contains($errors[0], 'configured=deepseek-v4-pro', 'error keeps the configured model');
            TestAssert::contains($errors[0], $timeoutMsg, 'error keeps the timeout message');
            TestAssert::notContains($errors[0], 'sk-testsecret', 'error does not keep the API key');
            TestAssert::notContains(ai_budget_log_text(), '[truncated]', 'timeout is not recorded as truncated');
            TestAssert::notContains(ai_budget_log_text(), '生成正文', 'error does not keep submitted text');

            echo "=== failed time-limit adjustment shortens the wait ===\n";
            ai_budget_prepare(1000, 30, '', false);
            ai_budget_clear_log();
            $invoke('beginAiCall', array('rewrite', 'continue'));
            $result = $post();
            $assertFailure($result, 'unadjusted limit');
            $opts = $lastOpts();
            TestAssert::same(25, $opts[CURLOPT_TIMEOUT], 'failed adjustment keeps 30 minus the 5 second tail');
            TestAssert::same(5, $opts[CURLOPT_CONNECTTIMEOUT], 'shortened wait still uses the connect cap');
            TestAssert::same(array(65), $GLOBALS['ai_budget_time_limits'], 'failed adjustment was still attempted with a finite limit');
            TestAssert::same(1, count(ai_budget_error_lines(ai_budget_log_text())), 'shortened timeout writes one error');

            echo "=== disabled time-limit changes are not called ===\n";
            ai_budget_prepare(1000, 30, ' exec , Set_Time_Limit ', false);
            ai_budget_clear_log();
            $invoke('beginAiCall', array('generate'));
            $result = $post();
            $assertFailure($result, 'disabled limit');
            $opts = $lastOpts();
            TestAssert::same(25, $opts[CURLOPT_TIMEOUT], 'disabled adjustment uses the conservative remainder');
            TestAssert::same(5, $opts[CURLOPT_CONNECTTIMEOUT], 'disabled adjustment keeps a positive connect wait');
            TestAssert::same(array(), $GLOBALS['ai_budget_time_limits'], 'disabled set_time_limit is not called');

            echo "=== unlimited PHP execution still stops at the operation cap ===\n";
            ai_budget_prepare(1000, 0, '', true);
            $_SERVER['REQUEST_TIME_FLOAT'] = 1;
            $invoke('beginAiCall', array('tdk'));
            $result = $post();
            $assertFailure($result, 'unlimited PHP time');
            $opts = $lastOpts();
            TestAssert::same(60, $opts[CURLOPT_TIMEOUT], 'unlimited PHP time still caps one call at 60 seconds');
            TestAssert::same(5, $opts[CURLOPT_CONNECTTIMEOUT], 'unlimited PHP time keeps the connect cap');
            TestAssert::same(array(), $GLOBALS['ai_budget_time_limits'], 'unlimited PHP time is not changed');

            echo "=== a PHP limit that already covers the call is left alone ===\n";
            ai_budget_prepare(1000, 100, '', true);
            $invoke('beginAiCall', array('ping'));
            $post();
            $opts = $lastOpts();
            TestAssert::same(60, $opts[CURLOPT_TIMEOUT], 'a long PHP limit still caps curl at 60 seconds');
            TestAssert::same(array(), $GLOBALS['ai_budget_time_limits'], 'enough PHP time does not call set_time_limit');

            echo "=== remaining operation time can be shorter than 60 seconds ===\n";
            ai_budget_prepare(1000, 0, '', true);
            $invoke('beginAiCall', array('rewrite', 'polish'));
            $held = $deadlineOf();
            $GLOBALS['ai_budget_clock'] = 1040;
            $post();
            $opts = $lastOpts();
            TestAssert::same(30, $opts[CURLOPT_TIMEOUT], '35 seconds of operation time leave a 30 second transfer');
            TestAssert::same(5, $opts[CURLOPT_CONNECTTIMEOUT], 'a 30 second transfer keeps the connect cap');
            TestAssert::same($held, $deadlineOf(), 'a later wait calculation keeps the original deadline');

            echo "=== less than one safe second does not open a connection ===\n";
            ai_budget_prepare(1000, 0, '', true);
            ai_budget_clear_log();
            $invoke('beginAiCall', array('rewrite', 'shorten'));
            $GLOBALS['ai_budget_clock'] = 1069.2;
            $beforeInits = $GLOBALS['ai_budget_curl_inits'];
            $result = $post();
            $assertFailure($result, 'exhausted budget');
            TestAssert::same($beforeInits, $GLOBALS['ai_budget_curl_inits'], 'exhausted budget does not call curl_init');
            TestAssert::same(array(), $GLOBALS['ai_budget_exec_opts'], 'exhausted budget does not execute curl');
            TestAssert::same(array(), $GLOBALS['ai_budget_time_limits'], 'exhausted budget does not extend the PHP limit');
            $errors = ai_budget_error_lines(ai_budget_log_text());
            TestAssert::same(1, count($errors), 'a refused call writes one error');
            TestAssert::contains($errors[0], 'scene=rewrite', 'refused call keeps scene');
            TestAssert::contains($errors[0], 'task=shorten', 'refused call keeps task');

            echo "=== one remaining second is used and is not rounded up from a fraction ===\n";
            ai_budget_prepare(1000, 0, '', true);
            $invoke('beginAiCall', array('rewrite', 'rewrite'));
            $GLOBALS['ai_budget_clock'] = 1069;
            $post();
            $opts = $lastOpts();
            TestAssert::same(1, $opts[CURLOPT_TIMEOUT], 'one remaining second is still a real wait');
            TestAssert::same(1, $opts[CURLOPT_CONNECTTIMEOUT], 'connect wait shrinks with the transfer wait');
            $GLOBALS['ai_budget_clock'] = 1000;
            $invoke('beginAiCall', array('rewrite', 'rewrite'));
            $GLOBALS['ai_budget_clock'] = 1067;
            $inits = $GLOBALS['ai_budget_curl_inits'];
            $post();
            $opts = $lastOpts();
            TestAssert::same(3, $opts[CURLOPT_TIMEOUT], 'three remaining seconds stay three');
            TestAssert::same(3, $opts[CURLOPT_CONNECTTIMEOUT], 'connect wait matches a three second transfer');
            TestAssert::same($inits + 1, $GLOBALS['ai_budget_curl_inits'], 'a positive wait still connects');

            echo "=== a reset PHP timer does not grant a new operation deadline ===\n";
            ai_budget_prepare(1000, 30, '', 'once');
            $invoke('beginAiCall', array('alt', 'url'));
            $deadline = $deadlineOf();
            $invoke('rememberAiModel', array('qwen-vl-max', ''));
            $post();
            $first = $GLOBALS['ai_budget_exec_opts'][0];
            TestAssert::same(60, $first[CURLOPT_TIMEOUT], 'first phase uses the 60 second cap');
            TestAssert::same(array(65), $GLOBALS['ai_budget_time_limits'], 'first phase extends PHP time once');
            $GLOBALS['ai_budget_clock'] = 1058;
            $setTask('text');
            $invoke('rememberAiModel', array('qwen-plus-latest', ''));
            $post();
            $second = $GLOBALS['ai_budget_exec_opts'][1];
            TestAssert::same(2, $second[CURLOPT_TIMEOUT], 'second phase keeps the time left after the reset PHP timer');
            TestAssert::same(2, $second[CURLOPT_CONNECTTIMEOUT], 'second phase connect wait follows its short transfer');
            TestAssert::same(array(65, 17), $GLOBALS['ai_budget_time_limits'], 'second extension is finite and then rejected');
            TestAssert::same($deadline, $deadlineOf(), 'the second phase keeps the original deadline');
            TestAssert::same(2, $GLOBALS['ai_budget_curl_inits'], 'both phases connect while budget remains');

            echo "=== Alt vision failure and text fallback are separate log phases ===\n";
            ai_budget_prepare(2000, 0, '', true);
            ai_budget_clear_log();
            ConfigStub::set(array('ai_provider' => 'qwen'));
            $invoke('beginAiCall', array('alt', 'text'));
            $deadline = $deadlineOf();
            $setTask('url');
            $invoke('rememberAiModel', array('qwen-vl-max', ''));
            $post();
            $GLOBALS['ai_budget_clock'] = 2066;
            $setTask('text');
            $invoke('rememberAiModel', array('qwen-plus-latest', ''));
            $post();
            $opts = $lastOpts();
            TestAssert::same(4, $opts[CURLOPT_TIMEOUT], 'text fallback receives only the time vision did not use');
            TestAssert::same($deadline, $deadlineOf(), 'text fallback does not start a new deadline');
            $errors = ai_budget_error_lines(ai_budget_log_text());
            TestAssert::same(2, count($errors), 'vision and text failures are logged separately');
            TestAssert::contains($errors[0], 'model=qwen-vl-max', 'vision log uses the vision model');
            TestAssert::contains($errors[0], 'scene=alt', 'vision log uses the alt scene');
            TestAssert::contains($errors[0], 'task=url', 'vision log uses the url task');
            TestAssert::contains($errors[1], 'model=qwen-plus-latest', 'text log uses the text model');
            TestAssert::contains($errors[1], 'scene=alt', 'text log uses the alt scene');
            TestAssert::contains($errors[1], 'task=text', 'text log uses the text task');
            TestAssert::notContains($errors[0], 'configured=', 'unchanged model omits configured');
            TestAssert::notContains($errors[1], 'configured=', 'text fallback omits configured when unchanged');

            echo "=== text fallback does not connect after the operation budget is gone ===\n";
            ai_budget_prepare(3000, 0, '', true);
            ai_budget_clear_log();
            $invoke('beginAiCall', array('alt', 'base64'));
            $deadline = $deadlineOf();
            $invoke('rememberAiModel', array('qwen-vl-max', ''));
            $post();
            $GLOBALS['ai_budget_clock'] = 3071;
            $inits = $GLOBALS['ai_budget_curl_inits'];
            $setTask('text');
            $invoke('rememberAiModel', array('qwen-plus-latest', ''));
            $result = $post();
            $assertFailure($result, 'fallback without budget');
            TestAssert::same($inits, $GLOBALS['ai_budget_curl_inits'], 'fallback without budget does not connect');
            TestAssert::same($deadline, $deadlineOf(), 'a refused fallback keeps the original deadline');
            $errors = ai_budget_error_lines(ai_budget_log_text());
            TestAssert::same(2, count($errors), 'the refused fallback still has its own error');
            TestAssert::contains($errors[0], 'task=base64', 'vision phase keeps the base64 task');
            TestAssert::contains($errors[1], 'task=text', 'refused fallback keeps the text task');
            TestAssert::contains($errors[1], 'model=qwen-plus-latest', 'refused fallback keeps the text model');

            echo "=== fast responses keep the success payload ===\n";
            ai_budget_clear_log();
            $GLOBALS['ai_budget_exec_mode'] = 'success';
            foreach (array('generate', 'rewrite', 'tdk', 'alt', 'ping') as $scene) {
                ai_budget_prepare(4000, 0, '', true);
                $GLOBALS['ai_budget_exec_mode'] = 'success';
                $task = $scene === 'rewrite' ? 'continue' : '';
                $invoke('beginAiCall', array($scene, $task));
                $result = $post();
                TestAssert::same(1, $result['code'], $scene . ' success code stays 1');
                TestAssert::contains((string) $result['data'], '<p>ok</p>', $scene . ' success body is preserved');
                $opts = $lastOpts();
                TestAssert::same(60, $opts[CURLOPT_TIMEOUT], $scene . ' fast call still caps at 60 seconds');
                TestAssert::same(5, $opts[CURLOPT_CONNECTTIMEOUT], $scene . ' fast call keeps the connect cap');
            }
            TestAssert::same(array(), ai_budget_error_lines(ai_budget_log_text()), 'success does not write an error');

            echo "=== callAi records one timeout error and an unwritable log still returns JSON ===\n";
            ai_budget_prepare(5000, 0, '', true);
            ai_budget_clear_log();
            $GLOBALS['ai_budget_exec_mode'] = 'timeout';
            $invoke('beginAiCall', array('rewrite', 'continue'));
            $cfg = array(
                'provider' => 'qwen',
                'base' => 'http://ai.invalid/v1/chat/completions',
                'path' => '',
                'api_key' => 'sk-testsecret',
                'model' => 'qwen-plus-latest',
                'max_tokens' => 2048,
            );
            $result = $invoke('callAi', array(
                array(array('role' => 'user', 'content' => '生成正文')),
                null,
                null,
                $cfg,
            ));
            $assertFailure($result, 'callAi timeout');
            $errors = ai_budget_error_lines(ai_budget_log_text());
            TestAssert::same(1, count($errors), 'callAi timeout writes one error');
            TestAssert::contains($errors[0], 'model=qwen-plus-latest', 'callAi error uses the requested model');
            TestAssert::contains($errors[0], 'scene=rewrite', 'callAi error keeps scene');
            TestAssert::contains($errors[0], 'task=continue', 'callAi error keeps task');
            TestAssert::notContains($errors[0], '生成正文', 'callAi error does not keep the submitted text');
            TestAssert::notContains($errors[0], 'sk-testsecret', 'callAi error does not keep the API key');
            TestAssert::notContains(ai_budget_log_text(), '[truncated]', 'callAi timeout is not truncated');

            ai_budget_rrmdir(DOC_PATH . DATA_DIR);
            $GLOBALS['ai_budget_check_dir'] = false;
            $GLOBALS['ai_budget_curl_inits'] = 0;
            $result = $post();
            $assertFailure($result, 'unwritable log');
            TestAssert::false(is_file(ai_budget_log_file()), 'unwritable log directory creates no log file');
            TestAssert::same(1, $GLOBALS['ai_budget_curl_inits'], 'an unwritable log does not change the call');
        } finally {
            ai_budget_rrmdir($aiBudgetRoot);
        }
    });
}
