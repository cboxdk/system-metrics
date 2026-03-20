<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Environment;

use Cbox\SystemMetrics\Contracts\EnvironmentDetector;
use Cbox\SystemMetrics\Contracts\FileReaderInterface;
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
use Cbox\SystemMetrics\Support\FileReader;

/**
 * Detects environment information on Linux systems.
 */
final class LinuxEnvironmentDetector implements EnvironmentDetector
{
    public function __construct(
        private readonly FileReaderInterface $fileReader = new FileReader,
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
            $name = $this->extractOsReleaseField($content, 'PRETTY_NAME')
                ?? $this->extractOsReleaseField($content, 'NAME')
                ?? 'Linux';
            $version = $this->extractOsReleaseField($content, 'VERSION_ID')
                ?? $this->extractOsReleaseField($content, 'VERSION')
                ?? $this->extractOsReleaseField($content, 'BUILD_ID')
                ?? 'unknown';

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
            'KVM' => VirtualizationVendor::KVM,
            'QEMU' => VirtualizationVendor::QEMU,
            'VMware' => VirtualizationVendor::VMware,
            'VirtualBox' => VirtualizationVendor::VirtualBox,
            'Xen' => VirtualizationVendor::Xen,
            'Microsoft' => VirtualizationVendor::HyperV,
            'Bochs' => VirtualizationVendor::Bochs,
            'Parallels' => VirtualizationVendor::Parallels,
            'Amazon EC2' => VirtualizationVendor::AWS,
            'Google' => VirtualizationVendor::GoogleCloud,
            'DigitalOcean' => VirtualizationVendor::DigitalOcean,
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
                vendor: VirtualizationVendor::Unknown,
                rawIdentifier: 'hypervisor flag detected',
            );
        }

        return new Virtualization(
            type: VirtualizationType::BareMetal,
            vendor: VirtualizationVendor::Unknown,
            rawIdentifier: null,
        );
    }

    /**
     * Detect if we are running inside a container.
     *
     * Strategy: check for known container indicators only. Each check looks for
     * a specific, well-documented marker that only exists inside containers.
     * If no indicator matches, we are not in a container. We never try to prove
     * a negative ("this looks like a VM because...") — that path leads to
     * fragile heuristics and false positives.
     *
     * Detection order (most reliable first):
     * 1. Sentinel files: /.dockerenv (Docker), /run/.containerenv (Podman)
     * 2. PID 1 environment: container= variable set by LXC/systemd-nspawn
     * 3. Cgroup keywords: docker, kubepods, containerd, crio, lxc in cgroup paths
     */
    private function detectContainerization(): Containerization
    {
        // 1. Sentinel files — definitive, no false positives
        if ($this->fileReader->exists('/.dockerenv')) {
            return $this->containerized(ContainerType::Docker, 'docker', '/.dockerenv');
        }

        if ($this->fileReader->exists('/run/.containerenv')) {
            return $this->containerized(ContainerType::Other, 'podman', '/run/.containerenv');
        }

        // 2. PID 1 environment — LXC and systemd-nspawn set container= in init env
        $initEnviron = $this->fileReader->read('/proc/1/environ');
        if ($initEnviron->isSuccess() && str_contains($initEnviron->getValue(), 'container=')) {
            return $this->containerized(ContainerType::Other, 'systemd-container', '/proc/1/environ');
        }

        // 3. Cgroup keywords — check both /proc/self/cgroup and /proc/1/cgroup
        //    for runtime-specific identifiers. Works on both cgroup v1 and v2.
        $cgroupContent = $this->readCgroupContent();

        if ($cgroupContent !== null) {
            $match = $this->matchContainerRuntime($cgroupContent);
            if ($match !== null) {
                return $match;
            }
        }

        return new Containerization(
            type: ContainerType::None,
            runtime: null,
            insideContainer: false,
            rawIdentifier: null,
        );
    }

    /**
     * Read cgroup content from both self and PID 1 for keyword matching.
     */
    private function readCgroupContent(): ?string
    {
        $parts = [];

        $self = $this->fileReader->read('/proc/self/cgroup');
        if ($self->isSuccess()) {
            $parts[] = $self->getValue();
        }

        $pid1 = $this->fileReader->read('/proc/1/cgroup');
        if ($pid1->isSuccess()) {
            $parts[] = $pid1->getValue();
        }

        return $parts !== [] ? implode("\n", $parts) : null;
    }

    /**
     * Match known container runtime keywords in cgroup content.
     *
     * Each keyword is specific to a container runtime and does not appear
     * in normal VM/bare-metal cgroup paths. This is an allowlist approach:
     * only known container patterns trigger detection.
     */
    private function matchContainerRuntime(string $cgroupContent): ?Containerization
    {
        /** @var array<string, array{type: ContainerType, runtime: string}> */
        $runtimes = [
            'kubepods' => ['type' => ContainerType::Kubernetes, 'runtime' => 'containerd'],
            'docker' => ['type' => ContainerType::Docker, 'runtime' => 'docker'],
            'containerd' => ['type' => ContainerType::Containerd, 'runtime' => 'containerd'],
            'crio' => ['type' => ContainerType::Crio, 'runtime' => 'cri-o'],
            'lxc' => ['type' => ContainerType::Other, 'runtime' => 'lxc'],
        ];

        foreach ($runtimes as $keyword => $config) {
            if (str_contains($cgroupContent, $keyword)) {
                return $this->containerized($config['type'], $config['runtime'], '/proc/cgroup');
            }
        }

        return null;
    }

    private function containerized(ContainerType $type, string $runtime, string $identifier): Containerization
    {
        return new Containerization(
            type: $type,
            runtime: $runtime,
            insideContainer: true,
            rawIdentifier: $identifier,
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
