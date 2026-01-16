<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Support;

use Cbox\SystemMetrics\Contracts\ProcessRunnerInterface;

/**
 * Provides system-level information and configuration.
 *
 * Uses ProcessRunner for consistent command execution through the
 * security whitelist, even for simple system info queries.
 */
final class SystemInfo
{
    private static ?int $pageSize = null;

    private static ?ProcessRunnerInterface $processRunner = null;

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
        $runner = self::getProcessRunner();
        $result = $runner->execute('getconf PAGESIZE');
        if ($result->isSuccess()) {
            $pageSize = (int) trim($result->getValue());
            if ($pageSize > 0) {
                self::$pageSize = $pageSize;

                return $pageSize;
            }
        }

        // Fallback to sysconf if available (requires POSIX extension)
        if (function_exists('posix_sysconf') && defined('POSIX_SC_PAGESIZE')) {
            $pageSize = @posix_sysconf(POSIX_SC_PAGESIZE);
            if ($pageSize > 0) {
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
        self::$processRunner = null;
    }

    /**
     * Set a custom ProcessRunner (useful for testing).
     */
    public static function setProcessRunner(ProcessRunnerInterface $runner): void
    {
        self::$processRunner = $runner;
    }

    /**
     * Get the ProcessRunner instance.
     */
    private static function getProcessRunner(): ProcessRunnerInterface
    {
        return self::$processRunner ?? new ProcessRunner;
    }
}
