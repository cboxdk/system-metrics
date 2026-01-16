<?php

declare(strict_types=1);

use Cbox\SystemMetrics\Actions\DetectEnvironmentAction;
use Cbox\SystemMetrics\Actions\ReadCpuMetricsAction;
use Cbox\SystemMetrics\Actions\ReadMemoryMetricsAction;
use Cbox\SystemMetrics\Actions\ReadNetworkMetricsAction;
use Cbox\SystemMetrics\Actions\ReadStorageMetricsAction;
use Cbox\SystemMetrics\Actions\SystemOverviewAction;
use Cbox\SystemMetrics\Contracts\CpuMetricsSource;
use Cbox\SystemMetrics\Contracts\EnvironmentDetector;
use Cbox\SystemMetrics\Contracts\MemoryMetricsSource;
use Cbox\SystemMetrics\Contracts\NetworkMetricsSource;
use Cbox\SystemMetrics\Contracts\StorageMetricsSource;
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
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\DTO\SystemOverview;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;

// Test doubles
class FakeSuccessEnvironmentDetector implements EnvironmentDetector
{
    public function detect(): Result
    {
        return Result::success(
            new EnvironmentSnapshot(
                os: new OperatingSystem(
                    family: OsFamily::Linux,
                    name: 'Ubuntu',
                    version: '22.04'
                ),
                kernel: new Kernel(
                    release: '5.15.0',
                    version: '#1 SMP'
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
                    version: CgroupVersion::V2,
                    cpuPath: '/',
                    memoryPath: '/'
                )
            )
        );
    }
}

class FakeFailureEnvironmentDetector implements EnvironmentDetector
{
    public function detect(): Result
    {
        return Result::failure(new SystemMetricsException('Environment detection failed'));
    }
}

class FakeSuccessCpuSource implements CpuMetricsSource
{
    public function read(): Result
    {
        return Result::success(
            new CpuSnapshot(
                total: new CpuTimes(1000, 100, 500, 8000, 200, 50, 150, 0),
                perCore: []
            )
        );
    }
}

class FakeFailureCpuSource implements CpuMetricsSource
{
    public function read(): Result
    {
        return Result::failure(new SystemMetricsException('CPU metrics read failed'));
    }
}

class FakeSuccessMemorySource implements MemoryMetricsSource
{
    public function read(): Result
    {
        return Result::success(
            new MemorySnapshot(
                totalBytes: 16000000000,
                freeBytes: 4000000000,
                availableBytes: 8000000000,
                usedBytes: 8000000000,
                buffersBytes: 1000000000,
                cachedBytes: 2000000000,
                swapTotalBytes: 2000000000,
                swapFreeBytes: 1500000000,
                swapUsedBytes: 500000000
            )
        );
    }
}

class FakeFailureMemorySource implements MemoryMetricsSource
{
    public function read(): Result
    {
        return Result::failure(new SystemMetricsException('Memory metrics read failed'));
    }
}

class FakeSuccessStorageSource implements StorageMetricsSource
{
    public function read(): Result
    {
        return Result::success(
            new StorageSnapshot(
                mountPoints: [],
                diskIO: []
            )
        );
    }
}

class FakeFailureStorageSource implements StorageMetricsSource
{
    public function read(): Result
    {
        return Result::failure(new SystemMetricsException('Storage metrics read failed'));
    }
}

class FakeSuccessNetworkSource implements NetworkMetricsSource
{
    public function read(): Result
    {
        return Result::success(
            new NetworkSnapshot(
                interfaces: [],
                connections: null
            )
        );
    }
}

class FakeFailureNetworkSource implements NetworkMetricsSource
{
    public function read(): Result
    {
        return Result::failure(new SystemMetricsException('Network metrics read failed'));
    }
}

describe('SystemOverviewAction', function () {
    it('collects complete system overview successfully', function () {
        $environmentAction = new DetectEnvironmentAction(new FakeSuccessEnvironmentDetector);
        $cpuAction = new ReadCpuMetricsAction(new FakeSuccessCpuSource);
        $memoryAction = new ReadMemoryMetricsAction(new FakeSuccessMemorySource);
        $storageAction = new ReadStorageMetricsAction(new FakeSuccessStorageSource);
        $networkAction = new ReadNetworkMetricsAction(new FakeSuccessNetworkSource);

        $action = new SystemOverviewAction($environmentAction, $cpuAction, $memoryAction, $storageAction, $networkAction);
        $result = $action->execute();

        expect($result->isSuccess())->toBeTrue();
        $overview = $result->getValue();
        expect($overview)->toBeInstanceOf(SystemOverview::class);
        expect($overview->environment)->toBeInstanceOf(EnvironmentSnapshot::class);
        expect($overview->cpu)->toBeInstanceOf(CpuSnapshot::class);
        expect($overview->memory)->toBeInstanceOf(MemorySnapshot::class);
        expect($overview->storage)->toBeInstanceOf(StorageSnapshot::class);
        expect($overview->network)->toBeInstanceOf(NetworkSnapshot::class);
    });

    it('propagates environment detection failure', function () {
        $environmentAction = new DetectEnvironmentAction(new FakeFailureEnvironmentDetector);
        $cpuAction = new ReadCpuMetricsAction(new FakeSuccessCpuSource);
        $memoryAction = new ReadMemoryMetricsAction(new FakeSuccessMemorySource);

        $storageAction = new ReadStorageMetricsAction(new FakeSuccessStorageSource);
        $networkAction = new ReadNetworkMetricsAction(new FakeSuccessNetworkSource);

        $action = new SystemOverviewAction($environmentAction, $cpuAction, $memoryAction, $storageAction, $networkAction);
        $result = $action->execute();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
        expect($result->getError()->getMessage())->toBe('Environment detection failed');
    });

    it('propagates CPU metrics failure', function () {
        $environmentAction = new DetectEnvironmentAction(new FakeSuccessEnvironmentDetector);
        $cpuAction = new ReadCpuMetricsAction(new FakeFailureCpuSource);
        $memoryAction = new ReadMemoryMetricsAction(new FakeSuccessMemorySource);

        $storageAction = new ReadStorageMetricsAction(new FakeSuccessStorageSource);
        $networkAction = new ReadNetworkMetricsAction(new FakeSuccessNetworkSource);

        $action = new SystemOverviewAction($environmentAction, $cpuAction, $memoryAction, $storageAction, $networkAction);
        $result = $action->execute();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
        expect($result->getError()->getMessage())->toBe('CPU metrics read failed');
    });

    it('propagates memory metrics failure', function () {
        $environmentAction = new DetectEnvironmentAction(new FakeSuccessEnvironmentDetector);
        $cpuAction = new ReadCpuMetricsAction(new FakeSuccessCpuSource);
        $memoryAction = new ReadMemoryMetricsAction(new FakeFailureMemorySource);

        $storageAction = new ReadStorageMetricsAction(new FakeSuccessStorageSource);
        $networkAction = new ReadNetworkMetricsAction(new FakeSuccessNetworkSource);

        $action = new SystemOverviewAction($environmentAction, $cpuAction, $memoryAction, $storageAction, $networkAction);
        $result = $action->execute();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
        expect($result->getError()->getMessage())->toBe('Memory metrics read failed');
    });

    it('propagates storage metrics failure', function () {
        $environmentAction = new DetectEnvironmentAction(new FakeSuccessEnvironmentDetector);
        $cpuAction = new ReadCpuMetricsAction(new FakeSuccessCpuSource);
        $memoryAction = new ReadMemoryMetricsAction(new FakeSuccessMemorySource);
        $storageAction = new ReadStorageMetricsAction(new FakeFailureStorageSource);
        $networkAction = new ReadNetworkMetricsAction(new FakeSuccessNetworkSource);

        $action = new SystemOverviewAction($environmentAction, $cpuAction, $memoryAction, $storageAction, $networkAction);
        $result = $action->execute();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
        expect($result->getError()->getMessage())->toBe('Storage metrics read failed');
    });

    it('propagates network metrics failure', function () {
        $environmentAction = new DetectEnvironmentAction(new FakeSuccessEnvironmentDetector);
        $cpuAction = new ReadCpuMetricsAction(new FakeSuccessCpuSource);
        $memoryAction = new ReadMemoryMetricsAction(new FakeSuccessMemorySource);
        $storageAction = new ReadStorageMetricsAction(new FakeSuccessStorageSource);
        $networkAction = new ReadNetworkMetricsAction(new FakeFailureNetworkSource);

        $action = new SystemOverviewAction($environmentAction, $cpuAction, $memoryAction, $storageAction, $networkAction);
        $result = $action->execute();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
        expect($result->getError()->getMessage())->toBe('Network metrics read failed');
    });
});
