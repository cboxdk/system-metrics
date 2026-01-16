<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Environment;

/**
 * Represents virtualization information.
 */
final readonly class Virtualization
{
    public function __construct(
        public VirtualizationType $type,
        public VirtualizationVendor $vendor,
        public ?string $rawIdentifier,
    ) {}
}
