<?php

declare(strict_types=1);

/**
 * iframe 白名单保存管线测试夹具：临时 RUN_PATH、生产依赖加载
 */
final class IframeSyncHarness
{
    /** @var bool */
    private static $depsLoaded = false;

    public static function loadDeps()
    {
        if (self::$depsLoaded) {
            return;
        }

        if (! defined('APP_PATH')) {
            define('APP_PATH', ROOT_PATH . 'apps');
        }
        if (! defined('RUN_PATH')) {
            define('RUN_PATH', TEST_ROOT . 'tmp' . DIRECTORY_SEPARATOR . 'run');
        }

        require_once CORE_PATH . '/function/file.php';

        // PHP 8.4+ 会在加载旧版 Model 签名时报告隐式 nullable 弃用；
        // 这里只兼容加载期 deprecation，测试中的 warning/notice 仍由 TestAssert 转为异常。
        $previousErrorReporting = error_reporting();
        error_reporting($previousErrorReporting & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        try {
            require_once CORE_PATH . '/basic/Model.php';
        } finally {
            error_reporting($previousErrorReporting);
        }

        require_once APP_PATH . '/admin/model/system/ConfigModel.php';
        require_once APP_PATH . '/common/function.php';
        require_once TEST_ROOT . 'support/ConfigModelTestDouble.php';

        self::$depsLoaded = true;
    }

    public static function ensureConfigDir(): string
    {
        self::loadDeps();
        $dir = RUN_PATH . '/config';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    /** 预置假配置缓存文件，返回绝对路径 */
    public static function seedConfigCache(string $filename = 'sys.php'): string
    {
        $dir = self::ensureConfigDir();
        $path = $dir . '/' . $filename;
        file_put_contents($path, "<?php\nreturn array();\n");
        return $path;
    }

    public static function cleanupRunPath()
    {
        if (! defined('RUN_PATH') || ! is_dir(RUN_PATH)) {
            return;
        }
        self::rmTree(RUN_PATH);
    }

    public static function iframeHtml(string $host, string $path = '/embed'): string
    {
        return '<iframe src="https://' . $host . $path . '"></iframe>';
    }

    private static function rmTree(string $path)
    {
        if (! file_exists($path)) {
            return;
        }
        if (! is_dir($path)) {
            @unlink($path);
            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            self::rmTree($path . DIRECTORY_SEPARATOR . $item);
        }
        @rmdir($path);
    }
}
