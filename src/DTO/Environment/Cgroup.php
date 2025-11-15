<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Environment;

/**
 * Represents cgroup information.
 */
final readonly class Cgroup
{
    public function __construct(
        public CgroupVersion $version,
        public ?string $cpuPath,
        public ?string $memoryPath,
    ) {}
}
