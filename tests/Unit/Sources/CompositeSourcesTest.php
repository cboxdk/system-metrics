<?php

use Cbox\SystemMetrics\Contracts\CpuMetricsSource;
use Cbox\SystemMetrics\Contracts\EnvironmentDetector;
use Cbox\SystemMetrics\Contracts\MemoryMetricsSource;
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
use Cbox\SystemMetrics\Sources\Cpu\CompositeCpuMetricsSource;
use Cbox\SystemMetrics\Sources\Environment\CompositeEnvironmentDetector;
use Cbox\SystemMetrics\Sources\Memory\CompositeMemoryMetricsSource;

// Fake sources for testing injection
class FakeCpuSource implements CpuMetricsSource
{
    public function read(): Result
    {
        return Result::success(new CpuSnapshot(
            total: new CpuTimes(100, 0, 50, 200, 0, 0, 0, 0),
            perCore: []
        ));
    }
}

class FakeMemorySource implements MemoryMetricsSource
{
    public function read(): Result
    {
        return Result::success(new MemorySnapshot(
            totalBytes: 1000000,
            freeBytes: 500000,
            availableBytes: 600000,
            usedBytes: 500000,
            buffersBytes: 0,
            cachedBytes: 0,
            swapTotalBytes: 0,
            swapFreeBytes: 0,
            swapUsedBytes: 0
        ));
    }
}

class FakeEnvironmentDetector implements EnvironmentDetector
{
    public function detect(): Result
    {
        return Result::success(new EnvironmentSnapshot(
            os: new OperatingSystem(OsFamily::Linux, 'Fake', '1.0'),
            kernel: new Kernel('5.0', '5.0.0'),
            architecture: new Architecture(ArchitectureKind::X86_64, 'x86_64'),
            virtualization: new Virtualization(VirtualizationType::BareMetal, VirtualizationVendor::Unknown, null),
            containerization: new Containerization(ContainerType::None, null, false, null),
            cgroup: new Cgroup(CgroupVersion::None, null, null)
        ));
    }
}

describe('CompositeCpuMetricsSource', function () {
    it('uses injected source when provided', function () {
        $fakeSource = new FakeCpuSource;
        $composite = new CompositeCpuMetricsSource($fakeSource);

        $result = $composite->read();

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeInstanceOf(CpuSnapshot::class);
    });

    it('creates OS-specific source when none provided', function () {
        $composite = new CompositeCpuMetricsSource;

        // This will use the actual OS-specific source (Linux or MacOS)
        // We just verify it returns a Result
        $result = $composite->read();

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('delegates read to underlying source', function () {
        $fakeSource = new FakeCpuSource;
        $composite = new CompositeCpuMetricsSource($fakeSource);

        $result = $composite->read();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot->total->user)->toBe(100);
        expect($snapshot->total->system)->toBe(50);
    });
});

describe('CompositeMemoryMetricsSource', function () {
    it('uses injected source when provided', function () {
        $fakeSource = new FakeMemorySource;
        $composite = new CompositeMemoryMetricsSource($fakeSource);

        $result = $composite->read();

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeInstanceOf(MemorySnapshot::class);
    });

    it('creates OS-specific source when none provided', function () {
        $composite = new CompositeMemoryMetricsSource;

        // This will use the actual OS-specific source
        $result = $composite->read();

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('delegates read to underlying source', function () {
        $fakeSource = new FakeMemorySource;
        $composite = new CompositeMemoryMetricsSource($fakeSource);

        $result = $composite->read();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot->totalBytes)->toBe(1000000);
        expect($snapshot->freeBytes)->toBe(500000);
    });
});

describe('CompositeEnvironmentDetector', function () {
    it('uses injected detector when provided', function () {
        $fakeDetector = new FakeEnvironmentDetector;
        $composite = new CompositeEnvironmentDetector($fakeDetector);

        $result = $composite->detect();

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeInstanceOf(EnvironmentSnapshot::class);
    });

    it('creates OS-specific detector when none provided', function () {
        $composite = new CompositeEnvironmentDetector;

        // This will use the actual OS-specific detector
        $result = $composite->detect();

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('delegates detect to underlying detector', function () {
        $fakeDetector = new FakeEnvironmentDetector;
        $composite = new CompositeEnvironmentDetector($fakeDetector);

        $result = $composite->detect();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot->os->name)->toBe('Fake');
        expect($snapshot->os->version)->toBe('1.0');
    });
});
