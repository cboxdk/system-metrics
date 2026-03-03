<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Testing;

use Cbox\SystemMetrics\Config\SystemMetricsConfig;
use Cbox\SystemMetrics\SystemMetrics;

/**
 * Test helper that installs fake sources into SystemMetricsConfig.
 *
 * Usage in tests:
 *
 *     $fakes = FakeSystemMetrics::install();
 *
 *     // All SystemMetrics calls now return predictable fake data
 *     $result = SystemMetrics::overview();
 *
 *     // Customize specific metrics:
 *     $fakes->loadAverage->set(new LoadAverageSnapshot(5.0, 3.0, 1.0));
 *
 *     // Simulate failures:
 *     $fakes->memory->failWith(new SystemMetricsException('out of memory'));
 *
 *     // Simulate container environment:
 *     $fakes->container->asContainer(cpuQuota: 2.0, memoryLimitBytes: 4_294_967_296);
 *
 *     // Clean up:
 *     FakeSystemMetrics::uninstall();
 */
final class FakeSystemMetrics
{
    public function __construct(
        public readonly FakeEnvironmentDetector $environment,
        public readonly FakeCpuMetricsSource $cpu,
        public readonly FakeMemoryMetricsSource $memory,
        public readonly FakeLoadAverageSource $loadAverage,
        public readonly FakeStorageMetricsSource $storage,
        public readonly FakeNetworkMetricsSource $network,
        public readonly FakeUptimeSource $uptime,
        public readonly FakeContainerMetricsSource $container,
        public readonly FakeSystemLimitsSource $limits,
    ) {}

    /**
     * Install all fake sources into SystemMetricsConfig.
     *
     * Returns the FakeSystemMetrics instance so you can customize individual fakes.
     */
    public static function install(): self
    {
        $fakes = new self(
            environment: new FakeEnvironmentDetector,
            cpu: new FakeCpuMetricsSource,
            memory: new FakeMemoryMetricsSource,
            loadAverage: new FakeLoadAverageSource,
            storage: new FakeStorageMetricsSource,
            network: new FakeNetworkMetricsSource,
            uptime: new FakeUptimeSource,
            container: new FakeContainerMetricsSource,
            limits: new FakeSystemLimitsSource,
        );

        SystemMetricsConfig::setEnvironmentDetector($fakes->environment);
        SystemMetricsConfig::setCpuMetricsSource($fakes->cpu);
        SystemMetricsConfig::setMemoryMetricsSource($fakes->memory);
        SystemMetricsConfig::setLoadAverageSource($fakes->loadAverage);
        SystemMetricsConfig::setStorageMetricsSource($fakes->storage);
        SystemMetricsConfig::setNetworkMetricsSource($fakes->network);
        SystemMetricsConfig::setUptimeSource($fakes->uptime);
        SystemMetricsConfig::setContainerMetricsSource($fakes->container);
        SystemMetricsConfig::setSystemLimitsSource($fakes->limits);

        // Clear environment cache so fakes take effect
        SystemMetrics::clearEnvironmentCache();

        return $fakes;
    }

    /**
     * Uninstall all fakes and reset to real implementations.
     */
    public static function uninstall(): void
    {
        SystemMetricsConfig::reset();
        SystemMetrics::clearEnvironmentCache();
    }
}
