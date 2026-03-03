<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO;

use Cbox\SystemMetrics\DTO\Environment\EnvironmentSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Container\ContainerLimits;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\LoadAverageSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\SystemLimits;
use Cbox\SystemMetrics\DTO\Metrics\UptimeSnapshot;

/**
 * Complete overview of the system combining all available metrics.
 *
 * Core metrics (environment, cpu, memory) are always present.
 * Optional metrics (storage, network, loadAverage, uptime, limits, container)
 * are nullable and will be null if unavailable on the current platform.
 */
final readonly class SystemOverview
{
    public function __construct(
        public EnvironmentSnapshot $environment,
        public CpuSnapshot $cpu,
        public MemorySnapshot $memory,
        public ?StorageSnapshot $storage = null,
        public ?NetworkSnapshot $network = null,
        public ?LoadAverageSnapshot $loadAverage = null,
        public ?UptimeSnapshot $uptime = null,
        public ?SystemLimits $limits = null,
        public ?ContainerLimits $container = null,
    ) {}
}
