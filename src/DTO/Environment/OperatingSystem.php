<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Environment;

/**
 * Represents operating system information.
 */
final readonly class OperatingSystem
{
    public function __construct(
        public OsFamily $family,
        public string $name,
        public string $version,
    ) {}
}
