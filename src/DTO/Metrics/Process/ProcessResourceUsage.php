<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Metrics\Process;

use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;

/**
 * Resource usage for a single process or process group at a point in time.
 */
final readonly class ProcessResourceUsage
{
    public function __construct(
        public CpuTimes $cpuTimes,
        public int $memoryRssBytes,
        public int $memoryVmsBytes,
        public int $threadCount,
        public int $openFileDescriptors,
        public int $processCount = 1,
    ) {}
}
