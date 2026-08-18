<?php

declare(strict_types=1);

/**
 * Imagick 安全校验 payload（仅作死字符串/字节，禁止 eval / 外网 / 真实 Imagick 调用）
 */
final class ImagickSecurityPayloads
{
    /** PDF 魔数 */
    public static function pdfHead()
    {
        return '%PDF-1.4 malicious';
    }

    /** PostScript 魔数 */
    public static function psHead()
    {
        return '%!PS-Adobe-3.0';
    }

    /** SVG 内容 */
    public static function svgHead()
    {
        return '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"></svg>';
    }

    /** MVG（ImageTragick 向量图形；需同时含 @begin 与 mvg 以匹配 imagick_detect_dangerous_magic） */
    public static function mvgHead()
    {
        return "@begin mvg\npush graphic-context\nviewbox 0 0 640 480\npop graphic-context\n@end mvg\n";
    }

    /** 最小合法 JPEG（1x1） */
    public static function minimalJpegBytes()
    {
        return base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////'
            . '2wBDAf//////////////////////////////////////////////////////////////////////////////////////'
            . 'wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k='
        );
    }

    /** HEIC ISO-BMFF ftyp 魔数（getimagesize 无法识别） */
    public static function heicHead()
    {
        return "\x00\x00\x00\x18" . 'ftyp' . 'heic' . "\x00\x00\x00\x00" . 'mif1';
    }

    /** AVIF ISO-BMFF ftyp 魔数 */
    public static function avifHead()
    {
        return "\x00\x00\x00\x20" . 'ftyp' . 'avif' . "\x00\x00\x00\x00" . 'avif' . 'mif1';
    }

    /** 截断 JPEG（仅 SOI 三字节，getimagesize 失败） */
    public static function truncatedJpegHead()
    {
        return "\xFF\xD8\xFF";
    }
}
