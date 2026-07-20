<?php

declare(strict_types=1);

/**
 * 内容/栏目/站点 标题与描述 恶意与合法样本（仅作死字符串，禁止执行）
 *
 * 审计 #8 回归用
 */
final class TitleDescPayloads
{
    /** @return string[] */
    public static function xss(): array
    {
        return array(
            '<script>alert(1)</script>',
            '"><img src=x onerror=alert(1)>',
            "'\"><svg/onload=alert(1)>",
            '<img src=x onerror=alert(1)>',
            '<body onload=alert(1)>',
            'javascript:alert(1)',
        );
    }

    /** @return string[] */
    public static function sqli(): array
    {
        return array(
            "' OR '1'='1",
            "1' OR 1=1--",
            "'; DROP TABLE ay_content--",
            "' UNION SELECT 1--",
        );
    }

    /** @return string[] */
    public static function titleValid(): array
    {
        return array(
            '普通标题',
            'Hello World',
            '标题-2026',
            'a & b',
        );
    }

    /**
     * 含空白/换行的描述：输入 => 经 clear_html_blank 后应不含的片段
     *
     * @return array<string,string> input => forbidden substring after clean
     */
    public static function descriptionBlankMap(): array
    {
        return array(
            "line1\r\nline2" => "\r\n",
            "line1\nline2" => "\n",
            "a\tb" => "\t",
            'a　b' => '　',
            'a&nbsp;b' => '&nbsp;',
        );
    }

    /** 富文本正文样本（用于空描述自动摘要） */
    public static function richContentWithScript(): string
    {
        return '<p>安全摘要</p><script>alert(1)</script><p>尾部</p>';
    }
}
