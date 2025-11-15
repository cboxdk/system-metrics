<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Exceptions;

/**
 * Thrown when the process lacks necessary permissions to read system metrics.
 */
final class InsufficientPermissionsException extends SystemMetricsException
{
    public static function forFile(string $path): self
    {
        return new self("Insufficient permissions to read: {$path}");
    }

    public static function forCommand(string $command): self
    {
        return new self("Insufficient permissions to execute: {$command}");
    }
}
