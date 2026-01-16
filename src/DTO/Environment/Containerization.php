<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Environment;

/**
 * Represents containerization information.
 */
final readonly class Containerization
{
    public function __construct(
        public ContainerType $type,
        public ?string $runtime,
        public bool $insideContainer,
        public ?string $rawIdentifier,
    ) {}
}
