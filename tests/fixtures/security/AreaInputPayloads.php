<?php

declare(strict_types=1);

/**
 * 区域编码 / 域名恶意与合法样本（仅作死字符串，禁止执行）
 */
final class AreaInputPayloads
{
    /** @return string[] */
    public static function acodeXss(): array
    {
        return array(
            '<script>alert(1)</script>',
            '"><img src=x onerror=alert(1)>',
            "cn<script>",
            'cn"onmouseover="alert(1)',
        );
    }

    /** @return string[] */
    public static function acodeSqli(): array
    {
        return array(
            "' OR '1'='1",
            "cn' OR 1=1--",
            "1; DROP TABLE ay_area--",
            "cn' UNION SELECT 1--",
            "cn OR 1=1",
        );
    }

    /** @return string[] */
    public static function acodeValid(): array
    {
        return array(
            'cn',
            'en',
            'en-us',
            'zh.CN',
            'a1',
            'area-2.0',
        );
    }

    /** @return string[] */
    public static function acodeInvalidCharset(): array
    {
        return array(
            'cn_us',
            'cn us',
            '中文',
            'cn/us',
            'cn@us',
        );
    }

    /** @return string[] */
    public static function domainXss(): array
    {
        return array(
            '<script>alert(1)</script>',
            '"><img src=x onerror=alert(1)>',
            'example.com"><script>',
            'javascript:alert(1)',
        );
    }

    /** @return string[] */
    public static function domainSqli(): array
    {
        return array(
            "' OR '1'='1",
            "example.com' OR 1=1--",
            "1; DROP TABLE ay_area--",
            "example.com' UNION SELECT 1--",
        );
    }

    /**
     * @return array<string,string> input => expected host
     */
    public static function domainValidMap(): array
    {
        return array(
            'www.example.com' => 'www.example.com',
            'https://www.example.com' => 'www.example.com',
            'http://www.example.com/' => 'www.example.com',
            'HTTPS://Example.COM' => 'example.com',
            'localhost' => 'localhost',
            'a.b-c.example.com' => 'a.b-c.example.com',
        );
    }

    /** @return string[] */
    public static function domainInvalid(): array
    {
        return array(
            'http://example.com/path',
            'example.com/admin',
            'example.com?q=1',
            'example.com#hash',
            'not_a_domain',
            '-bad.com',
            '.',
            '',
        );
    }
}
