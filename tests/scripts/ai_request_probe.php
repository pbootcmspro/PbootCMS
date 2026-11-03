<?php

declare(strict_types=1);

/**
 * AiController 入口行为探测：在子进程里调用 ping/generate/alt 等真实入口，
 * 用 cURL 桩记录实际请求 URL、请求体与次数，不连接任何外部服务。
 *
 * 用法: php tests/scripts/ai_request_probe.php <scenario.json>
 *
 * scenario:
 *   action   ping|generate|rewrite|tdk|alt
 *   saved    已保存配置的原始填写值（按 post()/入库同样的方式转义后注入）
 *   saved_key 已保存 API Key 明文（加密后注入 ai_api_key）；saved_cipher 直接注入密文
 *   post     $_POST 原始值
 *   session  会话值（默认 id=1）
 *   replies  按顺序返回的上游响应：{status, body} 或 {errno, error}
 *   ini      可选，子进程 php -d 键值（如 max_execution_time、disable_functions）
 * 输出: {"response":..., "requests":[{url, payload, headers}], "log":"..."}
 */

namespace app\admin\controller\content {
    function curl_init()
    {
        $h = new \stdClass();
        $h->opts = array();
        return $h;
    }

    function curl_setopt($h, $option, $value)
    {
        $h->opts[$option] = $value;
        return true;
    }

    function curl_exec($h)
    {
        $GLOBALS['probe_requests'][] = array(
            'url' => isset($h->opts[CURLOPT_URL]) ? $h->opts[CURLOPT_URL] : '',
            'payload' => isset($h->opts[CURLOPT_POSTFIELDS]) ? json_decode($h->opts[CURLOPT_POSTFIELDS], true) : null,
            'headers' => isset($h->opts[CURLOPT_HTTPHEADER]) ? $h->opts[CURLOPT_HTTPHEADER] : array(),
        );
        $reply = array_shift($GLOBALS['probe_replies']);
        if (! is_array($reply)) {
            $reply = array('status' => 200, 'body' => json_encode(array(
                'choices' => array(array('message' => array('content' => '连接成功'), 'finish_reason' => 'stop'))
            )));
        }
        $GLOBALS['probe_current'] = $reply;
        if (isset($reply['errno'])) {
            return false;
        }
        return is_string($reply['body']) ? $reply['body'] : json_encode($reply['body'], JSON_UNESCAPED_UNICODE);
    }

    function curl_errno($h)
    {
        return isset($GLOBALS['probe_current']['errno']) ? (int) $GLOBALS['probe_current']['errno'] : 0;
    }

    function curl_error($h)
    {
        return isset($GLOBALS['probe_current']['error']) ? (string) $GLOBALS['probe_current']['error'] : '';
    }

    function curl_getinfo($h, $option = 0)
    {
        return isset($GLOBALS['probe_current']['status']) ? (int) $GLOBALS['probe_current']['status'] : 200;
    }

    function curl_safe_close($h)
    {
    }

    function check_dir($path, $create = false)
    {
        if (is_dir($path)) {
            return true;
        }
        return $create && (mkdir($path, 0777, true) || is_dir($path));
    }
}

namespace {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);

    $file = isset($argv[1]) ? (string) $argv[1] : '';
    $scenario = $file !== '' && is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (! is_array($scenario)) {
        fwrite(STDERR, "missing scenario\n");
        exit(2);
    }

    $root = realpath(__DIR__ . '/../..') . DIRECTORY_SEPARATOR;
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pboot-ai-probe-' . getmypid() . DIRECTORY_SEPARATOR;
    @mkdir($tmp . 'data', 0777, true);
    @mkdir($tmp . 'static/upload/image', 0777, true);
    file_put_contents($tmp . 'static/upload/image/probe.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

    define('ROOT_PATH', $root);
    define('APP_PATH', $root . 'apps');
    define('CORE_PATH', $root . 'core');
    define('DOC_PATH', $tmp);
    define('DATA_DIR', 'data');
    define('STATIC_DIR', 'static');

    require CORE_PATH . '/function/handle.php';
    require CORE_PATH . '/basic/Config.php';
    require CORE_PATH . '/function/helper.php';
    require CORE_PATH . '/basic/Controller.php';
    require APP_PATH . '/common/function.php';
    require APP_PATH . '/admin/controller/content/AiController.php';

    $configs = array('ai_rate_limit' => '100');
    $saved = isset($scenario['saved']) && is_array($scenario['saved']) ? $scenario['saved'] : array();
    foreach ($saved as $k => $v) {
        $configs[$k] = escape_string((string) $v);
    }
    if (isset($scenario['saved_key'])) {
        $configs['ai_api_key'] = aes_encrypt(escape_string((string) $scenario['saved_key']));
    }
    if (isset($scenario['saved_cipher'])) {
        $configs['ai_api_key'] = (string) $scenario['saved_cipher'];
    }
    $ref = new ReflectionClass(\core\basic\Config::class);
    $prop = $ref->getProperty('configs');
    if (PHP_VERSION_ID < 80100) {
        $prop->setAccessible(true);
    }
    $prop->setValue(null, $configs);

    session_id('aiprobe' . getmypid());
    @session_start();
    $_SESSION = array('id' => 1, 'username' => 'probe');
    if (isset($scenario['session']) && is_array($scenario['session'])) {
        $_SESSION = array_merge($_SESSION, $scenario['session']);
    }
    if (! empty($scenario['rate_exhausted'])) {
        $_SESSION['ai_call_log'] = array_fill(0, 100, time());
    }
    $_POST = isset($scenario['post']) && is_array($scenario['post']) ? $scenario['post'] : array();
    $GLOBALS['probe_requests'] = array();
    $GLOBALS['probe_replies'] = isset($scenario['replies']) && is_array($scenario['replies']) ? $scenario['replies'] : array();

    register_shutdown_function(function () use ($tmp) {
        $out = '';
        while (ob_get_level() > 0) {
            $out = ob_get_clean() . $out;
        }
        $log = '';
        $dir = $tmp . 'data/log/ai/' . date('Y/Ym');
        if (is_file($dir . '/' . date('Ymd') . '.log')) {
            $log = (string) file_get_contents($dir . '/' . date('Ymd') . '.log');
        }
        echo json_encode(array(
            'response' => json_decode($out, true),
            'raw' => $out,
            'requests' => $GLOBALS['probe_requests'],
            'log' => $log,
        ), JSON_UNESCAPED_UNICODE);
        $rm = function ($path) use (&$rm) {
            if (is_dir($path)) {
                foreach (scandir($path) as $item) {
                    if ($item !== '.' && $item !== '..') {
                        $rm($path . DIRECTORY_SEPARATOR . $item);
                    }
                }
                @rmdir($path);
            } elseif (is_file($path)) {
                @unlink($path);
            }
        };
        if (strpos($tmp, 'pboot-ai-probe-') !== false) {
            $rm(rtrim($tmp, DIRECTORY_SEPARATOR));
        }
        @session_destroy();
    });

    ob_start();
    $action = isset($scenario['action']) ? (string) $scenario['action'] : 'ping';
    $controller = (new ReflectionClass(\app\admin\controller\content\AiController::class))->newInstanceWithoutConstructor();
    $controller->$action();
}
