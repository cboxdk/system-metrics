<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Environment;

/**
 * Represents kernel information.
 */
final readonly class Kernel
{
    public function __construct(
        public string $release,
        public string $version,
    ) {}
}
