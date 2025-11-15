<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO;

use PHPeek\SystemMetrics\DTO\Environment\EnvironmentSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;

/**
 * Complete overview of the system combining environment, CPU, and memory.
 */
final readonly class SystemOverview
{
    public function __construct(
        public EnvironmentSnapshot $environment,
        public CpuSnapshot $cpu,
        public MemorySnapshot $memory,
    ) {}
}
