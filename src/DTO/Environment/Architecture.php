<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Environment;

/**
 * Represents CPU architecture information.
 */
final readonly class Architecture
{
    public function __construct(
        public ArchitectureKind $kind,
        public string $raw,
    ) {}
}
