<?php

use Cbox\SystemMetrics\Config\SystemMetricsConfig;
use Cbox\SystemMetrics\Contracts\ContainerMetricsSource;
use Cbox\SystemMetrics\Contracts\CpuMetricsSource;
use Cbox\SystemMetrics\Contracts\EnvironmentDetector;
use Cbox\SystemMetrics\Contracts\LoadAverageSource;
use Cbox\SystemMetrics\Contracts\MemoryMetricsSource;
use Cbox\SystemMetrics\Contracts\NetworkMetricsSource;
use Cbox\SystemMetrics\Contracts\StorageMetricsSource;
use Cbox\SystemMetrics\Contracts\SystemLimitsSource;
use Cbox\SystemMetrics\Contracts\UptimeSource;
use Cbox\SystemMetrics\DTO\Environment\Architecture;
use Cbox\SystemMetrics\DTO\Environment\ArchitectureKind;
use Cbox\SystemMetrics\DTO\Environment\Cgroup;
use Cbox\SystemMetrics\DTO\Environment\CgroupVersion;
use Cbox\SystemMetrics\DTO\Environment\Containerization;
use Cbox\SystemMetrics\DTO\Environment\ContainerType;
use Cbox\SystemMetrics\DTO\Environment\EnvironmentSnapshot;
use Cbox\SystemMetrics\DTO\Environment\Kernel;
use Cbox\SystemMetrics\DTO\Environment\OperatingSystem;
use Cbox\SystemMetrics\DTO\Environment\OsFamily;
use Cbox\SystemMetrics\DTO\Environment\Virtualization;
use Cbox\SystemMetrics\DTO\Environment\VirtualizationType;
use Cbox\SystemMetrics\DTO\Environment\VirtualizationVendor;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\Container\CompositeContainerMetricsSource;
use Cbox\SystemMetrics\Sources\Cpu\CompositeCpuMetricsSource;
use Cbox\SystemMetrics\Sources\Environment\CompositeEnvironmentDetector;
use Cbox\SystemMetrics\Sources\LoadAverage\CompositeLoadAverageSource;
use Cbox\SystemMetrics\Sources\Memory\CompositeMemoryMetricsSource;
use Cbox\SystemMetrics\Sources\Network\CompositeNetworkMetricsSource;
use Cbox\SystemMetrics\Sources\Storage\CompositeStorageMetricsSource;
use Cbox\SystemMetrics\Sources\SystemLimits\CompositeSystemLimitsSource;
use Cbox\SystemMetrics\Sources\Uptime\CompositeUptimeSource;
use Cbox\SystemMetrics\Testing\FakeContainerMetricsSource;
use Cbox\SystemMetrics\Testing\FakeLoadAverageSource;
use Cbox\SystemMetrics\Testing\FakeNetworkMetricsSource;
use Cbox\SystemMetrics\Testing\FakeStorageMetricsSource;
use Cbox\SystemMetrics\Testing\FakeSystemLimitsSource;
use Cbox\SystemMetrics\Testing\FakeUptimeSource;

beforeEach(function () {
    // Reset config before each test to ensure isolation
    SystemMetricsConfig::reset();
});

afterEach(function () {
    // Clean up after each test
    SystemMetricsConfig::reset();
});

describe('SystemMetricsConfig', function () {
    it('returns default EnvironmentDetector when none set', function () {
        $detector = SystemMetricsConfig::getEnvironmentDetector();

        expect($detector)->toBeInstanceOf(EnvironmentDetector::class);
        expect($detector)->toBeInstanceOf(CompositeEnvironmentDetector::class);
    });

    it('returns custom EnvironmentDetector when set', function () {
        $customDetector = new class implements EnvironmentDetector
        {
            public function detect(): Result
            {
                return Result::success(
                    new EnvironmentSnapshot(
                        os: new OperatingSystem(
                            family: OsFamily::Linux,
                            name: 'Custom',
                            version: '1.0'
                        ),
                        kernel: new Kernel(
                            release: '5.0',
                            version: '5.0.0'
                        ),
                        architecture: new Architecture(
                            kind: ArchitectureKind::X86_64,
                            raw: 'x86_64'
                        ),
                        virtualization: new Virtualization(
                            type: VirtualizationType::BareMetal,
                            vendor: VirtualizationVendor::Unknown,
                            rawIdentifier: null
                        ),
                        containerization: new Containerization(
                            type: ContainerType::None,
                            runtime: null,
                            insideContainer: false,
                            rawIdentifier: null
                        ),
                        cgroup: new Cgroup(
                            version: CgroupVersion::None,
                            cpuPath: null,
                            memoryPath: null
                        )
                    )
                );
            }
        };

        SystemMetricsConfig::setEnvironmentDetector($customDetector);
        $detector = SystemMetricsConfig::getEnvironmentDetector();

        expect($detector)->toBe($customDetector);
    });

    it('returns default CpuMetricsSource when none set', function () {
        $source = SystemMetricsConfig::getCpuMetricsSource();

        expect($source)->toBeInstanceOf(CpuMetricsSource::class);
        expect($source)->toBeInstanceOf(CompositeCpuMetricsSource::class);
    });

    it('returns custom CpuMetricsSource when set', function () {
        $customSource = new class implements CpuMetricsSource
        {
            public function read(): Result
            {
                return Result::success(
                    new CpuSnapshot(
                        total: new CpuTimes(
                            user: 100,
                            nice: 0,
                            system: 50,
                            idle: 200,
                            iowait: 0,
                            irq: 0,
                            softirq: 0,
                            steal: 0
                        ),
                        perCore: []
                    )
                );
            }
        };

        SystemMetricsConfig::setCpuMetricsSource($customSource);
        $source = SystemMetricsConfig::getCpuMetricsSource();

        expect($source)->toBe($customSource);
    });

    it('returns default MemoryMetricsSource when none set', function () {
        $source = SystemMetricsConfig::getMemoryMetricsSource();

        expect($source)->toBeInstanceOf(MemoryMetricsSource::class);
        expect($source)->toBeInstanceOf(CompositeMemoryMetricsSource::class);
    });

    it('returns custom MemoryMetricsSource when set', function () {
        $customSource = new class implements MemoryMetricsSource
        {
            public function read(): Result
            {
                return Result::success(
                    new MemorySnapshot(
                        totalBytes: 1000000,
                        freeBytes: 500000,
                        availableBytes: 600000,
                        buffersBytes: 50000,
                        cachedBytes: 100000,
                        swapTotalBytes: 200000,
                        swapFreeBytes: 100000
                    )
                );
            }
        };

        SystemMetricsConfig::setMemoryMetricsSource($customSource);
        $source = SystemMetricsConfig::getMemoryMetricsSource();

        expect($source)->toBe($customSource);
    });

    it('resets all configuration to defaults', function () {
        $customDetector = new class implements EnvironmentDetector
        {
            public function detect(): Result
            {
                return Result::success(
                    new EnvironmentSnapshot(
                        os: new OperatingSystem(
                            family: OsFamily::Linux,
                            name: 'Custom',
                            version: '1.0'
                        ),
                        kernel: new Kernel(
                            release: '5.0',
                            version: '5.0.0'
                        ),
                        architecture: new Architecture(
                            kind: ArchitectureKind::X86_64,
                            raw: 'x86_64'
                        ),
                        virtualization: new Virtualization(
                            type: VirtualizationType::BareMetal,
                            vendor: VirtualizationVendor::Unknown,
                            rawIdentifier: null
                        ),
                        containerization: new Containerization(
                            type: ContainerType::None,
                            runtime: null,
                            insideContainer: false,
                            rawIdentifier: null
                        ),
                        cgroup: new Cgroup(
                            version: CgroupVersion::None,
                            cpuPath: null,
                            memoryPath: null
                        )
                    )
                );
            }
        };

        SystemMetricsConfig::setEnvironmentDetector($customDetector);
        SystemMetricsConfig::reset();

        $detector = SystemMetricsConfig::getEnvironmentDetector();
        expect($detector)->toBeInstanceOf(CompositeEnvironmentDetector::class);
        expect($detector)->not->toBe($customDetector);
    });

    it('persists custom configuration across multiple get calls', function () {
        $customDetector = new class implements EnvironmentDetector
        {
            public function detect(): Result
            {
                return Result::success(
                    new EnvironmentSnapshot(
                        os: new OperatingSystem(
                            family: OsFamily::Linux,
                            name: 'Custom',
                            version: '1.0'
                        ),
                        kernel: new Kernel(
                            release: '5.0',
                            version: '5.0.0'
                        ),
                        architecture: new Architecture(
                            kind: ArchitectureKind::X86_64,
                            raw: 'x86_64'
                        ),
                        virtualization: new Virtualization(
                            type: VirtualizationType::BareMetal,
                            vendor: VirtualizationVendor::Unknown,
                            rawIdentifier: null
                        ),
                        containerization: new Containerization(
                            type: ContainerType::None,
                            runtime: null,
                            insideContainer: false,
                            rawIdentifier: null
                        ),
                        cgroup: new Cgroup(
                            version: CgroupVersion::None,
                            cpuPath: null,
                            memoryPath: null
                        )
                    )
                );
            }
        };

        SystemMetricsConfig::setEnvironmentDetector($customDetector);

        $detector1 = SystemMetricsConfig::getEnvironmentDetector();
        $detector2 = SystemMetricsConfig::getEnvironmentDetector();

        expect($detector1)->toBe($customDetector);
        expect($detector2)->toBe($customDetector);
        expect($detector1)->toBe($detector2);
    });

    it('returns default LoadAverageSource when none set', function () {
        $source = SystemMetricsConfig::getLoadAverageSource();

        expect($source)->toBeInstanceOf(LoadAverageSource::class);
        expect($source)->toBeInstanceOf(CompositeLoadAverageSource::class);
    });

    it('returns custom LoadAverageSource when set', function () {
        $custom = new FakeLoadAverageSource;
        SystemMetricsConfig::setLoadAverageSource($custom);

        expect(SystemMetricsConfig::getLoadAverageSource())->toBe($custom);
    });

    it('returns default StorageMetricsSource when none set', function () {
        $source = SystemMetricsConfig::getStorageMetricsSource();

        expect($source)->toBeInstanceOf(StorageMetricsSource::class);
        expect($source)->toBeInstanceOf(CompositeStorageMetricsSource::class);
    });

    it('returns custom StorageMetricsSource when set', function () {
        $custom = new FakeStorageMetricsSource;
        SystemMetricsConfig::setStorageMetricsSource($custom);

        expect(SystemMetricsConfig::getStorageMetricsSource())->toBe($custom);
    });

    it('returns default NetworkMetricsSource when none set', function () {
        $source = SystemMetricsConfig::getNetworkMetricsSource();

        expect($source)->toBeInstanceOf(NetworkMetricsSource::class);
        expect($source)->toBeInstanceOf(CompositeNetworkMetricsSource::class);
    });

    it('returns custom NetworkMetricsSource when set', function () {
        $custom = new FakeNetworkMetricsSource;
        SystemMetricsConfig::setNetworkMetricsSource($custom);

        expect(SystemMetricsConfig::getNetworkMetricsSource())->toBe($custom);
    });

    it('returns default UptimeSource when none set', function () {
        $source = SystemMetricsConfig::getUptimeSource();

        expect($source)->toBeInstanceOf(UptimeSource::class);
        expect($source)->toBeInstanceOf(CompositeUptimeSource::class);
    });

    it('returns custom UptimeSource when set', function () {
        $custom = new FakeUptimeSource;
        SystemMetricsConfig::setUptimeSource($custom);

        expect(SystemMetricsConfig::getUptimeSource())->toBe($custom);
    });

    it('returns default ContainerMetricsSource when none set', function () {
        $source = SystemMetricsConfig::getContainerMetricsSource();

        expect($source)->toBeInstanceOf(ContainerMetricsSource::class);
        expect($source)->toBeInstanceOf(CompositeContainerMetricsSource::class);
    });

    it('returns custom ContainerMetricsSource when set', function () {
        $custom = new FakeContainerMetricsSource;
        SystemMetricsConfig::setContainerMetricsSource($custom);

        expect(SystemMetricsConfig::getContainerMetricsSource())->toBe($custom);
    });

    it('returns default SystemLimitsSource when none set', function () {
        $source = SystemMetricsConfig::getSystemLimitsSource();

        expect($source)->toBeInstanceOf(SystemLimitsSource::class);
        expect($source)->toBeInstanceOf(CompositeSystemLimitsSource::class);
    });

    it('returns custom SystemLimitsSource when set', function () {
        $custom = new FakeSystemLimitsSource;
        SystemMetricsConfig::setSystemLimitsSource($custom);

        expect(SystemMetricsConfig::getSystemLimitsSource())->toBe($custom);
    });

    it('resets all new sources to defaults', function () {
        SystemMetricsConfig::setLoadAverageSource(new FakeLoadAverageSource);
        SystemMetricsConfig::setStorageMetricsSource(new FakeStorageMetricsSource);
        SystemMetricsConfig::setNetworkMetricsSource(new FakeNetworkMetricsSource);
        SystemMetricsConfig::setUptimeSource(new FakeUptimeSource);
        SystemMetricsConfig::setContainerMetricsSource(new FakeContainerMetricsSource);
        SystemMetricsConfig::setSystemLimitsSource(new FakeSystemLimitsSource);

        SystemMetricsConfig::reset();

        expect(SystemMetricsConfig::getLoadAverageSource())->toBeInstanceOf(CompositeLoadAverageSource::class);
        expect(SystemMetricsConfig::getStorageMetricsSource())->toBeInstanceOf(CompositeStorageMetricsSource::class);
        expect(SystemMetricsConfig::getNetworkMetricsSource())->toBeInstanceOf(CompositeNetworkMetricsSource::class);
        expect(SystemMetricsConfig::getUptimeSource())->toBeInstanceOf(CompositeUptimeSource::class);
        expect(SystemMetricsConfig::getContainerMetricsSource())->toBeInstanceOf(CompositeContainerMetricsSource::class);
        expect(SystemMetricsConfig::getSystemLimitsSource())->toBeInstanceOf(CompositeSystemLimitsSource::class);
    });
});
