<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Exceptions;

/**
 * Thrown when a required system file does not exist.
 */
final class FileNotFoundException extends SystemMetricsException
{
    public static function forPath(string $path): self
    {
        return new self("File not found: {$path}");
    }
}
