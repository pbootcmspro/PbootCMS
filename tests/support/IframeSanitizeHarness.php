<?php

declare(strict_types=1);

/**
 * iframe 清洗测试夹具：安全不变量断言
 */
final class IframeSanitizeHarness
{
    /** @param string[] $hosts */
    public static function whitelistMap(array $hosts): array
    {
        $map = array();
        foreach ($hosts as $host) {
            $map[$host] = true;
        }
        return $map;
    }

    public static function hasEventHandlerAttr(string $html): bool
    {
        return (bool) preg_match('/\s+on\w+\s*=/i', $html);
    }

    public static function hasDangerousScheme(string $html): bool
    {
        return (bool) preg_match('/\b(javascript|vbscript)\s*:/i', $html);
    }

    public static function hasIframeTag(string $html): bool
    {
        return stripos($html, '<iframe') !== false;
    }

    public static function hasUnmatchedIframeClose(string $html): bool
    {
        $opens = preg_match_all('/<iframe\b/i', $html, $m1);
        $closes = preg_match_all('/<\/iframe\s*>/i', $html, $m2);
        return $closes > $opens;
    }

    /** 输出不得含未转义的 </script（case-insensitive） */
    public static function hasBareScriptClose(string $html): bool
    {
        return (bool) preg_match('/<\/script/i', $html);
    }

    /**
     * 白名单重建契约：允许的安全属性集，禁止 on* / 危险 scheme
     *
     * @param bool $expectIframe 是否期望输出中仍含 iframe
     */
    public static function assertSafeIframeContract(string $html, bool $expectIframe, string $label)
    {
        if ($expectIframe) {
            TestAssert::contains($html, '<iframe', "$label: has iframe");
            TestAssert::contains($html, 'sandbox=', "$label: has sandbox");
            TestAssert::contains($html, 'referrerpolicy=', "$label: has referrerpolicy");
            TestAssert::contains($html, 'frameborder="0"', "$label: has frameborder");
        }
        TestAssert::false(self::hasEventHandlerAttr($html), "$label: no on* attrs");
        TestAssert::false(self::hasDangerousScheme($html), "$label: no dangerous scheme");
    }
}
