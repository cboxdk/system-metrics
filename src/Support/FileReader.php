<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Support;

use Cbox\SystemMetrics\Contracts\FileReaderInterface;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\FileNotFoundException;
use Cbox\SystemMetrics\Exceptions\InsufficientPermissionsException;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Reads system files with proper error handling.
 *
 * Security Model:
 * - All file paths are hardcoded in source code (no user input)
 * - Path validation ensures only whitelisted directories can be accessed
 * - Uses realpath() to resolve symlinks and prevent traversal
 * - Read-only operations only (no write/modify operations)
 */
final class FileReader implements FileReaderInterface
{
    /**
     * Whitelist of allowed path prefixes for security.
     *
     * This prevents directory traversal even if user input somehow
     * makes it into the read() method in future development.
     *
     * @var array<string>
     */
    private const ALLOWED_PATH_PREFIXES = [
        // Linux system paths
        '/proc/',
        '/sys/',
        '/etc/os-release',
        '/etc/lsb-release',
        '/etc/debian_version',
        '/etc/redhat-release',
        '/etc/system-release',

        // macOS system paths (if needed in future)
        '/System/',
        '/Library/',

        // Test paths (for unit tests) - Unix
        '/tmp/',
        '/var/folders/',  // macOS temp (symlinked)
        '/private/var/folders/',  // macOS temp (real path)
        '/private/tmp/',  // macOS temp alternative
    ];

    /**
     * Windows-specific allowed path prefixes.
     *
     * Stored separately because Windows paths use different format.
     *
     * @var array<string>
     */
    private const WINDOWS_ALLOWED_PATH_PREFIXES = [
        // Windows system paths
        'C:\\Windows\\',

        // Windows temp paths (for unit tests)
        'C:\\Users\\',       // User temp directories
        'C:\\Temp\\',        // System temp
        'D:\\a\\',           // GitHub Actions workspace
        'D:\\Temp\\',        // Alternative temp location
    ];

    /**
     * Read the entire contents of a file.
     *
     * @return Result<string>
     */
    public function read(string $path): Result
    {
        // Validate path is whitelisted
        if (! $this->isPathAllowed($path)) {
            /** @var Result<string> */
            return Result::failure(
                new SystemMetricsException(
                    "Path not whitelisted for security: {$path}"
                )
            );
        }

        // Resolve real path to prevent symlink attacks
        $realPath = @realpath($path);
        if ($realPath === false) {
            // If realpath fails, check if file exists for better error message
            if (! file_exists($path)) {
                /** @var Result<string> */
                return Result::failure(FileNotFoundException::forPath($path));
            }

            /** @var Result<string> */
            return Result::failure(InsufficientPermissionsException::forFile($path));
        }

        // Re-validate resolved path is still whitelisted
        if (! $this->isPathAllowed($realPath)) {
            /** @var Result<string> */
            return Result::failure(
                new SystemMetricsException(
                    "Resolved path not whitelisted for security: {$realPath}"
                )
            );
        }

        if (! is_readable($realPath)) {
            /** @var Result<string> */
            return Result::failure(InsufficientPermissionsException::forFile($path));
        }

        $contents = @file_get_contents($realPath);

        if ($contents === false) {
            /** @var Result<string> */
            return Result::failure(InsufficientPermissionsException::forFile($path));
        }

        return Result::success($contents);
    }

    /**
     * Read a file and return it as an array of lines.
     *
     * @return Result<list<string>>
     */
    public function readLines(string $path): Result
    {
        return $this->read($path)->map(function (string $contents): array {
            return array_values(array_filter(
                explode("\n", $contents),
                fn (string $line) => $line !== ''
            ));
        });
    }

    /**
     * Check if a file exists and is readable.
     */
    public function exists(string $path): bool
    {
        return file_exists($path) && is_readable($path);
    }

    /**
     * Check if a path is whitelisted for reading.
     *
     * Paths must start with one of the allowed path prefixes
     * to prevent arbitrary file system access.
     */
    private function isPathAllowed(string $path): bool
    {
        // Check Unix-style paths
        foreach (self::ALLOWED_PATH_PREFIXES as $allowedPrefix) {
            if (str_starts_with($path, $allowedPrefix)) {
                return true;
            }
        }

        // Check Windows-style paths (case-insensitive)
        if (PHP_OS_FAMILY === 'Windows') {
            $normalizedPath = str_replace('/', '\\', $path);
            foreach (self::WINDOWS_ALLOWED_PATH_PREFIXES as $allowedPrefix) {
                if (stripos($normalizedPath, $allowedPrefix) === 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
