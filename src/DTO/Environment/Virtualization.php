<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Environment;

/**
 * Represents virtualization information.
 */
final readonly class Virtualization
{
    public function __construct(
        public VirtualizationType $type,
        public ?string $vendor,
        public ?string $rawIdentifier,
    ) {}
}
