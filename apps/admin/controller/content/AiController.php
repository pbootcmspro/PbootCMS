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

    // 服务商默认接入参数
    private static $providers = array(
        'deepseek' => array(
            'name' => 'DeepSeek',
            'base' => 'https://api.deepseek.com/chat/completions',
            'path' => '',
            'model' => 'deepseek-v4-flash',
        ),
        'qwen' => array(
            'name' => '通义千问',
            'base' => 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions',
            'path' => '',
            'model' => 'qwen-plus-latest',
            'vision_model' => 'qwen-vl-max',
        ),
        'claude' => array(
            'name' => 'Claude',
            'base' => 'https://api.anthropic.com/v1/messages',
            'path' => '',
            'model' => 'claude-sonnet-4-20250514',
            'vision_model' => 'claude-sonnet-4-20250514',
        ),
    );

    /**
     * 健康检查（POST），配置页「测试连接」；需系统配置权限 + 限流 + formcheck
     */
    public function ping()
    {
        $this->guardConfigAccess();
        $this->guard();
        $cfg = $this->getProviderConfig();
        if (! $cfg['api_key']) {
            $this->jsonError('未配置 API Key');
        }
        $rs = $this->callAi(array(
            array('role' => 'user', 'content' => '你好，请回复"连接成功"。'),
        ), 32);
        if ($rs['code'] !== 1) {
            $this->jsonError($rs['msg']);
        }
        $this->jsonOk(array('reply' => $rs['data']));
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
        $this->aiWriteLog('rewrite', 'mode=' . $mode);
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
        $cfg = $this->getProviderConfig();
        $img = $this->resolveAltImageForVision($imageSrc);
        $visionMode = 'text';
        $rs = null;
        $altVisionMaxTokens = 1024;
        $altTextCall = $this->resolveAltTextCall($cfg);
        $visionSupported = $this->providerSupportsVisionApi($cfg);
        if ($img && $visionSupported) {
            $messages = $this->buildVisionMessages($systemPrompt, $textPrompt, $img, $cfg);
            $this->consumeRateLimit();
            $rs = $this->callAiVision($messages, $altVisionMaxTokens);
            if ($rs['code'] === 1 && trim((string) ($rs['data'] ?? '')) !== '') {
                $visionMode = $img['kind'];
            }
        }
        if (! $rs || $rs['code'] !== 1 || trim((string) ($rs['data'] ?? '')) === '') {
            $messages = array(
                array('role' => 'system', 'content' => $systemPrompt),
                array('role' => 'user', 'content' => $textPrompt),
            );
            $this->consumeRateLimit();
            $rs = $this->callAi($messages, $altTextCall['max_tokens'], $altTextCall['model']);
            $visionMode = 'text';
        }
        if ($rs['code'] !== 1) {
            $this->jsonError($rs['msg']);
        }
        $altRaw = trim((string) $rs['data'], " \t\n\r\0\x0B\"'");
        if ($altRaw === '') {
            $this->jsonError('AI 未能生成有效的 Alt 描述');
        }
        $alt = $this->safeText($altRaw, 60);
        $this->aiWriteLog('alt', 'mode=' . $visionMode . ' title=' . mb_substr($title, 0, 30));
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
        $providerKey = $this->config('ai_provider') ?: 'deepseek';
        if (! isset(self::$providers[$providerKey])) {
            $providerKey = 'deepseek';
        }
        $defaults = self::$providers[$providerKey];
        $base = $this->config('ai_api_base');
        $model = $this->config('ai_model');
        $maxTokens = (int) $this->config('ai_max_tokens');
        $apiKey = aes_decrypt($this->config('ai_api_key'));
        return array(
            'provider' => $providerKey,
            'base' => $base ?: $defaults['base'],
            'path' => $defaults['path'],
            'model' => $model ?: $defaults['model'],
            'api_key' => $apiKey,
            'max_tokens' => $maxTokens > 0 ? $maxTokens : 2048,
        );
    }

    /**
     * 按 API URL 自动解析协议：官方 Anthropic Messages → anthropic，其余 → openai_compat
     */
    private function resolveProtocol(array $cfg)
    {
        $url = strtolower(rtrim($cfg['base'], '/') . ($cfg['path'] ?? ''));
        if (preg_match('#/messages(?:/|$)#', $url)) {
            return 'anthropic';
        }
        return 'openai_compat';
    }

    /**
     * 请求前校验自动推断的协议与 URL 是否匹配
     * @return array|null errResult 或 null 表示通过
     */
    private function validateAiConfig(array $cfg)
    {
        $url = strtolower(rtrim($cfg['base'], '/') . ($cfg['path'] ?? ''));
        $protocol = $cfg['protocol'];
        $hasOpenAi = (strpos($url, 'chat/completions') !== false);
        $hasAnthropic = (bool) preg_match('#/messages(?:/|$)#', $url);

        if ($protocol === 'openai_compat' && ! $hasOpenAi) {
            $this->aiWriteLog('error:config', 'openai_compat url missing chat/completions');
            if ($cfg['provider'] === 'claude') {
                return $this->errResult(
                    '当前服务商为 Claude，API URL 需填写完整端点，例如：'
                    . ' OpenAI 兼容 https://网关/v1/chat/completions'
                    . ' 或 Anthropic 原生 https://网关/v1/messages'
                );
            }
            return $this->errResult(
                'OpenAI 兼容协议要求 URL 包含 /chat/completions，'
                . '示例：https://网关域名/v1/chat/completions'
            );
        }
        if ($protocol === 'anthropic' && ! $hasAnthropic) {
            $this->aiWriteLog('error:config', 'anthropic url missing messages');
            return $this->errResult(
                'Anthropic 协议要求 URL 包含 /messages，'
                . '示例：https://网关域名/v1/messages'
            );
        }
        return null;
    }

    /**
     * 按协议路由至 OpenAI 兼容或 Anthropic Messages 实现
     */
    private function invokeProvider(array $cfg, array $messages, $maxTokens)
    {
        $cfg['protocol'] = $this->resolveProtocol($cfg);
        $check = $this->validateAiConfig($cfg);
        if ($check !== null) {
            return $check;
        }
        if ($cfg['protocol'] === 'anthropic') {
            return $this->callClaude($cfg, $messages, $maxTokens);
        }
        return $this->callOpenAiCompat($cfg, $messages, $maxTokens);
    }

    /**
     * 统一 AI 调用入口
     * @param array $messages OpenAI 风格 messages
     * @param int|null $maxTokens 单次最大 token
     * @return array {code:0/1, msg, data}
     */
    private function callAi(array $messages, $maxTokens = null, $modelOverride = null)
    {
        $cfg = $this->getProviderConfig();
        if (! $cfg['api_key']) {
            return $this->errResult('AI API Key 未配置');
        }
        if ($modelOverride !== null && $modelOverride !== '') {
            $cfg['model'] = $modelOverride;
        }
        if ($maxTokens === null) {
            $maxTokens = $cfg['max_tokens'];
        }
        try {
            if (! isset(self::$providers[$cfg['provider']])) {
                return $this->errResult('不支持的服务商：' . $cfg['provider']);
            }
            return $this->invokeProvider($cfg, $messages, $maxTokens);
        } catch (\Throwable $e) {
            $this->aiWriteLog('error:exception', $e->getMessage());
            $this->log('[AI] 调用异常：' . $e->getMessage(), 'error');
            return $this->errResult('AI 服务调用异常');
        }
    }

    /**
     * 视觉模型调用（复用 ai_api_base / ai_api_key，仅切换 vision 模型）
     */
    private function callAiVision(array $messages, $maxTokens = 64)
    {
        $cfg = $this->getProviderConfig();
        if (! $cfg['api_key']) {
            return $this->errResult('AI API Key 未配置');
        }
        $cfg['model'] = $this->resolveVisionModel($cfg);
        try {
            if (! isset(self::$providers[$cfg['provider']])) {
                return $this->errResult('不支持的服务商：' . $cfg['provider']);
            }
            return $this->invokeProvider($cfg, $messages, $maxTokens);
        } catch (\Throwable $e) {
            $this->aiWriteLog('error:exception', 'vision ' . $e->getMessage());
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
        if (preg_match('/vl|vision|4o|gemini/i', $model)) {
            return $model;
        }
        $defaults = self::$providers[$cfg['provider']] ?? array();
        return $defaults['vision_model'] ?? $model;
    }

    /**
     * 官方 API 是否支持多模态视觉（DeepSeek chat/completions 仅文本，传 image_url 会 HTTP 400）
     */
    private function providerSupportsVisionApi(array $cfg)
    {
        if ($cfg['provider'] === 'deepseek') {
            return false;
        }
        return in_array($cfg['provider'], array('qwen', 'claude'), true);
    }

    /**
     * Alt 文本回退：推理模型改用轻量 deepseek-v4-flash 模型，避免 token 耗尽在 reasoning 上
     */
    private function resolveAltTextCall(array $cfg)
    {
        $model = $cfg['model'];
        if ($cfg['provider'] === 'deepseek' && $this->isReasoningModel($model)) {
            return array('model' => 'deepseek-v4-flash', 'max_tokens' => 1024);
        }
        return array('model' => $model, 'max_tokens' => 256);
    }

    private function isReasoningModel($model)
    {
        return (bool) preg_match('/reasoner|(?:^|[\-_])r1(?:[\-_]|$)|deepseek-v\d/i', (string) $model);
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
     * AI 专用调用日志（不含 Key / 正文），写入 runtime/log/ai_YYYYMMDD.log
     */
    private function aiWriteLog($action, $detail = '')
    {
        $dir = RUN_PATH . '/log';
        if (! check_dir($dir, true)) {
            return;
        }
        $file = $dir . '/ai_' . date('Ymd') . '.log';
        $user = session('username') ?: ('uid:' . (session('id') ?: '0'));
        $provider = $this->config('ai_provider') ?: 'deepseek';
        $line = date('Y-m-d H:i:s') . ' [' . $action . '] provider=' . $provider . ' user=' . $user;
        if ($detail !== '') {
            $detail = preg_replace('/sk-[a-zA-Z0-9]+/i', 'sk-***', (string) $detail);
            $line .= ' ' . mb_substr(strip_tags($detail), 0, 200);
        }
        $line .= PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND);
    }

    /**
     * OpenAI 兼容协议（DeepSeek / 通义千问）
     */
    private function callOpenAiCompat($cfg, array $messages, $maxTokens)
    {
        $url = rtrim($cfg['base'], '/') . $cfg['path'];
        $payload = array(
            'model' => $cfg['model'],
            'messages' => $messages,
            'max_tokens' => (int) $maxTokens,
            'stream' => false,
        );
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
            $this->aiWriteLog('error:openai', 'model=' . $cfg['model'] . ' invalid json');
            return $this->errResult('AI 响应非 JSON');
        }
        if (isset($body['error'])) {
            $msg = is_array($body['error']) ? ($body['error']['message'] ?? json_encode($body['error'])) : (string) $body['error'];
            $this->aiWriteLog('error:openai', 'model=' . $cfg['model'] . ' ' . $msg);
            return $this->errResult('AI 服务返回错误：' . $msg);
        }
        if (empty($body['choices']) || ! is_array($body['choices'])) {
            $this->aiWriteLog('error:openai', 'model=' . $cfg['model'] . ' no choices');
            $mismatch = $this->diagnoseAnthropicShapeInBody($body);
            return $this->errResult($mismatch ?: 'AI 响应无有效 内容');
        }
        if (! isset($body['choices'][0]['message']['content'])) {
            $this->aiWriteLog('error:openai', 'model=' . $cfg['model'] . ' missing content');
            $mismatch = $this->diagnoseAnthropicShapeInBody($body);
            return $this->errResult($mismatch ?: 'AI 响应字段缺失');
        }
        if (isset($body['choices'][0]['finish_reason']) && $body['choices'][0]['finish_reason'] === 'length') {
            $this->aiWriteLog('truncated', 'openai_compat finish_reason=length');
        }
        $content = $body['choices'][0]['message']['content'];
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
            $reason = $body['choices'][0]['finish_reason'] ?? 'unknown';
            $msg = 'AI 返回内容为空（finish_reason=' . $reason . '）';
            $this->aiWriteLog('error:openai', 'model=' . $cfg['model'] . ' ' . $msg);
            return $this->errResult($msg);
        }
        return $this->okResult($content);
    }

    /**
     * Anthropic Claude 协议
     */
    private function callClaude($cfg, array $messages, $maxTokens)
    {
        $url = rtrim($cfg['base'], '/') . $cfg['path'];
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
            $this->aiWriteLog('error:claude', 'model=' . $cfg['model'] . ' invalid json');
            return $this->errResult('AI 响应非 JSON');
        }
        if (isset($body['error'])) {
            $msg = is_array($body['error']) && isset($body['error']['message']) ? $body['error']['message'] : json_encode($body['error']);
            $this->aiWriteLog('error:claude', 'model=' . $cfg['model'] . ' ' . $msg);
            return $this->errResult('AI 服务返回错误：' . $msg);
        }
        if (empty($body['content']) || ! is_array($body['content'])) {
            $this->aiWriteLog('error:claude', 'model=' . $cfg['model'] . ' no content');
            $mismatch = $this->diagnoseOpenAiShapeInBody($body);
            return $this->errResult($mismatch ?: 'AI 响应无有效 内容');
        }
        if (! isset($body['content'][0]['text'])) {
            $this->aiWriteLog('error:claude', 'model=' . $cfg['model'] . ' missing text');
            $mismatch = $this->diagnoseOpenAiShapeInBody($body);
            return $this->errResult($mismatch ?: 'AI 响应字段缺失');
        }
        if (isset($body['stop_reason']) && $body['stop_reason'] === 'max_tokens') {
            $this->aiWriteLog('truncated', 'claude stop_reason=max_tokens');
        }
        $content = $body['content'][0]['text'];
        if (! is_string($content) || trim($content) === '') {
            $reason = $body['stop_reason'] ?? 'unknown';
            $msg = 'AI 返回内容为空（stop_reason=' . $reason . '）';
            $this->aiWriteLog('error:claude', 'model=' . $cfg['model'] . ' ' . $msg);
            return $this->errResult($msg);
        }
        return $this->okResult($content);
    }

    /**
     * Claude 经 OpenAI 兼容中转时通常不接受 temperature 参数
     */
    private function shouldOmitTemperature(array $cfg)
    {
        if ($cfg['provider'] === 'claude') {
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

    /**
     * 通用 cURL POST JSON
     */
    private function httpPostJson($url, array $payload, array $headers)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PbootCMS-AI/1.0');
        if (strpos($url, 'https://') === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_CAINFO, CORE_PATH . '/cacert.pem');
        }
        $output = curl_exec($ch);
        if ($output === false) {
            $err = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            $msg = '网络请求失败(' . $errno . ')：' . $err;
            $this->aiWriteLog('error:http', $msg);
            return $this->errResult($msg);
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status < 200 || $status >= 300) {
            $snippet = mb_substr(strip_tags((string) $output), 0, 200);
            $msg = 'AI 服务 HTTP ' . $status . '：' . $snippet;
            $this->aiWriteLog('error:http', $msg);
            return $this->errResult($msg);
        }
        if (trim((string) $output) === '') {
            $this->aiWriteLog('error:http', 'empty response');
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

    private function errResult($msg)
    {
        return array('code' => 0, 'msg' => $msg, 'data' => null);
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
    private function jsonError($msg)
    {
        @ob_clean();
        echo json_encode(array(
            'code' => 0,
            'msg' => $msg,
            'data' => null,
        ), JSON_UNESCAPED_UNICODE);
        exit();
    }
}
