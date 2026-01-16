<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Support;

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
     * Check if the current OS is FreeBSD.
     */
    public static function isFreeBSD(): bool
    {
        return PHP_OS_FAMILY === 'BSD' && stripos(php_uname('s'), 'freebsd') !== false;
    }

    /**
     * Check if the current OS is OpenBSD.
     */
    public static function isOpenBSD(): bool
    {
        return PHP_OS_FAMILY === 'BSD' && stripos(php_uname('s'), 'openbsd') !== false;
    }

    /**
     * Check if the current OS is NetBSD.
     */
    public static function isNetBSD(): bool
    {
        return PHP_OS_FAMILY === 'BSD' && stripos(php_uname('s'), 'netbsd') !== false;
    }

    /**
     * Check if the current OS is any BSD variant.
     */
    public static function isBSD(): bool
    {
        return PHP_OS_FAMILY === 'BSD';
    }

    /**
     * Get the OS family string.
     */
    public static function getFamily(): string
    {
        return PHP_OS_FAMILY;
    }

    /**
     * Check if the current OS is supported (Linux, macOS, Windows, or BSD).
     */
    public static function isSupported(): bool
    {
        return self::isLinux() || self::isMacOs() || self::isWindows() || self::isBSD();
    }
}
