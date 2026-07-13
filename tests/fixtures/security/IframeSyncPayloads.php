<?php

declare(strict_types=1);

/**
 * 保存加白管线用内容样本（死字符串，无外网请求）
 */
final class IframeSyncPayloads
{
    public static function playerExample(): string
    {
        return '<iframe src="https://player.example.com/video/1"></iframe>';
    }

    public static function subExample(): string
    {
        return '<iframe src="https://sub.example.com/v"></iframe>';
    }

    public static function github(): string
    {
        return '<iframe src="https://github.com/"></iframe>';
    }

    public static function noIframe(): string
    {
        return '<p>plain text without iframe</p>';
    }

    /** 模拟 post() 转义后的入库串 */
    public static function escapedPlayer(): string
    {
        $raw = self::playerExample();
        return addslashes(htmlspecialchars(trim($raw), ENT_QUOTES, 'UTF-8'));
    }
}
