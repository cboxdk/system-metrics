<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO;

use Cbox\SystemMetrics\DTO\Environment\EnvironmentSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;

/**
 * Complete overview of the system combining environment, CPU, memory, storage, and network.
 */
final readonly class SystemOverview
{
    public function __construct(
        public EnvironmentSnapshot $environment,
        public CpuSnapshot $cpu,
        public MemorySnapshot $memory,
        public StorageSnapshot $storage,
        public NetworkSnapshot $network,
    ) {}
}
