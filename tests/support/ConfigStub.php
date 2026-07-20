<?php

declare(strict_types=1);

/**
 * Config 测试桩：注入 content_iframe_whitelist，避免拉起完整 Kernel
 */
final class ConfigStub
{
    private static function configProperty(): ReflectionProperty
    {
        require_once CORE_PATH . '/basic/Config.php';

        $ref = new ReflectionClass(\core\basic\Config::class);
        $prop = $ref->getProperty('configs');
        if (PHP_VERSION_ID < 80100) {
            $prop->setAccessible(true);
        }

        return $prop;
    }

    /** 恢复 Config 未初始化状态，避免 run.php 多文件同进程泄漏 */
    public static function reset()
    {
        if (! class_exists(\core\basic\Config::class, false)) {
            return;
        }
        self::configProperty()->setValue(null, null);
    }

    /** @param string[] $hosts */
    public static function setIframeWhitelist(array $hosts)
    {
        $prop = self::configProperty();
        $configs = $prop->getValue(null);
        if (! is_array($configs)) {
            $configs = array();
        }
        $configs['content_iframe_whitelist'] = implode(',', $hosts);
        $prop->setValue(null, $configs);
    }

    public static function clearIframeWhitelist()
    {
        self::setIframeWhitelist(array());
    }

    /** @param array<string, mixed> $configs */
    public static function set(array $configs)
    {
        $prop = self::configProperty();
        $current = $prop->getValue(null);
        if (! is_array($current)) {
            $current = array();
        }
        $prop->setValue(null, array_merge($current, $configs));
    }
}
