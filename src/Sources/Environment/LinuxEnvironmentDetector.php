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
use PHPeek\SystemMetrics\Support\FileReader;

/**
 * Detects environment information on Linux systems.
 */
final class LinuxEnvironmentDetector implements EnvironmentDetector
{
    public function __construct(
        private readonly FileReader $fileReader = new FileReader,
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
        $result = $this->fileReader->read('/etc/os-release');

        if ($result->isSuccess()) {
            $content = $result->getValue();
            $name = $this->extractOsReleaseField($content, 'NAME') ?? 'Linux';
            $version = $this->extractOsReleaseField($content, 'VERSION_ID') ?? 'unknown';

            return new OperatingSystem(
                family: OsFamily::Linux,
                name: $name,
                version: $version,
            );
        }

        // Fallback to php_uname
        return new OperatingSystem(
            family: OsFamily::Linux,
            name: php_uname('s'),
            version: 'unknown',
        );
    }

    private function extractOsReleaseField(string $content, string $field): ?string
    {
        if (preg_match("/^{$field}=\"?([^\"\\n]+)\"?/m", $content, $matches)) {
            return $matches[1];
        }

        return null;
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
            in_array($raw, ['aarch64', 'arm64']) => ArchitectureKind::Arm64,
            default => ArchitectureKind::Other,
        };

        return new Architecture(kind: $kind, raw: $raw);
    }

    private function detectVirtualization(): Virtualization
    {
        // Check DMI information
        $productName = $this->fileReader->read('/sys/class/dmi/id/product_name')
            ->getValueOr('');
        $sysVendor = $this->fileReader->read('/sys/class/dmi/id/sys_vendor')
            ->getValueOr('');

        $productName = trim($productName);
        $sysVendor = trim($sysVendor);

        // Common virtualization indicators
        $indicators = [
            'KVM' => 'KVM',
            'QEMU' => 'QEMU',
            'VMware' => 'VMware',
            'VirtualBox' => 'VirtualBox',
            'Xen' => 'Xen',
            'Microsoft' => 'Hyper-V',
            'Bochs' => 'Bochs',
            'Parallels' => 'Parallels',
            'Amazon EC2' => 'AWS',
            'Google' => 'Google Cloud',
            'DigitalOcean' => 'DigitalOcean',
        ];

        $combined = "{$productName} {$sysVendor}";

        foreach ($indicators as $keyword => $vendor) {
            if (stripos($combined, $keyword) !== false) {
                return new Virtualization(
                    type: VirtualizationType::VirtualMachine,
                    vendor: $vendor,
                    rawIdentifier: $combined,
                );
            }
        }

        // Check for hypervisor flag in cpuinfo
        $cpuinfo = $this->fileReader->read('/proc/cpuinfo')->getValueOr('');
        if (str_contains($cpuinfo, 'hypervisor')) {
            return new Virtualization(
                type: VirtualizationType::VirtualMachine,
                vendor: null,
                rawIdentifier: 'hypervisor flag detected',
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
        // Check for Docker
        if ($this->fileReader->exists('/.dockerenv')) {
            return new Containerization(
                type: ContainerType::Docker,
                runtime: 'docker',
                insideContainer: true,
                rawIdentifier: '/.dockerenv',
            );
        }

        // Check for Podman
        if ($this->fileReader->exists('/run/.containerenv')) {
            return new Containerization(
                type: ContainerType::Other,
                runtime: 'podman',
                insideContainer: true,
                rawIdentifier: '/run/.containerenv',
            );
        }

        // Check cgroup for container indicators
        $cgroupResult = $this->fileReader->read('/proc/self/cgroup');
        if ($cgroupResult->isSuccess()) {
            $content = $cgroupResult->getValue();

            if (str_contains($content, 'docker')) {
                return new Containerization(
                    type: ContainerType::Docker,
                    runtime: 'docker',
                    insideContainer: true,
                    rawIdentifier: '/proc/self/cgroup',
                );
            }

            if (str_contains($content, 'kubepods')) {
                return new Containerization(
                    type: ContainerType::Kubernetes,
                    runtime: 'containerd',
                    insideContainer: true,
                    rawIdentifier: '/proc/self/cgroup',
                );
            }

            if (str_contains($content, 'containerd')) {
                return new Containerization(
                    type: ContainerType::Containerd,
                    runtime: 'containerd',
                    insideContainer: true,
                    rawIdentifier: '/proc/self/cgroup',
                );
            }

            if (str_contains($content, 'crio')) {
                return new Containerization(
                    type: ContainerType::Crio,
                    runtime: 'cri-o',
                    insideContainer: true,
                    rawIdentifier: '/proc/self/cgroup',
                );
            }
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
        // Check for cgroup v2
        $controllersResult = $this->fileReader->read('/sys/fs/cgroup/cgroup.controllers');
        if ($controllersResult->isSuccess()) {
            return new Cgroup(
                version: CgroupVersion::V2,
                cpuPath: '/sys/fs/cgroup',
                memoryPath: '/sys/fs/cgroup',
            );
        }

        // Check for cgroup v1
        $cgroupResult = $this->fileReader->read('/proc/self/cgroup');
        if ($cgroupResult->isSuccess()) {
            $content = $cgroupResult->getValue();
            $cpuPath = null;
            $memoryPath = null;

            // Parse cgroup v1 paths
            foreach (explode("\n", $content) as $line) {
                if (str_contains($line, ':cpu:') || str_contains($line, ':cpu,cpuacct:')) {
                    $parts = explode(':', $line, 3);
                    $cpuPath = '/sys/fs/cgroup/cpu'.($parts[2] ?? '');
                }

                if (str_contains($line, ':memory:')) {
                    $parts = explode(':', $line, 3);
                    $memoryPath = '/sys/fs/cgroup/memory'.($parts[2] ?? '');
                }
            }

            if ($cpuPath !== null || $memoryPath !== null) {
                return new Cgroup(
                    version: CgroupVersion::V1,
                    cpuPath: $cpuPath,
                    memoryPath: $memoryPath,
                );
            }
        }

        return new Cgroup(
            version: CgroupVersion::None,
            cpuPath: null,
            memoryPath: null,
        );
    }
}
