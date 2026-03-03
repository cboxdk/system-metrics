<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Testing;

use Cbox\SystemMetrics\Contracts\EnvironmentDetector;
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
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Fake EnvironmentDetector for testing.
 */
final class FakeEnvironmentDetector implements EnvironmentDetector
{
    private ?EnvironmentSnapshot $snapshot = null;

    private ?SystemMetricsException $exception = null;

    /**
     * @return Result<EnvironmentSnapshot>
     */
    public function detect(): Result
    {
        if ($this->exception !== null) {
            /** @var Result<EnvironmentSnapshot> */
            return Result::failure($this->exception);
        }

        return Result::success($this->snapshot ?? self::default());
    }

    public function set(EnvironmentSnapshot $snapshot): self
    {
        $this->snapshot = $snapshot;

        return $this;
    }

    public function failWith(SystemMetricsException $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    public static function default(): EnvironmentSnapshot
    {
        return new EnvironmentSnapshot(
            os: new OperatingSystem(
                family: OsFamily::Linux,
                name: 'Ubuntu',
                version: '22.04',
            ),
            kernel: new Kernel(
                release: '5.15.0-generic',
                version: '#1 SMP',
            ),
            architecture: new Architecture(
                kind: ArchitectureKind::X86_64,
                raw: 'x86_64',
            ),
            virtualization: new Virtualization(
                type: VirtualizationType::BareMetal,
                vendor: VirtualizationVendor::Unknown,
                rawIdentifier: null,
            ),
            containerization: new Containerization(
                type: ContainerType::None,
                runtime: null,
                insideContainer: false,
                rawIdentifier: null,
            ),
            cgroup: new Cgroup(
                version: CgroupVersion::None,
                cpuPath: null,
                memoryPath: null,
            ),
        );
    }
}
