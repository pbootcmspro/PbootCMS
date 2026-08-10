<?php

declare(strict_types=1);

/**
 * 站点统计代码样本（死字符串；可信管理员配置后应完整输出）
 */
final class StatisticalSanitizePayloads
{
    public static function baiduInline(): string
    {
        return '<script>'
            . 'var _hmt = _hmt || [];'
            . '(function() {'
            . 'var hm = document.createElement("script");'
            . 'hm.src = "https://hm.baidu.com/hm.js?abcdef0123456789abcdef0123456789";'
            . 'var s = document.getElementsByTagName("script")[0];'
            . 's.parentNode.insertBefore(hm, s);'
            . '})();'
            . '</script>';
    }

    public static function baiduExternal(): string
    {
        return '<script src="https://hm.baidu.com/hm.js?abcdef0123456789abcdef0123456789"></script>';
    }

    public static function cnzzExternal(): string
    {
        return '<script src="https://s4.cnzz.com/z_stat.php?id=123456&web_id=123456" language="JavaScript"></script>';
    }

    /** 常见 CNZZ 内联（document.write + unescape） */
    public static function cnzzInlineConcat(): string
    {
        return '<script type="text/javascript">'
            . 'var cnzz_protocol = (("https:" == document.location.protocol) ? " https://" : " http://");'
            . 'document.write(unescape("%3Cspan id=\'cnzz_stat_icon_123\'%3E%3C/span%3E%3Cscript src=\'" '
            . '+ cnzz_protocol + "s23.cnzz.com/z_stat.php%3Fid%3D123\' type=\'text/javascript\'%3E%3C/script%3E"));'
            . '</script>';
    }

    public static function withPhpOpenTag(): string
    {
        return '<script>var x=1;</script><?php echo 1;?>';
    }
}
