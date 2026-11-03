<?php
/**
 * @copyright (C)2016-2099 Hnaoyun Inc.
 * @author PbootCMS AI Module
 * @date 2026年
 *  AI内容赋能控制器（写作助手 / TDK / 图片Alt 生成）
 */
namespace app\admin\controller\content;

use core\basic\Controller;

class AiController extends Controller
{

    const AI_OPERATION_BUDGET = 75;

    const AI_TAIL_MARGIN = 5;

    const AI_HTTP_MAX = 60;

    const AI_CONNECT_MAX = 5;

    private $aiScene = '';

    private $aiTask = '';

    private $aiModel = '';

    private $aiConfigured = '';

    private $aiDeadline = 0;

    private $aiPhpBudgetUntil = 0;

    private $aiProvider = '';

    private $aiSecrets = array();

    private $aiHttpSent = false;

    /**
     * 健康检查（POST），配置页「测试连接」；需系统配置权限 + 限流 + formcheck，不要求已启用、不保存草稿
     */
    public function ping()
    {
        $this->guardConfigAccess();
        $this->consumeRateLimit();
        $cipher = $this->config('ai_api_key');
        if (! extension_loaded('openssl') || ! function_exists('openssl_decrypt')) {
            $this->jsonError('服务端 OpenSSL 扩展不可用，无法读取 API Key');
        }
        $this->beginAiCall('ping');
        $saved = $this->getProviderConfig();
        $draft = $this->readPingDraft();
        $cfg = $this->mergeAiDraft($saved, $draft);
        $this->setAiConfigContext($cfg);
        $this->rememberAiModel($cfg['model']);
        if (! $cfg['ok']) {
            $this->pingFail('配置无效：' . $cfg['error'], $cfg, false);
        }
        if ($draft['api_key'] === '') {
            if (! $cfg['api_key']) {
                if (is_string($cipher) && $cipher !== '') {
                    $this->pingFail('API Key 解密失败，请重新保存 API Key', $cfg, false);
                }
                $this->pingFail('未配置 API Key', $cfg, false);
            }
            $savedEndpoint = ai_provider_endpoint($saved['provider'], $saved['raw_base']);
            if (! ai_endpoint_same_host($savedEndpoint, $cfg['endpoint'])) {
                $this->pingFail('API URL 的主机或端口与已保存配置不同，请重新输入完整 API Key 后再测试', $cfg, false);
            }
        }
        $rs = $this->callAi(array(
            array('role' => 'user', 'content' => '你好，请回复"连接成功"。'),
        ), 256, null, $cfg);
        $requested = $this->aiHttpSent;
        if ($rs['code'] !== 1) {
            if (isset($rs['reason']) && $rs['reason'] === 'length') {
                $this->aiWriteLog('ping', 'truncated');
                $this->jsonOk(array_merge($this->pingSummary($cfg, $requested), array(
                    'reply' => 'API 连接正常（响应被截断；测试连接使用固定输出上限，不受「最大Token」影响）'
                )));
            }
            $msg = $rs['msg'];
            if (! $requested && strpos($msg, '未发出请求') === false) {
                $msg .= '（未发出请求）';
            }
            $this->jsonError($msg, $this->pingSummary($cfg, $requested));
        }
        $this->aiWriteLog('ping');
        $this->jsonOk(array_merge($this->pingSummary($cfg, $requested), array('reply' => $this->aiRedact($rs['data']))));
    }

    /**
     * 生成完整文章
     * 入参：title (POST)
     * 返回：{content}
     */
    public function generate()
    {
        $this->guard();
        $title = $this->safeText(post('title'), 200);
        if (! $title) {
            $this->jsonError('标题不能为空');
        }
        $this->beginAiCall('generate');
        $messages = array(
            array('role' => 'system', 'content' => '你是一名专业中文 SEO 文章写作助手，擅长撰写结构清晰、信息丰富的企业网站文章。请使用规范的 HTML 段落标签 <p> 输出内容，不要使用 Markdown，也不要包含 <html>、<body> 等结构标签。'),
            array('role' => 'user', 'content' => '请围绕标题《' . $title . '》撰写一篇 600~1000 字的中文文章，要求：1) 结构清晰，分 3~5 段；2) 第一段为引言；3) 每段使用 <p> 标签包裹；4) 不要输出标题本身；5) 内容真实、可读性强。'),
        );
        $rs = $this->callAi($messages);
        if ($rs['code'] !== 1) {
            $this->jsonError($rs['msg']);
        }
        $html = $this->sanitizeHtml($rs['data']);
        if ($html === '') {
            $this->jsonError('AI 返回的文章内容为空');
        }
        $this->aiWriteLog('generate', 'title=' . $title);
        $this->jsonOk(array('content' => $html));
    }

    /**
     * 续写 / 改写 / 缩写 / 润色
     * 入参：text, mode in [continue|rewrite|shorten|polish]
     */
    public function rewrite()
    {
        $this->guard();
        $text = $this->safeText(post('text'), 8000);
        $mode = post('mode', 'var');
        if (! $text) {
            $this->jsonError('请提供需要处理的文本');
        }
        $allow = array('continue', 'rewrite', 'shorten', 'polish');
        if (! in_array($mode, $allow, true)) {
            $this->jsonError('不支持的处理方式');
        }
        $this->beginAiCall('rewrite', $mode);
        $promptMap = array(
            'continue' => '请在保持原文风格、上下文连贯的前提下，对以下文本进行续写，新增 200~400 字内容，仅返回续写部分，使用 <p> 段落包裹：',
            'rewrite' => '请保留原意的前提下，对以下文本进行重新组织和改写，使其表达更加流畅自然，仅返回改写后的全文，使用 <p> 段落包裹：',
            'shorten' => '请将以下文本进行压缩，保留核心信息，输出长度约为原文的 40%，仅返回缩写后的全文，使用 <p> 段落包裹：',
            'polish' => '请对以下文本进行语法修正、措辞优化和表达润色，保持原意不变，仅返回润色后的全文，使用 <p> 段落包裹：',
        );
        $messages = array(
            array('role' => 'system', 'content' => '你是一名专业中文写作编辑。请使用 HTML <p> 标签输出内容，不要使用 Markdown。'),
            array('role' => 'user', 'content' => $promptMap[$mode] . "\n\n" . $text),
        );
        $rs = $this->callAi($messages);
        if ($rs['code'] !== 1) {
            $this->jsonError($rs['msg']);
        }
        $html = $this->sanitizeHtml($rs['data']);
        if ($html === '') {
            $this->jsonError('AI 返回的处理结果为空');
        }
        $this->aiWriteLog('rewrite');
        $this->jsonOk(array('content' => $html));
    }

    /**
     * 一键 TDK：基于标题+正文生成 Title / Description / Keywords
     * 入参：title, content
     */
    public function tdk()
    {
        $this->guard();
        $title = $this->safeText(post('title'), 200);
        $content = $this->safeText(strip_tags(post('content')), 4000);
        if (! $title && ! $content) {
            $this->jsonError('请提供文章标题或正文');
        }
        $this->beginAiCall('tdk');
        $messages = array(
            array('role' => 'system', 'content' => '你是一名资深 SEO 顾问，擅长根据中文文章生成符合搜索引擎收录与用户点击习惯的 Title / Description / Keywords。'),
            array('role' => 'user', 'content' => '请根据以下文章信息生成 SEO 三要素，严格以 JSON 形式返回（只输出 JSON，不要任何解释、不要包裹 markdown）：\n标题：' . $title . "\n正文：" . $content . "\n\n输出 JSON 字段说明：\n- title：不超过 30 个汉字；\n- description：60~120 个汉字；\n- keywords：3~8 个，使用英文逗号分隔。"),
        );
        $rs = $this->callAi($messages);
        if ($rs['code'] !== 1) {
            $this->jsonError($rs['msg']);
        }
        $data = $this->parseJsonReply($rs['data']);
        if (! $data) {
            $this->jsonError('AI 返回内容解析失败');
        }
        $titleRaw = trim(strip_tags((string) ($data['title'] ?? '')));
        $descRaw = trim(strip_tags((string) ($data['description'] ?? '')));
        $keywordsRaw = trim(strip_tags((string) ($data['keywords'] ?? '')));
        if ($titleRaw === '' && $descRaw === '' && $keywordsRaw === '') {
            $this->jsonError('AI 返回的 TDK 内容无效');
        }
        $tdk = array(
            'title' => $this->safePlain($titleRaw, 80),
            'description' => $this->safePlain($descRaw, 240),
            'keywords' => $this->safePlain($keywordsRaw, 200),
        );
        $this->aiWriteLog('tdk');
        $this->jsonOk($tdk);
    }

    /**
     * 图片 Alt 生成（视觉识别 + 文章标题/正文，本地图 base64 / 外链 URL 直传视觉 API）
     * 入参：image_src, title, content
     */
    public function alt()
    {
        $this->guardEnabled();
        $title = $this->safeText(post('title'), 200);
        $content = $this->safeText(strip_tags(post('content')), 800);
        $imageSrc = post('image_src');
        if (!$imageSrc || (! $title && ! $content)) {
            $this->jsonError('请上传图片，并填写文章标题或正文');
        }
        $systemPrompt = '你是一名 SEO 与无障碍专家。请根据图片实际内容与文章主题生成中文 alt 描述。'
            . '要求：10~20 个汉字；描述画面主体及其与文章的关系；'
            . '禁止出现：截图、示意图、配图、图片、照片、如上所示；禁止重复文章标题；'
            . '仅输出描述本身，不要引号、不要前缀。';
        $textPrompt = '文章标题：' . $title . "\n"
            . ($content ? '正文摘要：' . $content . "\n" : '')
            . '请为文内配图生成一条 alt 描述。';
        $this->beginAiCall('alt', 'text');
        $cfg = $this->getProviderConfig();
        $this->setAiConfigContext($cfg);
        $img = $this->resolveAltImageForVision($imageSrc);
        $rs = null;
        $altVisionMaxTokens = 1024;
        $altTextCall = $this->resolveAltTextCall($cfg);
        $visionSupported = $cfg['ok'] && $this->providerSupportsVisionApi($cfg);
        if ($img && $visionSupported) {
            $this->aiTask = $img['kind'];
            $messages = $this->buildVisionMessages($systemPrompt, $textPrompt, $img, $cfg);
            $this->consumeRateLimit();
            $rs = $this->callAiVision($messages, $altVisionMaxTokens);
        }
        if (! $rs || $rs['code'] !== 1 || trim((string) ($rs['data'] ?? '')) === '') {
            $this->aiTask = 'text';
            $messages = array(
                array('role' => 'system', 'content' => $systemPrompt),
                array('role' => 'user', 'content' => $textPrompt),
            );
            $this->consumeRateLimit();
            $rs = $this->callAi($messages, $altTextCall['max_tokens'], $altTextCall['model']);
        }
        if ($rs['code'] !== 1) {
            $this->jsonError($rs['msg']);
        }
        $altRaw = trim((string) $rs['data'], " \t\n\r\0\x0B\"'");
        if ($altRaw === '') {
            $this->jsonError('AI 未能生成有效的 Alt 描述');
        }
        $alt = $this->safeText($altRaw, 60);
        $this->aiWriteLog('alt', 'title=' . mb_substr($title, 0, 30));
        $this->jsonOk(array('alt' => $alt));
    }

    // -------------------------- 私有方法 --------------------------

    /**
     * 执行入口安全卫士：启用检查 + 限流
     */
    private function guard()
    {
        $this->guardEnabled();
        $this->consumeRateLimit();
    }

    /**
     * 仅超级管理员或具备「配置参数」权限的用户
     */
    private function guardConfigAccess()
    {
        if ($this->hasLevelPath('/admin/Config/index')) {
            return;
        }
        $this->jsonError('您的账号权限不足');
    }

    /**
     * 是否拥有指定菜单权限（超级管理员 id=1 始终通过）
     */
    private function hasLevelPath($path)
    {
        if (session('id') == 1) {
            return true;
        }
        $levels = session('levels');
        return is_array($levels) && in_array($path, $levels, true);
    }

    /**
     * 是否已启用 AI 功能（不限流）
     */
    private function guardEnabled()
    {
        if (! $this->isEnabled()) {
            $this->jsonError('AI 功能未启用');
        }
    }

    /**
     * 消耗一次 AI 调用限流配额
     */
    private function consumeRateLimit()
    {
        if (! $this->checkRateLimit()) {
            $this->jsonError('调用过于频繁，请稍后重试');
        }
    }

    /**
     * 是否已启用 AI 功能
     */
    private function isEnabled()
    {
        return $this->config('ai_enabled') == '1';
    }

    /**
     * 简单的会话级令牌桶限流（每60秒N次）
     */
    private function checkRateLimit()
    {
        $limit = (int) $this->config('ai_rate_limit');
        if ($limit <= 0) {
            $limit = 20;
        }
        $now = time();
        $log = session('ai_call_log');
        if (! is_array($log)) {
            $log = array();
        }
        // 仅保留 60s 内
        $log = array_values(array_filter($log, function ($t) use ($now) {
            return ($now - $t) < 60;
        }));
        if (count($log) >= $limit) {
            session('ai_call_log', $log);
            return false;
        }
        $log[] = $now;
        session('ai_call_log', $log);
        return true;
    }

    /**
     * 读取当前服务商配置（含解密后的明文 Key）
     */
    private function getProviderConfig()
    {
        $providerKey = (string) $this->config('ai_provider');
        $defs = ai_provider_defs();
        if (! isset($defs[$providerKey])) {
            $providerKey = 'deepseek';
        }
        $maxTokens = (int) $this->config('ai_max_tokens');
        return $this->buildAiConfig(
            $providerKey,
            ai_input_unescape($this->config('ai_api_base')),
            ai_input_unescape($this->config('ai_model')),
            aes_decrypt($this->config('ai_api_key')),
            $maxTokens > 0 ? $maxTokens : 2048
        );
    }

    /**
     * 由服务商与未补全的 URL/模型（已还原转义）构建有效配置
     */
    private function buildAiConfig($provider, $rawBase, $rawModel, $apiKey, $maxTokens)
    {
        $eff = ai_effective_config($provider, $rawBase, $rawModel);
        return array(
            'ok' => $eff['ok'],
            'error' => $eff['error'],
            'provider' => (string) $provider,
            'raw_base' => (string) $rawBase,
            'raw_model' => (string) $rawModel,
            'base' => $eff['base'],
            'model' => $eff['model'],
            'endpoint' => $eff['endpoint'],
            'api_key' => (string) $apiKey,
            'max_tokens' => (int) $maxTokens,
        );
    }

    /**
     * 补齐未经 buildAiConfig 的配置（如直接传入的 base/model），保证都有解析后的端点
     */
    private function resolveAiConfig(array $cfg)
    {
        if (isset($cfg['endpoint']) && isset($cfg['ok'])) {
            return $cfg;
        }
        $built = $this->buildAiConfig(
            isset($cfg['provider']) ? $cfg['provider'] : '',
            isset($cfg['base']) ? $cfg['base'] : '',
            isset($cfg['model']) ? $cfg['model'] : '',
            isset($cfg['api_key']) ? $cfg['api_key'] : '',
            isset($cfg['max_tokens']) ? $cfg['max_tokens'] : 2048
        );
        return array_merge($cfg, $built);
    }

    /**
     * 协议由统一端点解析给出：Anthropic Messages → anthropic，其余 → openai_compat
     */
    private function resolveProtocol(array $cfg)
    {
        $cfg = $this->resolveAiConfig($cfg);
        return $cfg['endpoint']['protocol'];
    }

    /**
     * 按协议路由至 OpenAI 兼容或 Anthropic Messages 实现
     */
    private function invokeProvider(array $cfg, array $messages, $maxTokens)
    {
        if ($cfg['endpoint']['protocol'] === 'anthropic') {
            return $this->callClaude($cfg, $messages, $maxTokens);
        }
        return $this->callOpenAiCompat($cfg, $messages, $maxTokens);
    }

    /**
     * 本次请求上下文：日志 provider 与脱敏使用本次有效配置
     */
    private function setAiConfigContext(array $cfg)
    {
        $defs = ai_provider_defs();
        if (isset($cfg['provider']) && isset($defs[$cfg['provider']])) {
            $this->aiProvider = $cfg['provider'];
        }
        if (! empty($cfg['api_key'])) {
            $this->aiRememberSecret((string) $cfg['api_key'], true);
            $encoded = rawurlencode((string) $cfg['api_key']);
            if ($encoded !== (string) $cfg['api_key']) {
                $this->aiRememberSecret($encoded, true);
            }
        }
        $url = (isset($cfg['endpoint']['url']) && $cfg['endpoint']['url'] !== '') ? $cfg['endpoint']['url'] : (isset($cfg['base']) ? (string) $cfg['base'] : '');
        $parts = $url !== '' ? parse_url($url) : false;
        if (is_array($parts)) {
            foreach (array('user', 'pass') as $k) {
                if (isset($parts[$k]) && $parts[$k] !== '') {
                    $this->aiRememberSecret($parts[$k], true);
                }
            }
            if (isset($parts['fragment']) && $parts['fragment'] !== '') {
                $this->aiRememberSecret($parts['fragment']);
            }
            if (isset($parts['query']) && $parts['query'] !== '') {
                $this->aiRememberSecret($parts['query']);
                foreach (explode('&', $parts['query']) as $pair) {
                    $pos = strpos($pair, '=');
                    $name = $pos === false ? $pair : substr($pair, 0, $pos);
                    $value = $pos === false ? '' : substr($pair, $pos + 1);
                    if ($value === '') {
                        continue;
                    }
                    $decodedName = rawurldecode(urldecode($name));
                    $sensitive = (bool) preg_match('/(^|[_\-])(key|apikey|api_key|token|access_token|secret|password|passwd|pwd|pass|auth|sig|signature|credential)$/i', $decodedName);
                    if ($sensitive || strlen($value) >= 8) {
                        $this->aiRememberSecret($value, $sensitive);
                    }
                }
            }
        }
        $this->aiSecrets = array_values(array_unique(array_filter($this->aiSecrets, function ($s) {
            return is_string($s) && $s !== '';
        })));
        usort($this->aiSecrets, function ($a, $b) {
            return strlen($b) - strlen($a);
        });
    }

    /**
     * 登记敏感串及其 URL / JSON 转义变体；敏感项忽略长度下限
     */
    private function aiRememberSecret($secret, $force = false)
    {
        $secret = (string) $secret;
        if ($secret === '') {
            return;
        }
        $variants = array($secret);
        $decoded = rawurldecode($secret);
        if ($decoded !== $secret) {
            $variants[] = $decoded;
        }
        $decoded2 = urldecode($secret);
        if ($decoded2 !== $secret && $decoded2 !== $decoded) {
            $variants[] = $decoded2;
        }
        foreach ($variants as $v) {
            if ($v === '') {
                continue;
            }
            if (! $force && strlen($v) < 4) {
                continue;
            }
            $this->aiSecrets[] = $v;
            $json = json_encode($v);
            if (is_string($json) && strlen($json) >= 2) {
                $escaped = substr($json, 1, -1);
                if ($escaped !== $v) {
                    $this->aiSecrets[] = $escaped;
                }
            }
        }
    }

    /**
     * 从上游错误体提取可读文本：JSON 先解码再拼接标量，非 JSON 原样返回
     */
    private function aiErrorText($raw)
    {
        if (is_array($raw)) {
            $data = $raw;
        } elseif (is_string($raw)) {
            if (trim($raw) === '') {
                return '';
            }
            $data = json_decode($raw, true);
            if (is_string($data)) {
                return $data;
            }
            if (! is_array($data)) {
                return $raw;
            }
        } else {
            return (string) $raw;
        }
        $parts = array();
        array_walk_recursive($data, function ($v) use (&$parts) {
            if (is_string($v) || is_int($v) || is_float($v)) {
                $parts[] = (string) $v;
            }
        });
        return implode(' ', $parts);
    }

    /**
     * 提示与日志脱敏：当前 Key、URL 中的 userinfo / 查询串 / fragment
     */
    private function aiRedact($text)
    {
        $text = (string) $text;
        foreach ($this->aiSecrets as $secret) {
            if (strlen($secret) >= 8) {
                $text = str_replace($secret, '***', $text);
            } else {
                $text = preg_replace('/(?<![A-Za-z0-9])' . preg_quote($secret, '/') . '(?![A-Za-z0-9])/', '***', $text);
            }
        }
        $text = preg_replace('/sk-[a-zA-Z0-9_\-]+/i', 'sk-***', $text);
        $text = preg_replace('/\b((?:api[_-]?key|access[_-]?token|token|secret|password|passwd|pwd|signature|sig)\s*["\']?\s*[:=]\s*["\']?)[^\s"\'&;,]+/i', '$1***', $text);
        $text = preg_replace('/\bBearer\s+[^\s"\']+/i', 'Bearer ***', $text);
        return preg_replace('#(https?://)[^/\s@"\'<>]+@#i', '$1***@', $text);
    }

    /**
     * 统一 AI 调用入口
     * @param array $messages OpenAI 风格 messages
     * @param int|null $maxTokens 单次最大 token
     * @param string|null $modelOverride 临时覆盖模型
     * @param array|null $cfg 已解析配置（如 ping 草稿）；null 则读已保存配置
     * @return array {code:0/1, msg, data}
     */
    private function callAi(array $messages, $maxTokens = null, $modelOverride = null, $cfg = null)
    {
        if ($cfg === null) {
            $cfg = $this->getProviderConfig();
        }
        $cfg = $this->resolveAiConfig($cfg);
        $this->setAiConfigContext($cfg);
        $configuredModel = $cfg['model'];
        if (! $cfg['ok']) {
            return $this->invalidConfigResult($cfg);
        }
        if (! $cfg['api_key']) {
            return $this->errResult('AI API Key 未配置');
        }
        if ($modelOverride !== null && $modelOverride !== '') {
            $cfg['model'] = $modelOverride;
        }
        if ($this->shouldUseFlashForText($cfg)) {
            $cfg['model'] = 'deepseek-v4-flash';
        }
        $this->rememberAiModel($cfg['model'], $configuredModel);
        if ($maxTokens === null) {
            $maxTokens = $cfg['max_tokens'];
        }
        try {
            return $this->invokeProvider($cfg, $messages, $maxTokens);
        } catch (\Throwable $e) {
            $this->aiWriteLog('error', $e->getMessage());
            $this->log('[AI] 调用异常：' . $this->aiRedact($e->getMessage()), 'error');
            return $this->errResult('AI 服务调用异常');
        }
    }

    private function invalidConfigResult(array $cfg)
    {
        $this->rememberAiModel($cfg['model']);
        $msg = 'AI 配置无效：' . $cfg['error'] . '（未发出请求）';
        $this->aiWriteLog('error', $msg);
        return $this->errResult($msg);
    }

    /**
     * 测试连接草稿：读取页面当前填写值并还原转义；Key 为空或脱敏占位视为沿用已保存 Key
     */
    private function readPingDraft()
    {
        $draft = array(
            'provider' => null,
            'base' => null,
            'model' => null,
            'api_key' => '',
        );
        if (isset($_POST['ai_provider'])) {
            $draft['provider'] = trim((string) post('ai_provider'));
        }
        if (isset($_POST['ai_api_base'])) {
            $draft['base'] = ai_input_unescape(post('ai_api_base'));
        }
        if (isset($_POST['ai_model'])) {
            $draft['model'] = ai_input_unescape(post('ai_model'));
        }
        if (isset($_POST['ai_api_key'])) {
            $key = (string) post('ai_api_key');
            if ($key !== '' && ! is_masked_secret($key)) {
                $draft['api_key'] = $key;
            }
        }
        return $draft;
    }

    /**
     * 草稿覆盖已保存配置后再统一校验（不落库）；旧配置不完整不影响草稿修复
     */
    private function mergeAiDraft(array $saved, array $draft)
    {
        $provider = ($draft['provider'] !== null && $draft['provider'] !== '') ? $draft['provider'] : $saved['provider'];
        $base = $draft['base'] !== null ? $draft['base'] : $saved['raw_base'];
        $model = $draft['model'] !== null ? $draft['model'] : $saved['raw_model'];
        $apiKey = $draft['api_key'] !== '' ? $draft['api_key'] : $saved['api_key'];
        return $this->buildAiConfig($provider, $base, $model, $apiKey, $saved['max_tokens']);
    }

    /**
     * 测试连接结果的安全摘要：本次服务商、请求模型、脱敏地址，及是否已发出请求
     */
    private function pingSummary(array $cfg, $requested)
    {
        $defs = ai_provider_defs();
        $provider = isset($defs[$cfg['provider']]) ? $cfg['provider'] : '';
        $endpoint = '地址无效';
        if (! empty($cfg['endpoint']['ok'])) {
            $endpoint = $cfg['endpoint']['summary'];
        } elseif ($cfg['base'] === '') {
            $endpoint = '未填写';
        }
        return array(
            'provider' => $provider,
            'provider_name' => $provider !== '' ? $defs[$provider]['name'] : '不支持的服务商',
            'model' => $this->aiModel !== '' ? $this->aiModel : (string) $cfg['model'],
            'endpoint' => $this->aiRedact($endpoint),
            'requested' => (bool) $requested,
        );
    }

    private function pingFail($msg, array $cfg, $requested)
    {
        $msg = $requested ? $msg : $msg . '（未发出请求）';
        $this->aiWriteLog('error', $msg);
        $this->jsonError($this->aiRedact($msg), $this->pingSummary($cfg, $requested));
    }

    /**
     * 视觉模型调用（复用 ai_api_base / ai_api_key，仅切换 vision 模型）
     */
    private function callAiVision(array $messages, $maxTokens = 64)
    {
        $cfg = $this->getProviderConfig();
        $this->setAiConfigContext($cfg);
        if (! $cfg['ok']) {
            return $this->invalidConfigResult($cfg);
        }
        if (! $cfg['api_key']) {
            return $this->errResult('AI API Key 未配置');
        }
        $cfg['model'] = $this->resolveVisionModel($cfg);
        $this->rememberAiModel($cfg['model']);
        try {
            return $this->invokeProvider($cfg, $messages, $maxTokens);
        } catch (\Throwable $e) {
            $this->aiWriteLog('error', 'vision ' . $e->getMessage());
            $this->log('[AI] 视觉调用异常：' . $e->getMessage(), 'error');
            return $this->errResult('AI 视觉服务调用异常');
        }
    }

    /**
     * 解析 Alt 用视觉输入：本站 upload 读盘 base64，公网图 URL 直传
     */
    private function resolveAltImageForVision($imageSrc)
    {
        $local = $this->readLocalUploadImage($imageSrc);
        if ($local) {
            return $local;
        }
        $imageSrc = is_string($imageSrc) ? trim($imageSrc) : '';
        if ($this->isPublicRemoteImageUrl($imageSrc)) {
            return array('kind' => 'url', 'url' => $imageSrc);
        }
        return null;
    }

    /**
     * 构建多模态 messages（按 API 协议：Anthropic Messages / OpenAI 兼容）
     */
    private function buildVisionMessages($systemPrompt, $textPrompt, array $img, array $cfg)
    {
        if ($this->resolveProtocol($cfg) === 'anthropic') {
            if ($img['kind'] === 'url') {
                $imageBlock = array(
                    'type' => 'image',
                    'source' => array('type' => 'url', 'url' => $img['url']),
                );
            } else {
                $imageBlock = array(
                    'type' => 'image',
                    'source' => array(
                        'type' => 'base64',
                        'media_type' => $img['mime'],
                        'data' => $img['data'],
                    ),
                );
            }
            return array(
                array('role' => 'system', 'content' => $systemPrompt),
                array('role' => 'user', 'content' => array(
                    $imageBlock,
                    array('type' => 'text', 'text' => $textPrompt),
                )),
            );
        }
        $imageUrl = ($img['kind'] === 'url')
            ? $img['url']
            : ('data:' . $img['mime'] . ';base64,' . $img['data']);
        return array(
            array('role' => 'system', 'content' => $systemPrompt),
            array('role' => 'user', 'content' => array(
                array('type' => 'image_url', 'image_url' => array('url' => $imageUrl)),
                array('type' => 'text', 'text' => $textPrompt),
            )),
        );
    }

    /**
     * 选取视觉模型：用户已配置 VL 模型则沿用，否则用服务商默认
     */
    private function resolveVisionModel(array $cfg)
    {
        $model = $cfg['model'];
        if ($this->isVisionModelName($model)) {
            return $model;
        }
        $defs = ai_provider_defs();
        $vision = isset($defs[$cfg['provider']]['vision_model']) ? $defs[$cfg['provider']]['vision_model'] : '';
        return $vision !== '' ? $vision : $model;
    }

    /**
     * 按模型名称启发式识别视觉模型（非对模型真实能力的完整判断）
     */
    private function isVisionModelName($model)
    {
        return (bool) preg_match('/vl|vision|4o|gemini/i', (string) $model);
    }

    /**
     * 是否尝试多模态视觉：DeepSeek chat/completions 仅文本；OpenAI 兼容按模型名识别，不改用其他模型
     */
    private function providerSupportsVisionApi(array $cfg)
    {
        if ($cfg['provider'] === 'openai') {
            return $this->isVisionModelName($cfg['model']);
        }
        return in_array($cfg['provider'], array('qwen', 'claude'), true);
    }

    /**
     * Alt 文本回退：DeepSeek 推理模型改用 Flash，保留 Flash 的 1024 token 输出额度
     */
    private function resolveAltTextCall(array $cfg)
    {
        $model = $cfg['model'];
        if ($cfg['provider'] === 'deepseek' && $this->isReasoningModel($model)) {
            $model = 'deepseek-v4-flash';
        }
        $maxTokens = ($cfg['provider'] === 'deepseek' && stripos($model, 'flash') !== false) ? 1024 : 256;
        return array('model' => $model, 'max_tokens' => $maxTokens);
    }

    private function isReasoningModel($model)
    {
        $model = strtolower((string) $model);
        if ($model === '' || strpos($model, 'flash') !== false) {
            return false;
        }
        return (bool) preg_match('/reasoner|(?:^|[\-_])r1(?:[\-_]|$)|thinking|deepseek-v\d.*pro/', $model);
    }

    private function shouldUseFlashForText(array $cfg)
    {
        if (! in_array($this->aiScene, array('generate', 'rewrite', 'tdk'), true)) {
            return false;
        }
        return $cfg['provider'] === 'deepseek' && $this->isReasoningModel($cfg['model']);
    }

    private function shouldDisableThinking(array $cfg)
    {
        // Flash 默认也启用思考，文本与 Alt 场景需显式关闭；测试连接保留模型默认行为。
        return $cfg['provider'] === 'deepseek'
            && in_array($this->aiScene, array('generate', 'rewrite', 'tdk', 'alt'), true);
    }

    private function beginAiCall($scene, $task = '')
    {
        $this->aiScene = (string) $scene;
        $this->aiTask = (string) $task;
        $this->aiModel = '';
        $this->aiConfigured = '';
        $this->aiDeadline = microtime(true) + self::AI_OPERATION_BUDGET;
        $this->aiPhpBudgetUntil = 0;
        $this->aiProvider = '';
        $this->aiSecrets = array();
        $this->aiHttpSent = false;
    }

    private function rememberAiModel($actual, $configured = '')
    {
        $actual = (string) $actual;
        $configured = (string) $configured;
        $this->aiModel = $actual;
        if ($configured !== '' && $configured !== $actual) {
            $this->aiConfigured = $configured;
        } else {
            $this->aiConfigured = '';
        }
    }

    private function emptyByLengthMessage(array $cfg, $key, $reason)
    {
        $model = isset($cfg['model']) ? $cfg['model'] : '';
        $fixedLimit = in_array($this->aiScene, array('ping', 'alt'), true);
        $hint = '推理模型把输出额度用在思考上，正文为空。';
        if (isset($cfg['provider']) && $cfg['provider'] === 'deepseek' && $this->isReasoningModel($model)) {
            $hint .= $fixedLimit ? '请改用 deepseek-v4-flash' : '请改用 deepseek-v4-flash，或增大「最大 Token」';
        } else {
            $hint .= $fixedLimit ? '当前场景使用固定输出上限，请改用非推理模型' : '请增大「最大 Token」';
        }
        return $hint . '（' . $key . '=' . $reason . '）';
    }

    /**
     * 从 image_src 读取本站 upload 图片为 base64
     */
    private function readLocalUploadImage($imageSrc)
    {
        $rel = $this->extractUploadRelPath($imageSrc);
        if ($rel === '') {
            return null;
        }
        $uploadRoot = realpath(DOC_PATH . STATIC_DIR . '/upload');
        if (! $uploadRoot) {
            return null;
        }
        $filePath = realpath($uploadRoot . '/' . str_replace('\\', '/', $rel));
        $rootNorm = str_replace('\\', '/', $uploadRoot);
        $fileNorm = $filePath ? str_replace('\\', '/', $filePath) : '';
        if (! $filePath || strpos($fileNorm, $rootNorm . '/') !== 0 && $fileNorm !== $rootNorm) {
            return null;
        }
        if (! preg_match('/\.(jpe?g|png|gif|webp)$/i', $filePath)) {
            return null;
        }
        $size = filesize($filePath);
        if ($size === false || $size > 2 * 1024 * 1024) {
            return null;
        }
        $binary = @file_get_contents($filePath);
        if ($binary === false || $binary === '') {
            return null;
        }
        return array(
            'kind' => 'base64',
            'mime' => $this->mimeFromImagePath($filePath),
            'data' => base64_encode($binary),
        );
    }

    /**
     * 提取 upload 相对路径
     */
    private function extractUploadRelPath($imageSrc)
    {
        $imageSrc = is_string($imageSrc) ? trim($imageSrc) : '';
        if ($imageSrc !== '' && preg_match('~/upload/([^?#]+)~i', $imageSrc, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * 公网远程图片 URL 校验（防 SSRF，供视觉 API URL 直传）
     */
    private function isPublicRemoteImageUrl($url)
    {
        if (! is_string($url) || $url === '') {
            return false;
        }
        if (! preg_match('#^https?://#i', $url)) {
            return false;
        }
        $parts = parse_url($url);
        if (empty($parts['host'])) {
            return false;
        }
        $host = strtolower($parts['host']);
        if (in_array($host, array('localhost', '127.0.0.1', '::1'), true)) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! $this->isPrivateIp($host);
        }
        $ips = @gethostbynamel($host);
        if (is_array($ips)) {
            foreach ($ips as $ip) {
                if ($this->isPrivateIp($ip)) {
                    return false;
                }
            }
        }
        return true;
    }

    private function mimeFromImagePath($path)
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = array(
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        );
        return $map[$ext] ?? 'image/jpeg';
    }

    private function isPrivateIp($ip)
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        $ip = strtolower($ip);
        if ($ip === '::1') {
            return true;
        }
        if (strpos($ip, 'fe80:') === 0) {
            return true;
        }
        return (bool) preg_match('/^(fc|fd)[0-9a-f]{2}:/i', $ip);
    }

    /**
     * AI 专用调用日志（不含 Key / 正文），归档到 data/log/ai/Y/Ym/
     */
    private function aiWriteLog($action, $detail = '')
    {
        $dir = DOC_PATH . DATA_DIR . '/log/ai/' . date('Y/Ym');
        for ($i = 0; $i < 8; $i ++) {
            if (check_dir($dir, true)) {
                break;
            }
            usleep(2000);
        }
        if (! is_dir($dir)) {
            return;
        }
        $file = $dir . '/' . date('Ymd') . '.log';
        $user = session('username') ?: ('uid:' . (session('id') ?: '0'));
        $provider = $this->aiProvider !== '' ? $this->aiProvider : ($this->config('ai_provider') ?: 'deepseek');
        $line = date('Y-m-d H:i:s') . ' [' . $action . ']'
            . ' provider=' . $this->aiLogToken($provider)
            . ' model=' . $this->aiLogToken($this->aiModel)
            . ' user=' . $this->aiLogToken($user)
            . ' scene=' . $this->aiLogToken($this->aiScene)
            . ' task=' . $this->aiLogToken($this->aiTask);
        if ($this->aiConfigured !== '') {
            $line .= ' configured=' . $this->aiLogToken($this->aiConfigured);
        }
        if ($detail !== '') {
            $detail = $this->aiRedact($detail);
            $detail = preg_replace('/\s+/', ' ', strip_tags($detail));
            $line .= ' ' . mb_substr(trim($detail), 0, 200);
        }
        $line .= PHP_EOL;
        $this->aiAppendLog($file, $line);
    }

    private function aiLogToken($value)
    {
        $value = trim(strip_tags((string) $value));
        $value = preg_replace('/\s+/', '_', $value);
        if ($value === '' || strpos($value, '=') !== false) {
            return '-';
        }
        return $value;
    }

    private function aiAppendLog($path, $content)
    {
        $fp = @fopen($path, 'ab');
        if (! $fp) {
            return false;
        }
        if (! flock($fp, LOCK_EX)) {
            fclose($fp);
            return false;
        }
        $result = fwrite($fp, $content);
        flock($fp, LOCK_UN);
        fclose($fp);
        return $result !== false;
    }

    /**
     * OpenAI 兼容协议（DeepSeek / 通义千问）
     */
    private function callOpenAiCompat($cfg, array $messages, $maxTokens)
    {
        $url = $cfg['endpoint']['url'];
        $payload = array(
            'model' => $cfg['model'],
            'messages' => $messages,
            'max_tokens' => (int) $maxTokens,
            'stream' => false,
        );
        if ($this->shouldDisableThinking($cfg)) {
            $payload['thinking'] = array('type' => 'disabled');
        }
        if (! $this->shouldOmitTemperature($cfg)) {
            $payload['temperature'] = 0.7;
        }
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $cfg['api_key'],
        );
        $resp = $this->httpPostJson($url, $payload, $headers);
        if ($resp['code'] !== 1) {
            return $resp;
        }
        $body = json_decode($resp['data'], true);
        if (! is_array($body)) {
            $this->aiWriteLog('error', 'invalid json');
            return $this->errResult('AI 响应非 JSON');
        }
        if (isset($body['error'])) {
            if (is_array($body['error'])) {
                $msg = isset($body['error']['message']) ? (string) $body['error']['message'] : $this->aiErrorText($body['error']);
            } else {
                $msg = (string) $body['error'];
            }
            $this->aiWriteLog('error', $msg);
            return $this->errResult('AI 服务返回错误：' . $msg);
        }
        if (empty($body['choices']) || ! is_array($body['choices'])) {
            $this->aiWriteLog('error', 'no choices');
            $mismatch = $this->diagnoseAnthropicShapeInBody($body);
            return $this->errResult($mismatch ?: 'AI 响应无有效 内容');
        }
        $finish = isset($body['choices'][0]['finish_reason']) ? (string) $body['choices'][0]['finish_reason'] : '';
        $message = (isset($body['choices'][0]['message']) && is_array($body['choices'][0]['message']))
            ? $body['choices'][0]['message']
            : array();
        if (! array_key_exists('content', $message) || $message['content'] === null) {
            if ($finish === 'length') {
                $msg = $this->emptyByLengthMessage($cfg, 'finish_reason', 'length');
                $this->aiWriteLog('error', $msg);
                return $this->errResult($msg, 'length');
            }
            $this->aiWriteLog('error', 'missing content');
            $mismatch = $this->diagnoseAnthropicShapeInBody($body);
            return $this->errResult($mismatch ?: 'AI 响应字段缺失');
        }
        $content = $message['content'];
        if (is_array($content)) {
            $parts = array();
            foreach ($content as $part) {
                if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                    $parts[] = $part['text'];
                }
            }
            $content = implode('', $parts);
        }
        if (! is_string($content)) {
            $content = (string) $content;
        }
        if (trim($content) === '') {
            if ($finish === 'length') {
                $msg = $this->emptyByLengthMessage($cfg, 'finish_reason', 'length');
            } else {
                $reason = $finish !== '' ? $finish : 'unknown';
                $msg = 'AI 返回内容为空（finish_reason=' . $reason . '）';
            }
            $this->aiWriteLog('error', $msg);
            return $this->errResult($msg, $finish === 'length' ? 'length' : '');
        }
        if ($finish === 'length') {
            $this->aiWriteLog('truncated', 'finish_reason=length');
        }
        return $this->okResult($content);
    }

    /**
     * Anthropic Claude 协议
     */
    private function callClaude($cfg, array $messages, $maxTokens)
    {
        $url = $cfg['endpoint']['url'];
        // Claude 区分 system 与 messages
        $system = '';
        $msgs = array();
        foreach ($messages as $m) {
            if (isset($m['role']) && $m['role'] === 'system') {
                $system .= ($system ? "\n" : '') . $m['content'];
            } else {
                $msgs[] = array(
                    'role' => $m['role'],
                    'content' => $m['content'],
                );
            }
        }
        $payload = array(
            'model' => $cfg['model'],
            'max_tokens' => (int) $maxTokens,
            'messages' => $msgs,
        );
        if ($this->shouldDisableThinking($cfg)) {
            $payload['thinking'] = array('type' => 'disabled');
        }
        if ($system) {
            $payload['system'] = $system;
        }
        $headers = array(
            'Content-Type: application/json',
            'x-api-key: ' . $cfg['api_key'],
            'anthropic-version: 2023-06-01',
        );
        $resp = $this->httpPostJson($url, $payload, $headers);
        if ($resp['code'] !== 1) {
            return $resp;
        }
        $body = json_decode($resp['data'], true);
        if (! is_array($body)) {
            $this->aiWriteLog('error', 'invalid json');
            return $this->errResult('AI 响应非 JSON');
        }
        if (isset($body['error'])) {
            if (is_array($body['error'])) {
                $msg = isset($body['error']['message']) ? (string) $body['error']['message'] : $this->aiErrorText($body['error']);
            } else {
                $msg = (string) $body['error'];
            }
            $this->aiWriteLog('error', $msg);
            return $this->errResult('AI 服务返回错误：' . $msg);
        }
        if (empty($body['content']) || ! is_array($body['content'])) {
            $stop = isset($body['stop_reason']) ? (string) $body['stop_reason'] : '';
            if ($stop === 'max_tokens') {
                $msg = $this->emptyByLengthMessage($cfg, 'stop_reason', 'max_tokens');
                $this->aiWriteLog('error', $msg);
                return $this->errResult($msg, 'length');
            }
            $this->aiWriteLog('error', 'no content');
            $mismatch = $this->diagnoseOpenAiShapeInBody($body);
            return $this->errResult($mismatch ?: 'AI 响应无有效 内容');
        }
        if (! isset($body['content'][0]['text'])) {
            $this->aiWriteLog('error', 'missing text');
            $mismatch = $this->diagnoseOpenAiShapeInBody($body);
            return $this->errResult($mismatch ?: 'AI 响应字段缺失');
        }
        $content = $body['content'][0]['text'];
        $stop = isset($body['stop_reason']) ? (string) $body['stop_reason'] : '';
        if (! is_string($content) || trim($content) === '') {
            if ($stop === 'max_tokens') {
                $msg = $this->emptyByLengthMessage($cfg, 'stop_reason', 'max_tokens');
            } else {
                $reason = $stop !== '' ? $stop : 'unknown';
                $msg = 'AI 返回内容为空（stop_reason=' . $reason . '）';
            }
            $this->aiWriteLog('error', $msg);
            return $this->errResult($msg, $stop === 'max_tokens' ? 'length' : '');
        }
        if ($stop === 'max_tokens') {
            $this->aiWriteLog('truncated', 'stop_reason=max_tokens');
        }
        return $this->okResult($content);
    }

    /**
     * Claude 经 OpenAI 兼容中转时通常不接受 temperature 参数
     */
    private function shouldOmitTemperature(array $cfg)
    {
        if ($cfg['provider'] === 'claude' || $cfg['provider'] === 'openai') {
            return true;
        }
        return (bool) preg_match('/claude/i', $cfg['model']);
    }

    /**
     * OpenAI 解析失败时检测是否为 Anthropic Messages 响应
     */
    private function diagnoseAnthropicShapeInBody($body)
    {
        if (! is_array($body)) {
            return '';
        }
        if (isset($body['content']) && is_array($body['content']) && ! isset($body['choices'])) {
            return 'AI 响应为 Anthropic Messages 格式，与当前 OpenAI 兼容协议不一致。'
                . '请检查 API URL：Anthropic 原生请使用 /v1/messages，OpenAI 兼容请使用 /v1/chat/completions。';
        }
        return '';
    }

    /**
     * Anthropic 解析失败时检测是否为 OpenAI Chat Completions 响应
     */
    private function diagnoseOpenAiShapeInBody($body)
    {
        if (! is_array($body)) {
            return '';
        }
        if (isset($body['choices']) && is_array($body['choices']) && ! isset($body['content'])) {
            return 'AI 响应为 OpenAI Chat Completions 格式，与当前 Anthropic 协议不一致。'
                . '请检查 API URL：OpenAI 兼容请使用 /v1/chat/completions，Anthropic 原生请使用 /v1/messages。';
        }
        return '';
    }

    private function aiResolveHttpWait()
    {
        $now = microtime(true);
        $desired = $this->aiDesiredWait($now);
        if ($desired < 1) {
            return 0;
        }
        $phpLeft = $this->aiPhpSecondsLeft($now);
        if ($phpLeft === null) {
            return $desired;
        }
        $need = $desired + self::AI_TAIL_MARGIN;
        if (($phpLeft + 0.001) < $need) {
            if ($this->aiTryExtendTimeLimit($need)) {
                return $desired;
            }
            $now = microtime(true);
            $desired = $this->aiDesiredWait($now);
            if ($desired < 1) {
                return 0;
            }
            $phpLeft = $this->aiPhpSecondsLeft($now);
            if ($phpLeft === null) {
                return $desired;
            }
            $wait = (int) floor(min($desired, $phpLeft - self::AI_TAIL_MARGIN));
            if ($wait < 1) {
                return 0;
            }
            return $wait;
        }
        return $desired;
    }

    private function aiDesiredWait($now)
    {
        if ($this->aiDeadline <= 0) {
            return 0;
        }
        $opLeft = $this->aiDeadline - $now - self::AI_TAIL_MARGIN;
        $desired = (int) floor(min(self::AI_HTTP_MAX, $opLeft));
        if ($desired < 1) {
            return 0;
        }
        return $desired;
    }

    private function aiPhpSecondsLeft($now)
    {
        if ($this->aiPhpBudgetUntil > 0) {
            return $this->aiPhpBudgetUntil - $now;
        }
        $limit = (int) ini_get('max_execution_time');
        if ($limit <= 0) {
            return null;
        }
        $start = isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : $now;
        return $limit - ($now - $start);
    }

    private function aiCanSetTimeLimit()
    {
        if (! function_exists('set_time_limit')) {
            return false;
        }
        $disabled = ini_get('disable_functions');
        if (! is_string($disabled) || trim($disabled) === '') {
            return true;
        }
        $names = explode(',', strtolower($disabled));
        foreach ($names as $name) {
            if (trim($name) === 'set_time_limit') {
                return false;
            }
        }
        return true;
    }

    private function aiTryExtendTimeLimit($seconds)
    {
        $seconds = (int) $seconds;
        if ($seconds < 1 || ! $this->aiCanSetTimeLimit()) {
            return false;
        }
        $ok = set_time_limit($seconds);
        if ($ok !== true) {
            return false;
        }
        $this->aiPhpBudgetUntil = microtime(true) + $seconds;
        return true;
    }

    private function aiTimeoutMessage()
    {
        return 'AI 请求超时，请缩短原文或改用非推理模型后重试';
    }

    /**
     * 通用 cURL POST JSON
     */
    private function httpPostJson($url, array $payload, array $headers)
    {
        $wait = $this->aiResolveHttpWait();
        if ($wait < 1) {
            $msg = $this->aiTimeoutMessage();
            $this->aiWriteLog('error', $msg);
            return $this->errResult($msg);
        }
        $connect = min(self::AI_CONNECT_MAX, $wait);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect);
        curl_setopt($ch, CURLOPT_TIMEOUT, $wait);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PbootCMS-AI/1.0');
        if (strpos($url, 'https://') === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_CAINFO, CORE_PATH . '/cacert.pem');
        }
        $this->aiHttpSent = true;
        $output = curl_exec($ch);
        if ($output === false) {
            $err = curl_error($ch);
            $errno = curl_errno($ch);
            curl_safe_close($ch);
            if ((int) $errno === 28) {
                $msg = $this->aiTimeoutMessage();
            } else {
                $msg = '网络请求失败(' . $errno . ')：' . $err;
            }
            $this->aiWriteLog('error', $msg);
            return $this->errResult($msg);
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_safe_close($ch);
        if ($status < 200 || $status >= 300) {
            $text = $this->aiErrorText((string) $output);
            $snippet = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($this->aiRedact($text)))), 0, 200);
            $msg = 'AI 服务 HTTP ' . $status . '：' . $snippet;
            $this->aiWriteLog('error', $msg);
            return $this->errResult($msg);
        }
        if (trim((string) $output) === '') {
            $this->aiWriteLog('error', 'empty response');
            return $this->errResult('AI 服务返回空响应');
        }
        return $this->okResult($output);
    }

    /**
     * 解析 AI 返回的 TDK JSON（兼容包裹在 ```json``` 中的情况）
     */
    private function parseJsonReply($text)
    {
        $text = trim($text);
        // 去除 markdown 代码块包裹
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $text, $m)) {
            $text = $m[1];
        }
        // 截取第一个 { ... }
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $text = substr($text, $start, $end - $start + 1);
        }
        $data = json_decode($text, true);
        if (! is_array($data)) {
            return null;
        }
        // keywords 兼容数组形式
        if (isset($data['keywords']) && is_array($data['keywords'])) {
            $data['keywords'] = implode(',', $data['keywords']);
        }
        return $data;
    }

    /**
     * 富文本输出净化：保留段落与基本格式标签，剔除脚本/样式
     */
    private function sanitizeHtml($html)
    {
        $allow = '<p><br><h1><h2><h3><h4><strong><b><em><i><u><ul><ol><li><blockquote>';
        // 先去掉 script / style 整段
        $html = preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is', '', $html);
        $html = strip_tags($html, $allow);
        // 去掉所有 on* 事件属性 与 javascript: 链接
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/javascript\s*:/i', '', $html);
        return trim($html);
    }

    /**
     * 纯文本字段净化：去标签 + html 实体转义 + 截断
     */
    private function safePlain($text, $maxLen = 200)
    {
        $text = strip_tags((string) $text);
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (function_exists('mb_substr') && mb_strlen($text) > $maxLen) {
            $text = mb_substr($text, 0, $maxLen);
        } elseif (strlen($text) > $maxLen * 3) {
            $text = substr($text, 0, $maxLen * 3);
        }
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * 输入文本基础校验：长度限制
     */
    private function safeText($text, $maxLen)
    {
        if (! is_string($text)) {
            return '';
        }
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if (function_exists('mb_substr') && mb_strlen($text) > $maxLen) {
            $text = mb_substr($text, 0, $maxLen);
        }
        return $text;
    }

    private function okResult($data)
    {
        return array('code' => 1, 'msg' => '', 'data' => $data);
    }

    private function errResult($msg, $reason = '')
    {
        $rs = array('code' => 0, 'msg' => $this->aiRedact($msg), 'data' => null);
        if ($reason !== '') {
            $rs['reason'] = $reason;
        }
        return $rs;
    }

    /**
     * 输出 JSON：成功
     */
    private function jsonOk($data)
    {
        @ob_clean();
        echo json_encode(array(
            'code' => 1,
            'msg' => 'ok',
            'data' => $data,
        ), JSON_UNESCAPED_UNICODE);
        exit();
    }

    /**
     * 输出 JSON：失败
     */
    private function jsonError($msg, $data = null)
    {
        @ob_clean();
        echo json_encode(array(
            'code' => 0,
            'msg' => $msg,
            'data' => $data,
        ), JSON_UNESCAPED_UNICODE);
        exit();
    }
}
