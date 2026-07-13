<?php

declare(strict_types=1);

/**
 * 原生测试断言（全局唯一 API）
 */
final class TestAssert
{
    /** @var int */
    private static $failed = 0;

    public static function reset()
    {
        self::$failed = 0;
    }

    public static function exitCode(): int
    {
        return self::$failed > 0 ? 1 : 0;
    }

    public static function true($cond, string $msg)
    {
        if ($cond) {
            echo "OK: $msg\n";
        } else {
            echo "FAIL: $msg\n";
            self::$failed++;
        }
    }

    public static function false($cond, string $msg)
    {
        self::true(!$cond, $msg);
    }

    public static function same($expected, $actual, string $msg)
    {
        self::true(
            $expected === $actual,
            $msg . ' (got: ' . var_export($actual, true) . ')'
        );
    }

    public static function contains(string $haystack, string $needle, string $msg)
    {
        self::true(strpos($haystack, $needle) !== false, $msg);
    }

    public static function notContains(string $haystack, string $needle, string $msg)
    {
        self::true(strpos($haystack, $needle) === false, $msg);
    }

    /**
     * @param callable $fn
     */
    public static function runSuite(callable $fn): int
    {
        self::reset();
        set_error_handler(static function ($errno, $errstr, $errfile, $errline) {
            if (!(error_reporting() & $errno)) {
                return false;
            }
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
        try {
            $fn();
        } finally {
            restore_error_handler();
        }
        return self::exitCode();
    }
}
