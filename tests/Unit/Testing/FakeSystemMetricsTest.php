<?php

use Cbox\SystemMetrics\Config\SystemMetricsConfig;
use Cbox\SystemMetrics\DTO\Metrics\Container\CgroupVersion;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use Cbox\SystemMetrics\DTO\Metrics\LoadAverageSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\SystemLimits;
use Cbox\SystemMetrics\DTO\Metrics\UptimeSnapshot;
use Cbox\SystemMetrics\SystemMetrics;
use Cbox\SystemMetrics\Testing\FakeContainerMetricsSource;
use Cbox\SystemMetrics\Testing\FakeCpuMetricsSource;
use Cbox\SystemMetrics\Testing\FakeEnvironmentDetector;
use Cbox\SystemMetrics\Testing\FakeLoadAverageSource;
use Cbox\SystemMetrics\Testing\FakeMemoryMetricsSource;
use Cbox\SystemMetrics\Testing\FakeNetworkMetricsSource;
use Cbox\SystemMetrics\Testing\FakeStorageMetricsSource;
use Cbox\SystemMetrics\Testing\FakeSystemLimitsSource;
use Cbox\SystemMetrics\Testing\FakeSystemMetrics;
use Cbox\SystemMetrics\Testing\FakeUptimeSource;

afterEach(function () {
    FakeSystemMetrics::uninstall();
});

describe('FakeSystemMetrics', function () {
    it('installs all fake sources into SystemMetricsConfig', function () {
        $fakes = FakeSystemMetrics::install();

        expect(SystemMetricsConfig::getEnvironmentDetector())->toBeInstanceOf(FakeEnvironmentDetector::class);
        expect(SystemMetricsConfig::getCpuMetricsSource())->toBeInstanceOf(FakeCpuMetricsSource::class);
        expect(SystemMetricsConfig::getMemoryMetricsSource())->toBeInstanceOf(FakeMemoryMetricsSource::class);
        expect(SystemMetricsConfig::getLoadAverageSource())->toBeInstanceOf(FakeLoadAverageSource::class);
        expect(SystemMetricsConfig::getStorageMetricsSource())->toBeInstanceOf(FakeStorageMetricsSource::class);
        expect(SystemMetricsConfig::getNetworkMetricsSource())->toBeInstanceOf(FakeNetworkMetricsSource::class);
        expect(SystemMetricsConfig::getUptimeSource())->toBeInstanceOf(FakeUptimeSource::class);
        expect(SystemMetricsConfig::getContainerMetricsSource())->toBeInstanceOf(FakeContainerMetricsSource::class);
        expect(SystemMetricsConfig::getSystemLimitsSource())->toBeInstanceOf(FakeSystemLimitsSource::class);
    });

    it('returns instance with accessible fake sources', function () {
        $fakes = FakeSystemMetrics::install();

        expect($fakes->environment)->toBeInstanceOf(FakeEnvironmentDetector::class);
        expect($fakes->cpu)->toBeInstanceOf(FakeCpuMetricsSource::class);
        expect($fakes->memory)->toBeInstanceOf(FakeMemoryMetricsSource::class);
        expect($fakes->loadAverage)->toBeInstanceOf(FakeLoadAverageSource::class);
        expect($fakes->storage)->toBeInstanceOf(FakeStorageMetricsSource::class);
        expect($fakes->network)->toBeInstanceOf(FakeNetworkMetricsSource::class);
        expect($fakes->uptime)->toBeInstanceOf(FakeUptimeSource::class);
        expect($fakes->container)->toBeInstanceOf(FakeContainerMetricsSource::class);
        expect($fakes->limits)->toBeInstanceOf(FakeSystemLimitsSource::class);
    });

    it('uninstall resets all sources to defaults', function () {
        FakeSystemMetrics::install();
        FakeSystemMetrics::uninstall();

        // After uninstall, Config should return Composite (real) sources, not fakes
        expect(SystemMetricsConfig::getLoadAverageSource())->not->toBeInstanceOf(FakeLoadAverageSource::class);
        expect(SystemMetricsConfig::getStorageMetricsSource())->not->toBeInstanceOf(FakeStorageMetricsSource::class);
        expect(SystemMetricsConfig::getNetworkMetricsSource())->not->toBeInstanceOf(FakeNetworkMetricsSource::class);
        expect(SystemMetricsConfig::getUptimeSource())->not->toBeInstanceOf(FakeUptimeSource::class);
        expect(SystemMetricsConfig::getContainerMetricsSource())->not->toBeInstanceOf(FakeContainerMetricsSource::class);
        expect(SystemMetricsConfig::getSystemLimitsSource())->not->toBeInstanceOf(FakeSystemLimitsSource::class);
    });
});

describe('SystemMetrics facade with fakes', function () {
    it('returns fake environment data', function () {
        FakeSystemMetrics::install();

        $result = SystemMetrics::environment();

        expect($result->isSuccess())->toBeTrue();
        $env = $result->getValue();
        expect($env->os->name)->toBe('Ubuntu');
        expect($env->os->version)->toBe('22.04');
    });

    it('returns fake CPU data', function () {
        FakeSystemMetrics::install();

        $result = SystemMetrics::cpu();

        expect($result->isSuccess())->toBeTrue();
        $cpu = $result->getValue();
        expect($cpu)->toBeInstanceOf(CpuSnapshot::class);
        expect($cpu->coreCount())->toBe(4);
    });

    it('returns fake memory data', function () {
        FakeSystemMetrics::install();

        $result = SystemMetrics::memory();

        expect($result->isSuccess())->toBeTrue();
        $memory = $result->getValue();
        expect($memory)->toBeInstanceOf(MemorySnapshot::class);
        expect($memory->totalBytes)->toBe(8_589_934_592);
    });

    it('returns fake load average data', function () {
        FakeSystemMetrics::install();

        $result = SystemMetrics::loadAverage();

        expect($result->isSuccess())->toBeTrue();
        $load = $result->getValue();
        expect($load)->toBeInstanceOf(LoadAverageSnapshot::class);
        expect($load->oneMinute)->toBe(0.5);
        expect($load->fiveMinutes)->toBe(0.3);
        expect($load->fifteenMinutes)->toBe(0.2);
    });

    it('returns fake storage data', function () {
        FakeSystemMetrics::install();

        $result = SystemMetrics::storage();

        expect($result->isSuccess())->toBeTrue();
        $storage = $result->getValue();
        expect($storage)->toBeInstanceOf(StorageSnapshot::class);
        expect($storage->mountPoints)->toHaveCount(1);
        expect($storage->mountPoints[0]->mountPoint)->toBe('/');
    });

    it('returns fake network data', function () {
        FakeSystemMetrics::install();

        $result = SystemMetrics::network();

        expect($result->isSuccess())->toBeTrue();
        $network = $result->getValue();
        expect($network)->toBeInstanceOf(NetworkSnapshot::class);
        expect($network->interfaces)->toHaveCount(1);
        expect($network->interfaces[0]->name)->toBe('eth0');
    });

    it('returns fake uptime data', function () {
        FakeSystemMetrics::install();

        $result = SystemMetrics::uptime();

        expect($result->isSuccess())->toBeTrue();
        $uptime = $result->getValue();
        expect($uptime)->toBeInstanceOf(UptimeSnapshot::class);
        expect($uptime->totalSeconds)->toBe(86400);
    });

    it('returns fake system limits data', function () {
        FakeSystemMetrics::install();

        $result = SystemMetrics::limits();

        expect($result->isSuccess())->toBeTrue();
        $limits = $result->getValue();
        expect($limits)->toBeInstanceOf(SystemLimits::class);
        expect($limits->cpuCores)->toBe(4);
        expect($limits->memoryBytes)->toBe(8_589_934_592);
    });

    it('returns failure for container by default (non-containerized)', function () {
        FakeSystemMetrics::install();

        $result = SystemMetrics::container();

        expect($result->isFailure())->toBeTrue();
    });

    it('returns complete overview with all fake data', function () {
        FakeSystemMetrics::install();

        $result = SystemMetrics::overview();

        expect($result->isSuccess())->toBeTrue();
        $overview = $result->getValue();
        expect($overview->environment->os->name)->toBe('Ubuntu');
        expect($overview->cpu->coreCount())->toBe(4);
        expect($overview->memory->totalBytes)->toBe(8_589_934_592);
        expect($overview->loadAverage)->not->toBeNull();
        expect($overview->loadAverage->oneMinute)->toBe(0.5);
        expect($overview->storage)->not->toBeNull();
        expect($overview->network)->not->toBeNull();
        expect($overview->uptime)->not->toBeNull();
        expect($overview->limits)->not->toBeNull();
        expect($overview->container)->toBeNull(); // Default: not containerized
    });
});

describe('Fake source customization', function () {
    it('allows setting custom load average', function () {
        $fakes = FakeSystemMetrics::install();

        $custom = new LoadAverageSnapshot(
            oneMinute: 8.5,
            fiveMinutes: 6.0,
            fifteenMinutes: 4.0,
        );
        $fakes->loadAverage->set($custom);

        $result = SystemMetrics::loadAverage();
        expect($result->getValue()->oneMinute)->toBe(8.5);
    });

    it('allows setting custom CPU data', function () {
        $fakes = FakeSystemMetrics::install();

        $custom = new CpuSnapshot(
            total: new CpuTimes(
                user: 100, nice: 0, system: 50, idle: 200,
                iowait: 0, irq: 0, softirq: 0, steal: 0,
            ),
            perCore: [],
        );
        $fakes->cpu->set($custom);

        $result = SystemMetrics::cpu();
        expect($result->getValue()->coreCount())->toBe(0);
        expect($result->getValue()->total->user)->toBe(100);
    });

    it('allows setting custom memory data', function () {
        $fakes = FakeSystemMetrics::install();

        $custom = new MemorySnapshot(
            totalBytes: 16_000_000_000,
            freeBytes: 8_000_000_000,
            availableBytes: 10_000_000_000,
            usedBytes: 6_000_000_000,
            buffersBytes: 1_000_000_000,
            cachedBytes: 3_000_000_000,
            swapTotalBytes: 4_000_000_000,
            swapFreeBytes: 3_000_000_000,
            swapUsedBytes: 1_000_000_000,
        );
        $fakes->memory->set($custom);

        $result = SystemMetrics::memory();
        expect($result->getValue()->totalBytes)->toBe(16_000_000_000);
    });

    it('allows simulating container environment', function () {
        $fakes = FakeSystemMetrics::install();

        $fakes->container->asContainer(
            cpuQuota: 4.0,
            memoryLimitBytes: 8_589_934_592,
        );

        $result = SystemMetrics::container();
        expect($result->isSuccess())->toBeTrue();

        $container = $result->getValue();
        expect($container->cgroupVersion)->toBe(CgroupVersion::V2);
        expect($container->cpuQuota)->toBe(4.0);
        expect($container->memoryLimitBytes)->toBe(8_589_934_592);
        expect($container->cpuUsageCores)->toBe(2.0); // 50% of quota
        expect($container->memoryUsageBytes)->toBe(5_153_960_755); // 60% of limit
    });

    it('container appears in overview when configured', function () {
        $fakes = FakeSystemMetrics::install();
        $fakes->container->asContainer();

        $result = SystemMetrics::overview();
        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue()->container)->not->toBeNull();
        expect($result->getValue()->container->cgroupVersion)->toBe(CgroupVersion::V2);
    });
});

describe('Fake source failure simulation', function () {
    it('simulates load average failure', function () {
        $fakes = FakeSystemMetrics::install();
        $fakes->loadAverage->failWith(
            new \Cbox\SystemMetrics\Exceptions\SystemMetricsException('Load average unavailable')
        );

        $result = SystemMetrics::loadAverage();
        expect($result->isFailure())->toBeTrue();
        expect($result->getError()->getMessage())->toBe('Load average unavailable');
    });

    it('simulates memory failure', function () {
        $fakes = FakeSystemMetrics::install();
        $fakes->memory->failWith(
            new \Cbox\SystemMetrics\Exceptions\SystemMetricsException('Memory read failed')
        );

        $result = SystemMetrics::memory();
        expect($result->isFailure())->toBeTrue();
    });

    it('simulates CPU failure', function () {
        $fakes = FakeSystemMetrics::install();
        $fakes->cpu->failWith(
            new \Cbox\SystemMetrics\Exceptions\SystemMetricsException('CPU read failed')
        );

        $result = SystemMetrics::cpu();
        expect($result->isFailure())->toBeTrue();
    });

    it('simulates storage failure', function () {
        $fakes = FakeSystemMetrics::install();
        $fakes->storage->failWith(
            new \Cbox\SystemMetrics\Exceptions\SystemMetricsException('Storage read failed')
        );

        $result = SystemMetrics::storage();
        expect($result->isFailure())->toBeTrue();
    });

    it('simulates network failure', function () {
        $fakes = FakeSystemMetrics::install();
        $fakes->network->failWith(
            new \Cbox\SystemMetrics\Exceptions\SystemMetricsException('Network read failed')
        );

        $result = SystemMetrics::network();
        expect($result->isFailure())->toBeTrue();
    });

    it('simulates uptime failure', function () {
        $fakes = FakeSystemMetrics::install();
        $fakes->uptime->failWith(
            new \Cbox\SystemMetrics\Exceptions\SystemMetricsException('Uptime read failed')
        );

        $result = SystemMetrics::uptime();
        expect($result->isFailure())->toBeTrue();
    });

    it('simulates system limits failure', function () {
        $fakes = FakeSystemMetrics::install();
        $fakes->limits->failWith(
            new \Cbox\SystemMetrics\Exceptions\SystemMetricsException('Limits read failed')
        );

        $result = SystemMetrics::limits();
        expect($result->isFailure())->toBeTrue();
    });

    it('simulates environment failure causes overview failure', function () {
        $fakes = FakeSystemMetrics::install();
        $fakes->environment->failWith(
            new \Cbox\SystemMetrics\Exceptions\SystemMetricsException('Environment detection failed')
        );

        $result = SystemMetrics::overview();
        expect($result->isFailure())->toBeTrue();
    });

    it('optional metric failures degrade gracefully in overview', function () {
        $fakes = FakeSystemMetrics::install();
        $fakes->storage->failWith(
            new \Cbox\SystemMetrics\Exceptions\SystemMetricsException('Storage failed')
        );
        $fakes->network->failWith(
            new \Cbox\SystemMetrics\Exceptions\SystemMetricsException('Network failed')
        );

        $result = SystemMetrics::overview();
        expect($result->isSuccess())->toBeTrue();
        $overview = $result->getValue();
        expect($overview->storage)->toBeNull();
        expect($overview->network)->toBeNull();
        // Core metrics still present
        expect($overview->cpu)->not->toBeNull();
        expect($overview->memory)->not->toBeNull();
    });
});

describe('Fake source defaults', function () {
    it('FakeLoadAverageSource has sensible defaults', function () {
        $default = FakeLoadAverageSource::default();

        expect($default->oneMinute)->toBe(0.5);
        expect($default->fiveMinutes)->toBe(0.3);
        expect($default->fifteenMinutes)->toBe(0.2);
    });

    it('FakeCpuMetricsSource defaults to 4 cores', function () {
        $default = FakeCpuMetricsSource::default();

        expect($default->coreCount())->toBe(4);
        expect($default->total->user)->toBe(4000);
    });

    it('FakeCpuMetricsSource accepts custom core count', function () {
        $default = FakeCpuMetricsSource::default(cores: 8);

        expect($default->coreCount())->toBe(8);
    });

    it('FakeMemoryMetricsSource defaults to 8 GB total', function () {
        $default = FakeMemoryMetricsSource::default();

        expect($default->totalBytes)->toBe(8_589_934_592);
        expect($default->usedBytes)->toBe(4_294_967_296);
        expect($default->usedPercentage())->toBe(50.0);
    });

    it('FakeStorageMetricsSource defaults to single root mount', function () {
        $default = FakeStorageMetricsSource::default();

        expect($default->mountPoints)->toHaveCount(1);
        expect($default->mountPoints[0]->mountPoint)->toBe('/');
        expect($default->mountPoints[0]->usedPercentage())->toBe(50.0);
    });

    it('FakeNetworkMetricsSource defaults to single eth0 interface', function () {
        $default = FakeNetworkMetricsSource::default();

        expect($default->interfaces)->toHaveCount(1);
        expect($default->interfaces[0]->name)->toBe('eth0');
        expect($default->interfaces[0]->isUp)->toBeTrue();
    });

    it('FakeUptimeSource defaults to 1 day uptime', function () {
        $default = FakeUptimeSource::default();

        expect($default->totalSeconds)->toBe(86400);
        expect($default->days())->toBe(1);
    });

    it('FakeSystemLimitsSource defaults to 4 cores and 8 GB', function () {
        $default = FakeSystemLimitsSource::default();

        expect($default->cpuCores)->toBe(4);
        expect($default->memoryBytes)->toBe(8_589_934_592);
        expect($default->isContainerized())->toBeFalse();
    });

    it('FakeEnvironmentDetector defaults to Ubuntu Linux', function () {
        $default = FakeEnvironmentDetector::default();

        expect($default->os->name)->toBe('Ubuntu');
        expect($default->os->version)->toBe('22.04');
        expect($default->containerization->insideContainer)->toBeFalse();
    });
});
