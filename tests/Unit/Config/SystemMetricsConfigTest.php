<?php

use Cbox\SystemMetrics\Config\SystemMetricsConfig;
use Cbox\SystemMetrics\Contracts\CpuMetricsSource;
use Cbox\SystemMetrics\Contracts\EnvironmentDetector;
use Cbox\SystemMetrics\Contracts\MemoryMetricsSource;
use Cbox\SystemMetrics\Sources\Cpu\CompositeCpuMetricsSource;
use Cbox\SystemMetrics\Sources\Environment\CompositeEnvironmentDetector;
use Cbox\SystemMetrics\Sources\Memory\CompositeMemoryMetricsSource;

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
            public function detect(): \Cbox\SystemMetrics\DTO\Result
            {
                return \Cbox\SystemMetrics\DTO\Result::success(
                    new \Cbox\SystemMetrics\DTO\Environment\EnvironmentSnapshot(
                        os: new \Cbox\SystemMetrics\DTO\Environment\OperatingSystem(
                            family: \Cbox\SystemMetrics\DTO\Environment\OsFamily::Linux,
                            name: 'Custom',
                            version: '1.0'
                        ),
                        kernel: new \Cbox\SystemMetrics\DTO\Environment\Kernel(
                            release: '5.0',
                            version: '5.0.0'
                        ),
                        architecture: new \Cbox\SystemMetrics\DTO\Environment\Architecture(
                            kind: \Cbox\SystemMetrics\DTO\Environment\ArchitectureKind::X86_64,
                            raw: 'x86_64'
                        ),
                        virtualization: new \Cbox\SystemMetrics\DTO\Environment\Virtualization(
                            type: \Cbox\SystemMetrics\DTO\Environment\VirtualizationType::BareMetal,
                            vendor: \Cbox\SystemMetrics\DTO\Environment\VirtualizationVendor::Unknown,
                            rawIdentifier: null
                        ),
                        containerization: new \Cbox\SystemMetrics\DTO\Environment\Containerization(
                            type: \Cbox\SystemMetrics\DTO\Environment\ContainerType::None,
                            runtime: null,
                            insideContainer: false,
                            rawIdentifier: null
                        ),
                        cgroup: new \Cbox\SystemMetrics\DTO\Environment\Cgroup(
                            version: \Cbox\SystemMetrics\DTO\Environment\CgroupVersion::None,
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
            public function read(): \Cbox\SystemMetrics\DTO\Result
            {
                return \Cbox\SystemMetrics\DTO\Result::success(
                    new \Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot(
                        total: new \Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuTimes(
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
            public function read(): \Cbox\SystemMetrics\DTO\Result
            {
                return \Cbox\SystemMetrics\DTO\Result::success(
                    new \Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot(
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
            public function detect(): \Cbox\SystemMetrics\DTO\Result
            {
                return \Cbox\SystemMetrics\DTO\Result::success(
                    new \Cbox\SystemMetrics\DTO\Environment\EnvironmentSnapshot(
                        os: new \Cbox\SystemMetrics\DTO\Environment\OperatingSystem(
                            family: \Cbox\SystemMetrics\DTO\Environment\OsFamily::Linux,
                            name: 'Custom',
                            version: '1.0'
                        ),
                        kernel: new \Cbox\SystemMetrics\DTO\Environment\Kernel(
                            release: '5.0',
                            version: '5.0.0'
                        ),
                        architecture: new \Cbox\SystemMetrics\DTO\Environment\Architecture(
                            kind: \Cbox\SystemMetrics\DTO\Environment\ArchitectureKind::X86_64,
                            raw: 'x86_64'
                        ),
                        virtualization: new \Cbox\SystemMetrics\DTO\Environment\Virtualization(
                            type: \Cbox\SystemMetrics\DTO\Environment\VirtualizationType::BareMetal,
                            vendor: \Cbox\SystemMetrics\DTO\Environment\VirtualizationVendor::Unknown,
                            rawIdentifier: null
                        ),
                        containerization: new \Cbox\SystemMetrics\DTO\Environment\Containerization(
                            type: \Cbox\SystemMetrics\DTO\Environment\ContainerType::None,
                            runtime: null,
                            insideContainer: false,
                            rawIdentifier: null
                        ),
                        cgroup: new \Cbox\SystemMetrics\DTO\Environment\Cgroup(
                            version: \Cbox\SystemMetrics\DTO\Environment\CgroupVersion::None,
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
            public function detect(): \Cbox\SystemMetrics\DTO\Result
            {
                return \Cbox\SystemMetrics\DTO\Result::success(
                    new \Cbox\SystemMetrics\DTO\Environment\EnvironmentSnapshot(
                        os: new \Cbox\SystemMetrics\DTO\Environment\OperatingSystem(
                            family: \Cbox\SystemMetrics\DTO\Environment\OsFamily::Linux,
                            name: 'Custom',
                            version: '1.0'
                        ),
                        kernel: new \Cbox\SystemMetrics\DTO\Environment\Kernel(
                            release: '5.0',
                            version: '5.0.0'
                        ),
                        architecture: new \Cbox\SystemMetrics\DTO\Environment\Architecture(
                            kind: \Cbox\SystemMetrics\DTO\Environment\ArchitectureKind::X86_64,
                            raw: 'x86_64'
                        ),
                        virtualization: new \Cbox\SystemMetrics\DTO\Environment\Virtualization(
                            type: \Cbox\SystemMetrics\DTO\Environment\VirtualizationType::BareMetal,
                            vendor: \Cbox\SystemMetrics\DTO\Environment\VirtualizationVendor::Unknown,
                            rawIdentifier: null
                        ),
                        containerization: new \Cbox\SystemMetrics\DTO\Environment\Containerization(
                            type: \Cbox\SystemMetrics\DTO\Environment\ContainerType::None,
                            runtime: null,
                            insideContainer: false,
                            rawIdentifier: null
                        ),
                        cgroup: new \Cbox\SystemMetrics\DTO\Environment\Cgroup(
                            version: \Cbox\SystemMetrics\DTO\Environment\CgroupVersion::None,
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
});
