# Environment Detection

Detect operating system information, architecture, virtualization, containers, and cgroup configuration.

## Overview

Environment detection provides comprehensive information about the system your code is running on. The results are automatically cached after the first call for performance.

```php
use PHPeek\SystemMetrics\SystemMetrics;

$env = SystemMetrics::environment()->getValue();
```

## Operating System Information

### OS Family, Name, and Version

```php
// OS Family (enum)
echo "OS Family: {$env->os->family->value}\n";
// Values: "linux" or "macos"

// OS Name (string)
echo "OS Name: {$env->os->name}\n";
// Examples: "Ubuntu", "Debian", "CentOS", "macOS", "Red Hat Enterprise Linux"

// OS Version (string)
echo "OS Version: {$env->os->version}\n";
// Examples: "22.04", "11", "8.5", "26.0.1"
```

**Linux examples:**
- Ubuntu: name="Ubuntu", version="22.04"
- Debian: name="Debian GNU/Linux", version="11"
- CentOS: name="CentOS Linux", version="7"
- Alpine: name="Alpine Linux", version="3.18.0"

**macOS examples:**
- macOS: name="macOS", version="26.0.1" (Darwin version)

### Kernel Information

```php
// Kernel release
echo "Kernel: {$env->kernel->release}\n";
// Examples: "5.15.0-91-generic", "6.1.0-13-amd64", "23.0.0"

// Kernel version string
echo "Kernel Version: {$env->kernel->version}\n";
// Example: "#101-Ubuntu SMP Tue Nov 14 13:30:08 UTC 2023"

// Kernel name
echo "Kernel Name: {$env->kernel->name}\n";
// Values: "Linux" or "Darwin"
```

## Architecture Detection

```php
// Architecture kind (enum)
echo "Architecture: {$env->architecture->kind->value}\n";
// Values: "x86_64", "aarch64", "arm64", "i686", "i386", "unknown"

// Raw architecture string from system
echo "Raw: {$env->architecture->raw}\n";
// Examples: "x86_64", "aarch64", "arm64", "armv7l"
```

**Common architectures:**
- `x86_64`: 64-bit Intel/AMD (most common for servers)
- `aarch64` / `arm64`: 64-bit ARM (Apple Silicon, AWS Graviton)
- `i686` / `i386`: 32-bit x86 (legacy systems)

## Virtualization Detection

Detects if running on bare metal, in a virtual machine, or the VM vendor.

```php
// Virtualization type (enum)
echo "Type: {$env->virtualization->type->value}\n";
// Values: "bare_metal", "virtual_machine", "container", "unknown"

// Virtualization vendor (string|null)
if ($env->virtualization->vendor !== null) {
    echo "Vendor: {$env->virtualization->vendor}\n";
}
// Examples: "KVM", "VMware", "VirtualBox", "Xen", "Hyper-V", "Parallels", "QEMU"
```

**Detection sources (Linux):**
- `/sys/hypervisor/type` - Hypervisor type
- `/sys/class/dmi/id/product_name` - DMI product name
- `/sys/class/dmi/id/sys_vendor` - System vendor
- `/proc/cpuinfo` - CPU model and features

**Examples:**
```php
// Bare metal server
$env->virtualization->type === VirtualizationType::BARE_METAL;
$env->virtualization->vendor === null;

// KVM virtual machine
$env->virtualization->type === VirtualizationType::VIRTUAL_MACHINE;
$env->virtualization->vendor === "KVM";

// VMware ESXi
$env->virtualization->type === VirtualizationType::VIRTUAL_MACHINE;
$env->virtualization->vendor === "VMware";
```

## Container Detection

Detects if running inside a container (Docker, Kubernetes, etc.).

```php
// Is inside container? (boolean)
if ($env->containerization->insideContainer) {
    echo "Running in a container\n";
}

// Container type (enum)
echo "Container: {$env->containerization->type->value}\n";
// Values: "docker", "podman", "lxc", "kubernetes", "systemd_nspawn", "none", "unknown"
```

**Detection methods (Linux):**
- Checks `/.dockerenv` file (Docker)
- Parses `/proc/self/cgroup` for container indicators
- Checks `/run/secrets/kubernetes.io` (Kubernetes)
- Looks for `container=` in `/proc/1/environ`

**Examples:**
```php
// Docker container
$env->containerization->insideContainer === true;
$env->containerization->type === ContainerType::DOCKER;

// Kubernetes pod
$env->containerization->insideContainer === true;
$env->containerization->type === ContainerType::KUBERNETES;

// Not containerized
$env->containerization->insideContainer === false;
$env->containerization->type === ContainerType::NONE;
```

## Cgroup Detection

Detects cgroup version and paths for CPU and memory controllers.

```php
// Cgroup version (enum)
echo "Cgroup: {$env->cgroup->version->value}\n";
// Values: "v1", "v2", "none", "unknown"

// Cgroup paths (string|null)
if ($env->cgroup->cpuPath !== null) {
    echo "CPU cgroup: {$env->cgroup->cpuPath}\n";
}
if ($env->cgroup->memoryPath !== null) {
    echo "Memory cgroup: {$env->cgroup->memoryPath}\n";
}
```

**Cgroup versions:**
- **v1 (legacy)**: Separate hierarchies for each controller
- **v2 (unified)**: Single unified hierarchy
- **none**: Not using cgroups (macOS, non-containerized)
- **unknown**: Cgroups present but version cannot be determined

**Example paths:**
```php
// Cgroup v1 (Docker)
$env->cgroup->version === CgroupVersion::V1;
$env->cgroup->cpuPath === "/docker/abc123.../";
$env->cgroup->memoryPath === "/docker/abc123.../";

// Cgroup v2 (modern systems)
$env->cgroup->version === CgroupVersion::V2;
$env->cgroup->cpuPath === "/system.slice/docker-abc123.scope";
$env->cgroup->memoryPath === "/system.slice/docker-abc123.scope";

// No cgroups (macOS)
$env->cgroup->version === CgroupVersion::NONE;
$env->cgroup->cpuPath === null;
$env->cgroup->memoryPath === null;
```

## Complete Example

```php
use PHPeek\SystemMetrics\SystemMetrics;

$result = SystemMetrics::environment();

if ($result->isFailure()) {
    echo "Error: " . $result->getError()->getMessage() . "\n";
    exit(1);
}

$env = $result->getValue();

// Operating System
echo "=== OPERATING SYSTEM ===\n";
echo "Family: {$env->os->family->value}\n";
echo "Name: {$env->os->name}\n";
echo "Version: {$env->os->version}\n";
echo "Kernel: {$env->kernel->release}\n";
echo "Architecture: {$env->architecture->kind->value}\n\n";

// Virtualization
echo "=== VIRTUALIZATION ===\n";
echo "Type: {$env->virtualization->type->value}\n";
if ($env->virtualization->vendor !== null) {
    echo "Vendor: {$env->virtualization->vendor}\n";
}
echo "\n";

// Containerization
echo "=== CONTAINERIZATION ===\n";
echo "Inside Container: " . ($env->containerization->insideContainer ? 'yes' : 'no') . "\n";
echo "Container Type: {$env->containerization->type->value}\n\n";

// Cgroups
echo "=== CGROUPS ===\n";
echo "Version: {$env->cgroup->version->value}\n";
if ($env->cgroup->cpuPath !== null) {
    echo "CPU Path: {$env->cgroup->cpuPath}\n";
}
if ($env->cgroup->memoryPath !== null) {
    echo "Memory Path: {$env->cgroup->memoryPath}\n";
}
```

**Output example (Docker container on Linux):**
```
=== OPERATING SYSTEM ===
Family: linux
Name: Ubuntu
Version: 22.04
Kernel: 5.15.0-91-generic
Architecture: x86_64

=== VIRTUALIZATION ===
Type: virtual_machine
Vendor: KVM

=== CONTAINERIZATION ===
Inside Container: yes
Container Type: docker

=== CGROUPS ===
Version: v2
CPU Path: /system.slice/docker-abc123def456.scope
Memory Path: /system.slice/docker-abc123def456.scope
```

## Performance Notes

Environment detection results are **automatically cached** after the first call. Subsequent calls return the cached result without re-reading files or executing commands.

```php
// First call: reads from system (1-5ms)
$env1 = SystemMetrics::environment()->getValue();

// Subsequent calls: returns cached result (<0.001ms)
$env2 = SystemMetrics::environment()->getValue();

// Same object instance
assert($env1 === $env2);  // true
```

To clear the cache (rarely needed):
```php
SystemMetrics::clearEnvironmentCache();
```

## Platform Differences

### Linux
- Full detection for all fields
- Reads from `/proc`, `/sys`, `/etc/os-release`
- Detects virtualization via DMI/SMBIOS
- Container detection via cgroups and filesystem markers

### macOS
- OS name, version, kernel from `sw_vers` and `uname`
- Architecture from `uname -m`
- No cgroup support (always `CgroupVersion::NONE`)
- Limited container detection (mostly returns `ContainerType::NONE`)
- Simplified virtualization detection

See [Platform Support](../platform-support/comparison.md) for detailed comparison.

## Use Cases

### Environment-Specific Configuration

```php
$env = SystemMetrics::environment()->getValue();

if ($env->os->family === OsFamily::LINUX) {
    $configPath = '/etc/myapp/config.ini';
} elseif ($env->os->family === OsFamily::MACOS) {
    $configPath = '/usr/local/etc/myapp/config.ini';
}
```

### Container-Aware Behavior

```php
$env = SystemMetrics::environment()->getValue();

if ($env->containerization->insideContainer) {
    // Use container-specific limits, not host resources
    $limits = SystemMetrics::container()->getValue();
    $maxMemory = $limits->memoryLimitBytes;
} else {
    // Use host memory
    $memory = SystemMetrics::memory()->getValue();
    $maxMemory = $memory->totalBytes;
}
```

### Architecture-Specific Optimizations

```php
$env = SystemMetrics::environment()->getValue();

match ($env->architecture->kind) {
    ArchitectureKind::X86_64 => useX86Optimizations(),
    ArchitectureKind::ARM64, ArchitectureKind::AARCH64 => useArmOptimizations(),
    default => useGenericImplementation(),
};
```

## Related Documentation

- [Container Metrics](../advanced-usage/container-metrics.md) - Cgroup limits and usage
- [Unified Limits API](../advanced-usage/unified-limits.md) - Environment-aware resource limits
- [Platform Support: Linux](../platform-support/linux.md) - Linux-specific details
- [Platform Support: macOS](../platform-support/macos.md) - macOS-specific details
