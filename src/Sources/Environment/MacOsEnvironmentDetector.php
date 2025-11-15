<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Environment;

use PHPeek\SystemMetrics\Contracts\EnvironmentDetector;
use PHPeek\SystemMetrics\DTO\Environment\Architecture;
use PHPeek\SystemMetrics\DTO\Environment\ArchitectureKind;
use PHPeek\SystemMetrics\DTO\Environment\Cgroup;
use PHPeek\SystemMetrics\DTO\Environment\CgroupVersion;
use PHPeek\SystemMetrics\DTO\Environment\Containerization;
use PHPeek\SystemMetrics\DTO\Environment\ContainerType;
use PHPeek\SystemMetrics\DTO\Environment\EnvironmentSnapshot;
use PHPeek\SystemMetrics\DTO\Environment\Kernel;
use PHPeek\SystemMetrics\DTO\Environment\OperatingSystem;
use PHPeek\SystemMetrics\DTO\Environment\OsFamily;
use PHPeek\SystemMetrics\DTO\Environment\Virtualization;
use PHPeek\SystemMetrics\DTO\Environment\VirtualizationType;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Support\ProcessRunner;

/**
 * Detects environment information on macOS systems.
 */
final class MacOsEnvironmentDetector implements EnvironmentDetector
{
    public function __construct(
        private readonly ProcessRunner $processRunner = new ProcessRunner,
    ) {}

    public function detect(): Result
    {
        return Result::success(new EnvironmentSnapshot(
            os: $this->detectOperatingSystem(),
            kernel: $this->detectKernel(),
            architecture: $this->detectArchitecture(),
            virtualization: $this->detectVirtualization(),
            containerization: $this->detectContainerization(),
            cgroup: $this->detectCgroup(),
        ));
    }

    private function detectOperatingSystem(): OperatingSystem
    {
        $name = 'macOS';
        $version = 'unknown';

        // Try sw_vers for detailed version info
        $result = $this->processRunner->execute('sw_vers -productVersion');
        if ($result->isSuccess()) {
            $version = trim($result->getValue());
        } else {
            // Fallback to php_uname
            $unameVersion = php_uname('r');
            // Darwin kernel version (e.g., "23.1.0" for macOS Sonoma)
            $version = $unameVersion;
        }

        return new OperatingSystem(
            family: OsFamily::MacOs,
            name: $name,
            version: $version,
        );
    }

    private function detectKernel(): Kernel
    {
        return new Kernel(
            release: php_uname('r'),
            version: php_uname('v'),
        );
    }

    private function detectArchitecture(): Architecture
    {
        $raw = php_uname('m');

        $kind = match (true) {
            in_array($raw, ['x86_64', 'amd64']) => ArchitectureKind::X86_64,
            in_array($raw, ['arm64']) => ArchitectureKind::Arm64,
            default => ArchitectureKind::Other,
        };

        return new Architecture(kind: $kind, raw: $raw);
    }

    private function detectVirtualization(): Virtualization
    {
        // Try to detect virtualization through sysctl
        $result = $this->processRunner->execute('sysctl -n machdep.cpu.brand_string');

        if ($result->isSuccess()) {
            $cpuBrand = trim($result->getValue());

            // Check for virtualization indicators
            if (str_contains(strtolower($cpuBrand), 'virtual') ||
                str_contains(strtolower($cpuBrand), 'qemu')) {
                return new Virtualization(
                    type: VirtualizationType::VirtualMachine,
                    vendor: null,
                    rawIdentifier: $cpuBrand,
                );
            }
        }

        // Check if running under Rosetta 2 (Intel emulation on Apple Silicon)
        $rosettaResult = $this->processRunner->execute('sysctl -n sysctl.proc_translated');
        if ($rosettaResult->isSuccess() && trim($rosettaResult->getValue()) === '1') {
            return new Virtualization(
                type: VirtualizationType::VirtualMachine,
                vendor: 'Rosetta 2',
                rawIdentifier: 'sysctl.proc_translated=1',
            );
        }

        return new Virtualization(
            type: VirtualizationType::BareMetal,
            vendor: null,
            rawIdentifier: null,
        );
    }

    private function detectContainerization(): Containerization
    {
        // macOS doesn't typically run in containers except for Docker Desktop
        // which uses a VM, so container detection is limited

        // Check for Docker Desktop environment variables
        if (getenv('DOCKER_HOST') !== false) {
            return new Containerization(
                type: ContainerType::Docker,
                runtime: 'docker-desktop',
                insideContainer: false,
                rawIdentifier: 'DOCKER_HOST env var',
            );
        }

        return new Containerization(
            type: ContainerType::None,
            runtime: null,
            insideContainer: false,
            rawIdentifier: null,
        );
    }

    private function detectCgroup(): Cgroup
    {
        // macOS does not use cgroups
        return new Cgroup(
            version: CgroupVersion::None,
            cpuPath: null,
            memoryPath: null,
        );
    }
}
