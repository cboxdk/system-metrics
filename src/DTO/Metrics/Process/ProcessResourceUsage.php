<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Metrics\Process;

use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;

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
