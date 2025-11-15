<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support;

use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\FileNotFoundException;
use PHPeek\SystemMetrics\Exceptions\InsufficientPermissionsException;

/**
 * Reads system files with proper error handling.
 */
final class FileReader
{
    /**
     * Read the entire contents of a file.
     *
     * @return Result<string>
     */
    public function read(string $path): Result
    {
        if (! file_exists($path)) {
            return Result::failure(FileNotFoundException::forPath($path));
        }

        if (! is_readable($path)) {
            return Result::failure(InsufficientPermissionsException::forFile($path));
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return Result::failure(InsufficientPermissionsException::forFile($path));
        }

        return Result::success($contents);
    }

    /**
     * Read a file and return it as an array of lines.
     *
     * @return Result<array<string>>
     */
    public function readLines(string $path): Result
    {
        return $this->read($path)->map(function (string $contents): array {
            return array_filter(
                explode("\n", $contents),
                fn (string $line) => $line !== ''
            );
        });
    }

    /**
     * Check if a file exists and is readable.
     */
    public function exists(string $path): bool
    {
        return file_exists($path) && is_readable($path);
    }
}
