<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support;

/**
 * Provides system-level information and configuration.
 */
final class SystemInfo
{
    private static ?int $pageSize = null;

    /**
     * Get the system page size in bytes.
     *
     * On Linux, this is typically 4096 bytes, but can be:
     * - 16384 (16KB) on some ARM64 systems
     * - 65536 (64KB) on s390x or with hugepages
     * - 4096 (4KB) on x86_64
     *
     * @return int Page size in bytes (defaults to 4096 if detection fails)
     */
    public static function getPageSize(): int
    {
        if (self::$pageSize !== null) {
            return self::$pageSize;
        }

        // Try to get page size from getconf command
        $result = @shell_exec('getconf PAGESIZE 2>/dev/null');
        if ($result !== null && $result !== false) {
            $pageSize = (int) trim($result);
            if ($pageSize > 0) {
                self::$pageSize = $pageSize;

                return $pageSize;
            }
        }

        // Fallback to sysconf if available (requires POSIX extension)
        if (function_exists('posix_sysconf') && defined('POSIX_SC_PAGESIZE')) {
            $pageSize = @posix_sysconf(POSIX_SC_PAGESIZE);
            if ($pageSize !== false && $pageSize > 0) {
                self::$pageSize = $pageSize;

                return $pageSize;
            }
        }

        // Final fallback to common default
        self::$pageSize = 4096;

        return 4096;
    }

    /**
     * Reset cached values (useful for testing).
     */
    public static function reset(): void
    {
        self::$pageSize = null;
    }
}
