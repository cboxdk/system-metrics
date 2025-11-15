<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support;

/**
 * Simple runtime OS detection helper.
 */
final class OsDetector
{
    /**
     * Check if the current OS is Linux.
     */
    public static function isLinux(): bool
    {
        return PHP_OS_FAMILY === 'Linux';
    }

    /**
     * Check if the current OS is macOS.
     */
    public static function isMacOs(): bool
    {
        return PHP_OS_FAMILY === 'Darwin';
    }

    /**
     * Check if the current OS is Windows.
     */
    public static function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    /**
     * Get the OS family string.
     */
    public static function getFamily(): string
    {
        return PHP_OS_FAMILY;
    }

    /**
     * Check if the current OS is supported (Linux or macOS).
     */
    public static function isSupported(): bool
    {
        return self::isLinux() || self::isMacOs();
    }
}
