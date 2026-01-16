<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Exceptions;

/**
 * Thrown when attempting to read metrics on an unsupported operating system.
 */
final class UnsupportedOperatingSystemException extends SystemMetricsException
{
    public static function forOs(string $osFamily): self
    {
        return new self("Unsupported operating system: {$osFamily}");
    }
}
