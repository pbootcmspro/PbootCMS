<?php

declare(strict_types=1);

/**
 * iframe 白名单清洗 / mXSS 回归 payload（仅作死字符串，禁止 eval / 外网请求）
 */
final class IframeSanitizePayloads
{
    /** 引号内出现 >，正则 [^>]* 易截断属性边界 */
    public static function malformedQuoteGt()
    {
        return '<iframe src="foo>bar" onload="evil()"></iframe>';
    }

    /** 双引号 src 内含 > */
    public static function malformedSrcPathGt()
    {
        return '<iframe src="https://example.com/x>y" onload="evil()"></iframe>';
    }

    /** 命中白名单但携带 onload */
    public static function whitelistedWithOnload()
    {
        return '<iframe onload="evil()" src="https://example.com/embed" width="560"></iframe>';
    }

    /** 重复 src：最后一个合法 */
    public static function duplicateSrcLastGood()
    {
        return '<iframe src="javascript:alert(1)" src="https://example.com/embed"></iframe>';
    }

    /** 重复 src：最后一个非法 */
    public static function duplicateSrcLastBad()
    {
        return '<iframe src="https://example.com/embed" src="javascript:alert(1)"></iframe>';
    }

    /** HTML 实体编码的 javascript: scheme */
    public static function entityEncodedJavascript()
    {
        return '&#106;avascript:alert(1)';
    }

    /** 十六进制实体 javascript: */
    public static function hexEntityJavascript()
    {
        return '&#x6A;avascript:alert(1)';
    }

    /** 未闭合 iframe */
    public static function unclosedWhitelisted()
    {
        return '<p>before</p><iframe src="https://example.com/embed" onload="x"><p>after</p>';
    }

    /** 嵌套：外层白名单、内层非白名单 */
    public static function nestedOuterGoodInnerBad()
    {
        return '<iframe src="https://example.com/outer"><iframe src="https://evil.com/inner"></iframe></iframe>';
    }

    /** 嵌套：外层非白名单、内层白名单 */
    public static function nestedOuterBadInnerGood()
    {
        return '<iframe src="https://evil.com/outer"><iframe src="https://example.com/inner"></iframe></iframe>';
    }

    /** 非白名单 iframe 应整段移除 */
    public static function nonWhitelisted()
    {
        return '<p>keep</p><iframe src="https://evil.com/x" onload="e()"></iframe><p>also</p>';
    }

    /** ueditor：字面量 </script> 破出 text/plain 容器 */
    public static function scriptCloseBreakout()
    {
        return 'hello</script><img onerror=1>';
    }

    /** ueditor：实体编码的 </script> */
    public static function scriptCloseEntity()
    {
        return 'hello&lt;/script&gt;<img>';
    }

    /** ueditor：含 iframe 的历史内容（fix/67 不得误删） */
    public static function editorContentWithIframe()
    {
        return '<p>text</p><iframe src="https://player.bilibili.com/video/1" width="560"></iframe>';
    }

    /** filter_html 端到端：iframe + script */
    public static function iframeWithScript()
    {
        return '<iframe src="https://example.com/x" onload="x()"></iframe><script>alert(1)</script>';
    }

    /** YouTube 官方嵌入样例（含 allow / strict-origin referrerpolicy） */
    public static function youtubeEmbed()
    {
        return '<iframe src="https://www.youtube.com/embed/-Nwk2wKCu7Q?si=DclTfZx6HqSk-3xc"'
            . ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"'
            . ' referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>';
    }

    /** allow 含未知 token / 带 origin 的扩展写法，应被剥除 */
    public static function allowWithUnknownTokens()
    {
        return '<iframe src="https://example.com/embed"'
            . ' allow="autoplay; evil-feature; fullscreen https://evil.com; encrypted-media"'
            . ' onload="x()"></iframe>';
    }
}
