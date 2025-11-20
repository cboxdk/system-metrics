<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Environment;

use FFI;
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
use PHPeek\SystemMetrics\DTO\Environment\VirtualizationVendor;
use PHPeek\SystemMetrics\DTO\Result;

/**
 * Detects environment information on Windows systems using FFI.
 *
 * Uses Win32 API for virtualization detection:
 * - Registry access via RegOpenKeyEx/RegQueryValueEx
 * - System information via GetSystemInfo
 * - Environment variables for container detection
 * - File system markers for Docker detection
 *
 * Detection methods:
 * 1. Registry: HKLM\HARDWARE\DESCRIPTION\System\SystemBiosVersion
 * 2. Registry: HKLM\SOFTWARE\Microsoft\Virtual Machine\Guest\Parameters
 * 3. Environment variables: DOCKER_CONTAINER, VBOX_USER_HOME
 * 4. File markers: C:\.dockerenv
 */
final class WindowsEnvironmentDetector implements EnvironmentDetector
{
    /** @var FFI|null Cached FFI instance */
    private static ?FFI $ffi = null;

    // Registry constants
    private const HKEY_LOCAL_MACHINE = 0x80000002;

    private const KEY_READ = 0x20019;

    private const REG_SZ = 1;

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
        $name = 'Windows';
        $version = php_uname('r'); // Fallback to PHP's detection

        // Try to get Windows version from registry
        $versionFromRegistry = $this->readRegistryString(
            'SOFTWARE\\Microsoft\\Windows NT\\CurrentVersion',
            'DisplayVersion'
        );

        if ($versionFromRegistry !== null) {
            $version = $versionFromRegistry;
        }

        return new OperatingSystem(
            family: OsFamily::Windows,
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
            in_array($raw, ['x86_64', 'AMD64', 'amd64']) => ArchitectureKind::X86_64,
            in_array($raw, ['ARM64', 'arm64']) => ArchitectureKind::Arm64,
            in_array($raw, ['x86', 'i386', 'i686']) => ArchitectureKind::X86,
            default => ArchitectureKind::Other,
        };

        return new Architecture(kind: $kind, raw: $raw);
    }

    private function detectVirtualization(): Virtualization
    {
        // Method 1: Check BIOS version from registry
        $biosVersion = $this->readRegistryString(
            'HARDWARE\\DESCRIPTION\\System',
            'SystemBiosVersion'
        );

        if ($biosVersion !== null) {
            $biosLower = strtolower($biosVersion);

            // Hyper-V detection
            if (str_contains($biosLower, 'hyper-v') || str_contains($biosLower, 'microsoft corporation')) {
                // Additional check for Hyper-V guest parameters
                $hvGuestParams = $this->readRegistryString(
                    'SOFTWARE\\Microsoft\\Virtual Machine\\Guest\\Parameters',
                    'HostName'
                );

                if ($hvGuestParams !== null) {
                    return new Virtualization(
                        type: VirtualizationType::VirtualMachine,
                        vendor: VirtualizationVendor::HyperV,
                        rawIdentifier: 'registry: Virtual Machine Guest Parameters',
                    );
                }
            }

            // VMware detection
            if (str_contains($biosLower, 'vmware')) {
                return new Virtualization(
                    type: VirtualizationType::VirtualMachine,
                    vendor: VirtualizationVendor::VMware,
                    rawIdentifier: "registry: BIOS={$biosVersion}",
                );
            }

            // VirtualBox detection
            if (str_contains($biosLower, 'vbox') || str_contains($biosLower, 'virtualbox')) {
                return new Virtualization(
                    type: VirtualizationType::VirtualMachine,
                    vendor: VirtualizationVendor::VirtualBox,
                    rawIdentifier: "registry: BIOS={$biosVersion}",
                );
            }

            // KVM/QEMU detection
            if (str_contains($biosLower, 'qemu') || str_contains($biosLower, 'kvm')) {
                return new Virtualization(
                    type: VirtualizationType::VirtualMachine,
                    vendor: VirtualizationVendor::KVM,
                    rawIdentifier: "registry: BIOS={$biosVersion}",
                );
            }
        }

        // Method 2: Check system manufacturer
        $manufacturer = $this->readRegistryString(
            'HARDWARE\\DESCRIPTION\\System\\BIOS',
            'SystemManufacturer'
        );

        if ($manufacturer !== null) {
            $mfgLower = strtolower($manufacturer);

            if (str_contains($mfgLower, 'microsoft corporation') || str_contains($mfgLower, 'hyper-v')) {
                return new Virtualization(
                    type: VirtualizationType::VirtualMachine,
                    vendor: VirtualizationVendor::HyperV,
                    rawIdentifier: "registry: Manufacturer={$manufacturer}",
                );
            }

            if (str_contains($mfgLower, 'vmware')) {
                return new Virtualization(
                    type: VirtualizationType::VirtualMachine,
                    vendor: VirtualizationVendor::VMware,
                    rawIdentifier: "registry: Manufacturer={$manufacturer}",
                );
            }

            if (str_contains($mfgLower, 'innotek') || str_contains($mfgLower, 'oracle')) {
                return new Virtualization(
                    type: VirtualizationType::VirtualMachine,
                    vendor: VirtualizationVendor::VirtualBox,
                    rawIdentifier: "registry: Manufacturer={$manufacturer}",
                );
            }

            if (str_contains($mfgLower, 'qemu') || str_contains($mfgLower, 'red hat')) {
                return new Virtualization(
                    type: VirtualizationType::VirtualMachine,
                    vendor: VirtualizationVendor::KVM,
                    rawIdentifier: "registry: Manufacturer={$manufacturer}",
                );
            }
        }

        // Method 3: Check environment variables
        if (getenv('VBOX_USER_HOME') !== false) {
            return new Virtualization(
                type: VirtualizationType::VirtualMachine,
                vendor: VirtualizationVendor::VirtualBox,
                rawIdentifier: 'env: VBOX_USER_HOME',
            );
        }

        // No virtualization detected
        return new Virtualization(
            type: VirtualizationType::BareMetal,
            vendor: VirtualizationVendor::Unknown,
            rawIdentifier: null,
        );
    }

    private function detectContainerization(): Containerization
    {
        // Check for Docker environment variable
        if (getenv('DOCKER_CONTAINER') !== false) {
            return new Containerization(
                type: ContainerType::Docker,
                runtime: 'docker',
                insideContainer: true,
                rawIdentifier: 'env: DOCKER_CONTAINER',
            );
        }

        // Check for .dockerenv file (Windows containers)
        if (file_exists('C:\\.dockerenv')) {
            return new Containerization(
                type: ContainerType::Docker,
                runtime: 'docker',
                insideContainer: true,
                rawIdentifier: 'C:\\.dockerenv',
            );
        }

        // Check for Windows container via registry
        $containerRuntime = $this->readRegistryString(
            'SYSTEM\\CurrentControlSet\\Services\\cexecsvc',
            'DisplayName'
        );

        if ($containerRuntime !== null) {
            return new Containerization(
                type: ContainerType::Docker,
                runtime: 'docker',
                insideContainer: true,
                rawIdentifier: 'registry: cexecsvc service',
            );
        }

        // No container detected
        return new Containerization(
            type: ContainerType::None,
            runtime: null,
            insideContainer: false,
            rawIdentifier: null,
        );
    }

    private function detectCgroup(): Cgroup
    {
        // Windows doesn't use cgroups
        return new Cgroup(
            version: CgroupVersion::None,
            cpuPath: null,
            memoryPath: null,
        );
    }

    /**
     * Read a string value from Windows registry using FFI.
     */
    private function readRegistryString(string $subKey, string $valueName): ?string
    {
        try {
            $ffi = $this->getFFI();

            $hKey = $ffi->new('unsigned long');
            // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
            if ($hKey === null) {
                return null;
            }

            // Open registry key
            // @phpstan-ignore method.notFound (FFI methods defined via cdef)
            $result = $ffi->RegOpenKeyExA(
                self::HKEY_LOCAL_MACHINE,
                $subKey,
                0,
                self::KEY_READ,
                FFI::addr($hKey)
            );

            if ($result !== 0) {
                return null; // Failed to open key
            }

            // Query value size
            $dataSize = $ffi->new('unsigned long');
            // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
            if ($dataSize === null) {
                return null;
            }

            $dataType = $ffi->new('unsigned long');
            // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
            if ($dataType === null) {
                return null;
            }

            // @phpstan-ignore method.notFound (FFI methods defined via cdef)
            $result = $ffi->RegQueryValueExA(
                // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
                $hKey->cdata,
                $valueName,
                null,
                FFI::addr($dataType),
                null,
                FFI::addr($dataSize)
            );

            if ($result !== 0) {
                // @phpstan-ignore method.notFound (FFI methods defined via cdef)
                $ffi->RegCloseKey($hKey->cdata); // @phpstan-ignore property.notFound

                return null;
            }

            // Allocate buffer and read value
            // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
            $bufferSize = (int) $dataSize->cdata;
            $buffer = $ffi->new("char[{$bufferSize}]");
            // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
            if ($buffer === null) {
                return null;
            }

            // @phpstan-ignore method.notFound (FFI methods defined via cdef)
            $result = $ffi->RegQueryValueExA(
                $hKey->cdata, // @phpstan-ignore property.notFound
                $valueName,
                null,
                FFI::addr($dataType),
                $buffer,
                FFI::addr($dataSize)
            );

            // @phpstan-ignore method.notFound (FFI methods defined via cdef)
            $ffi->RegCloseKey($hKey->cdata); // @phpstan-ignore property.notFound

            if ($result !== 0) {
                return null;
            }

            // Check if it's a string type (REG_SZ)
            // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
            if ((int) $dataType->cdata !== self::REG_SZ) {
                return null;
            }

            return FFI::string($buffer);

        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get or create FFI instance (cached for performance).
     */
    private function getFFI(): FFI
    {
        if (self::$ffi === null) {
            self::$ffi = FFI::cdef('
                // Registry functions
                int RegOpenKeyExA(
                    unsigned long hKey,
                    const char* lpSubKey,
                    unsigned long ulOptions,
                    unsigned long samDesired,
                    unsigned long* phkResult
                );

                int RegQueryValueExA(
                    unsigned long hKey,
                    const char* lpValueName,
                    unsigned long* lpReserved,
                    unsigned long* lpType,
                    unsigned char* lpData,
                    unsigned long* lpcbData
                );

                int RegCloseKey(unsigned long hKey);
            ', 'advapi32.dll');
        }

        return self::$ffi;
    }
}
