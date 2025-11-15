<?php

use PHPeek\SystemMetrics\Contracts\FileReaderInterface;
use PHPeek\SystemMetrics\DTO\Environment\ArchitectureKind;
use PHPeek\SystemMetrics\DTO\Environment\CgroupVersion;
use PHPeek\SystemMetrics\DTO\Environment\ContainerType;
use PHPeek\SystemMetrics\DTO\Environment\EnvironmentSnapshot;
use PHPeek\SystemMetrics\DTO\Environment\OsFamily;
use PHPeek\SystemMetrics\DTO\Environment\VirtualizationType;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\FileNotFoundException;
use PHPeek\SystemMetrics\Sources\Environment\LinuxEnvironmentDetector;

// Flexible fake file reader that can return different content based on paths
class FakeEnvironmentFileReader implements FileReaderInterface
{
    public function __construct(private array $files = [], private array $existingFiles = []) {}

    public function read(string $path): Result
    {
        if (isset($this->files[$path])) {
            return Result::success($this->files[$path]);
        }

        return Result::failure(new FileNotFoundException($path));
    }

    public function exists(string $path): bool
    {
        return in_array($path, $this->existingFiles, true);
    }
}

describe('LinuxEnvironmentDetector', function () {
    it('can detect basic Linux environment', function () {
        $fileReader = new FakeEnvironmentFileReader([
            '/etc/os-release' => "NAME=\"Ubuntu\"\nVERSION_ID=\"22.04\"\n",
        ]);

        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeInstanceOf(EnvironmentSnapshot::class);

        $snapshot = $result->getValue();
        expect($snapshot->os->family)->toBe(OsFamily::Linux);
        expect($snapshot->os->name)->toBe('Ubuntu');
        expect($snapshot->os->version)->toBe('22.04');
    });

    it('detects operating system from os-release file', function () {
        $fileReader = new FakeEnvironmentFileReader([
            '/etc/os-release' => "NAME=\"Debian GNU/Linux\"\nVERSION_ID=\"11\"\n",
        ]);

        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->os->name)->toBe('Debian GNU/Linux');
        expect($snapshot->os->version)->toBe('11');
    });

    it('falls back to php_uname when os-release is missing', function () {
        $fileReader = new FakeEnvironmentFileReader;
        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->os->family)->toBe(OsFamily::Linux);
        expect($snapshot->os->version)->toBe('unknown');
    });

    it('detects kernel information', function () {
        $fileReader = new FakeEnvironmentFileReader;
        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->kernel->release)->toBeString();
        expect($snapshot->kernel->version)->toBeString();
    });

    it('detects x86_64 architecture', function () {
        $fileReader = new FakeEnvironmentFileReader;
        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->architecture->kind)->toBeInstanceOf(ArchitectureKind::class);
        expect($snapshot->architecture->raw)->toBeString();
    });

    it('detects KVM virtualization', function () {
        $fileReader = new FakeEnvironmentFileReader([
            '/sys/class/dmi/id/product_name' => 'KVM',
            '/sys/class/dmi/id/sys_vendor' => 'QEMU',
        ]);

        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->virtualization->type)->toBe(VirtualizationType::VirtualMachine);
        expect($snapshot->virtualization->vendor)->toBe('KVM');
    });

    it('detects VMware virtualization', function () {
        $fileReader = new FakeEnvironmentFileReader([
            '/sys/class/dmi/id/product_name' => 'VMware Virtual Platform',
            '/sys/class/dmi/id/sys_vendor' => 'VMware, Inc.',
        ]);

        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->virtualization->type)->toBe(VirtualizationType::VirtualMachine);
        expect($snapshot->virtualization->vendor)->toBe('VMware');
    });

    it('detects VirtualBox virtualization', function () {
        $fileReader = new FakeEnvironmentFileReader([
            '/sys/class/dmi/id/product_name' => 'VirtualBox',
            '/sys/class/dmi/id/sys_vendor' => 'innotek GmbH',
        ]);

        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->virtualization->vendor)->toBe('VirtualBox');
    });

    it('detects hypervisor flag in cpuinfo', function () {
        $fileReader = new FakeEnvironmentFileReader([
            '/proc/cpuinfo' => "processor : 0\nflags : fpu hypervisor\n",
        ]);

        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->virtualization->type)->toBe(VirtualizationType::VirtualMachine);
        expect($snapshot->virtualization->rawIdentifier)->toBe('hypervisor flag detected');
    });

    it('detects bare metal when no virtualization', function () {
        $fileReader = new FakeEnvironmentFileReader([
            '/sys/class/dmi/id/product_name' => 'System Product Name',
            '/sys/class/dmi/id/sys_vendor' => 'ASUS',
            '/proc/cpuinfo' => "processor : 0\nflags : fpu vme de\n",
        ]);

        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->virtualization->type)->toBe(VirtualizationType::BareMetal);
        expect($snapshot->virtualization->vendor)->toBeNull();
    });

    it('detects Docker container via dockerenv file', function () {
        $fileReader = new FakeEnvironmentFileReader([], ['/.dockerenv']);

        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->containerization->type)->toBe(ContainerType::Docker);
        expect($snapshot->containerization->runtime)->toBe('docker');
        expect($snapshot->containerization->insideContainer)->toBeTrue();
    });

    it('detects Podman container', function () {
        $fileReader = new FakeEnvironmentFileReader([], ['/run/.containerenv']);

        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->containerization->runtime)->toBe('podman');
        expect($snapshot->containerization->insideContainer)->toBeTrue();
    });

    it('detects Docker from cgroup', function () {
        $fileReader = new FakeEnvironmentFileReader([
            '/proc/self/cgroup' => "12:cpu:/docker/abc123\n11:memory:/docker/abc123\n",
        ]);

        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->containerization->type)->toBe(ContainerType::Docker);
        expect($snapshot->containerization->runtime)->toBe('docker');
    });

    it('detects Kubernetes from cgroup', function () {
        $fileReader = new FakeEnvironmentFileReader([
            '/proc/self/cgroup' => "12:cpu:/kubepods/pod123\n",
        ]);

        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->containerization->type)->toBe(ContainerType::Kubernetes);
        expect($snapshot->containerization->runtime)->toBe('containerd');
    });

    it('detects containerd from cgroup', function () {
        $fileReader = new FakeEnvironmentFileReader([
            '/proc/self/cgroup' => "12:cpu:/system.slice/containerd.service\n",
        ]);

        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->containerization->type)->toBe(ContainerType::Containerd);
        expect($snapshot->containerization->runtime)->toBe('containerd');
    });

    it('detects cri-o from cgroup', function () {
        $fileReader = new FakeEnvironmentFileReader([
            '/proc/self/cgroup' => "12:cpu:/system.slice/crio-abc123.scope\n",
        ]);

        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->containerization->type)->toBe(ContainerType::Crio);
        expect($snapshot->containerization->runtime)->toBe('cri-o');
    });

    it('detects no container when not containerized', function () {
        $fileReader = new FakeEnvironmentFileReader;

        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->containerization->type)->toBe(ContainerType::None);
        expect($snapshot->containerization->insideContainer)->toBeFalse();
        expect($snapshot->containerization->runtime)->toBeNull();
    });

    it('detects cgroup v2', function () {
        $fileReader = new FakeEnvironmentFileReader([
            '/sys/fs/cgroup/cgroup.controllers' => "cpu memory io\n",
        ]);

        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->cgroup->version)->toBe(CgroupVersion::V2);
        expect($snapshot->cgroup->cpuPath)->toBe('/sys/fs/cgroup');
        expect($snapshot->cgroup->memoryPath)->toBe('/sys/fs/cgroup');
    });

    it('detects cgroup v1', function () {
        $fileReader = new FakeEnvironmentFileReader([
            '/proc/self/cgroup' => "12:cpu:/user.slice\n11:memory:/user.slice\n",
        ]);

        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->cgroup->version)->toBe(CgroupVersion::V1);
        expect($snapshot->cgroup->cpuPath)->toContain('/sys/fs/cgroup/cpu');
        expect($snapshot->cgroup->memoryPath)->toContain('/sys/fs/cgroup/memory');
    });

    it('detects no cgroup when not available', function () {
        $fileReader = new FakeEnvironmentFileReader;

        $detector = new LinuxEnvironmentDetector($fileReader);
        $result = $detector->detect();

        $snapshot = $result->getValue();
        expect($snapshot->cgroup->version)->toBe(CgroupVersion::None);
        expect($snapshot->cgroup->cpuPath)->toBeNull();
        expect($snapshot->cgroup->memoryPath)->toBeNull();
    });

    it('uses default dependencies when none provided', function () {
        $detector = new LinuxEnvironmentDetector;
        expect($detector)->toBeInstanceOf(LinuxEnvironmentDetector::class);
    });
});
