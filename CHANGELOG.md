# Changelog

All notable changes to `system-metrics` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## v1.0.0 - 2025-11-17

### What's Changed

* chore(deps): bump actions/upload-artifact from 4 to 5 by @dependabot[bot] in https://github.com/gophpeek/system-metrics/pull/4

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/gophpeek/system-metrics/pull/4

**Full Changelog**: https://github.com/gophpeek/system-metrics/compare/v0.1.0...v1.0.0

## [0.1.0] - 2025-11-16

### Added

- **CPU Usage Percentage**: Calculate CPU usage percentage over time intervals
  
  - `SystemMetrics::cpuUsage(float $intervalSeconds = 1.0)` facade method for automatic measurement
  - `CpuSnapshot::calculateDelta(CpuSnapshot $before, CpuSnapshot $after)` for manual two-snapshot calculation
  - `CpuDelta` DTO with comprehensive percentage calculations:
    - `usagePercentage()` - Overall CPU usage (0-100+, can exceed 100% on multi-core)
    - `normalizedUsagePercentage()` - Per-core average usage (0-100%)
    - `userPercentage()` - User-mode CPU time percentage
    - `systemPercentage()` - System-mode (kernel) CPU time percentage
    - `idlePercentage()` - Idle time percentage
    - `iowaitPercentage()` - I/O wait time percentage
    - `coreUsagePercentage(int $coreIndex)` - Usage for specific core
    - `busiestCore()` - Get busiest core during interval
    - `idlestCore()` - Get least busy core during interval
    
  - `CpuCoreDelta` DTO for per-core delta calculations
  - ⚠️ Important: CPU percentage requires TWO snapshots with time elapsed between them
  - Convenience method blocks execution; manual method allows non-blocking patterns
  - 15 comprehensive unit tests for CPU delta calculations
  
- **System Uptime Metrics**: Track system uptime since last boot
  
  - `SystemMetrics::uptime()` facade method
  - `UptimeSnapshot` DTO with boot time, total seconds, and timestamp
  - Helper methods: `days()`, `hours()`, `minutes()`, `totalHours()`, `totalMinutes()`, `humanReadable()`
  - Linux support via `/proc/uptime` parsing
  - macOS support via `sysctl kern.boottime`
  - `LinuxProcUptimeParser` and `MacOsSysctlBoottimeParser`
  - `LinuxProcUptimeSource`, `MacOsSysctlUptimeSource`, `CompositeUptimeSource`
  - `ReadUptimeAction` for uptime retrieval
  - 13 comprehensive unit tests for uptime
  
- **DX Finder Methods**: Developer experience enhancements for targeted metric access
  
  - **CpuSnapshot Finders**: Find specific CPU cores with smart filtering
    - `findCore(int $coreIndex)` - Find specific core by index
    - `findBusyCores(float $threshold)` - Find cores above busy threshold
    - `findIdleCores(float $threshold)` - Find cores above idle threshold
    - `busiestCore()` - Get core with highest busy percentage
    - `idlestCore()` - Get core with lowest busy percentage
    
  - **StorageSnapshot Finders**: Find specific mount points and filesystem types
    - `findMountPoint(string $path)` - Find mount containing path (most specific match)
    - `findDevice(string $device)` - Find mount by device name
    - `findByFilesystemType(FileSystemType $type)` - Find all mounts by filesystem type
    
  - **NetworkSnapshot Finders**: Find specific network interfaces with filtering
    - `findInterface(string $name)` - Find interface by exact name
    - `findByType(NetworkInterfaceType $type)` - Find interfaces by type
    - `findActiveInterfaces()` - Find all interfaces that are up
    - `findByMacAddress(string $mac)` - Find interface by MAC address
    
  - 46 comprehensive unit tests for all finder methods
  
- **Unified Limits API**: Single source for resource limits regardless of environment
  
  - `SystemMetrics::limits()` facade method for unified limits and current usage
  - `SystemLimits` DTO with complete resource limits and current consumption
  - `LimitSource` enum: HOST (bare metal/VM), CGROUP_V1, CGROUP_V2
  - **Scaling Decision Helpers**:
    - `availableCpuCores()` - Calculate available CPU cores for scaling
    - `availableMemoryBytes()` - Calculate available memory for scaling
    - `canScaleCpu(int $additionalCores)` - Check if can scale CPU by amount
    - `canScaleMemory(int $additionalBytes)` - Check if can scale memory by amount
    
  - **Utilization Helpers**:
    - `cpuUtilization()` - CPU usage as percentage (0-100+)
    - `memoryUtilization()` - Memory usage as percentage (0-100+)
    - `swapUtilization()` - Swap usage as percentage (null if no swap)
    
  - **Headroom Helpers**:
    - `cpuHeadroom()` - CPU headroom percentage (100 - utilization)
    - `memoryHeadroom()` - Memory headroom percentage (100 - utilization)
    
  - **Pressure Detection**:
    - `isMemoryPressure(float $threshold = 80.0)` - Detect memory pressure
    - `isCpuPressure(float $threshold = 80.0)` - Detect CPU pressure
    
  - **Environment Detection**:
    - `isContainerized()` - Check if running in container (cgroup v1/v2)
    
  - `CompositeSystemLimitsSource` with intelligent decision logic:
    - Checks if running in container with cgroup limits first
    - Uses cgroup limits if available (container-aware)
    - Falls back to host limits (bare metal/VM)
    - Integrates with ContainerMetricsSource, CpuMetricsSource, MemoryMetricsSource
    
  - `ReadSystemLimitsAction` for limits retrieval
  - 25 comprehensive unit tests for SystemLimits
  

### Changed

- Updated README with System Uptime section including examples and use cases
- Updated README with DX Finder Methods section showing targeted metric access
- Updated README with Unified Limits API section including vertical scaling examples

### Technical Details

- PHPStan Level 9 compliance maintained (0 errors)
  
- Human-readable uptime format: "5 days, 3 hours, 42 minutes"
  
- Proper singular/plural forms ("1 day" vs "2 days")
  
- Finder methods use smart filtering and sorting for optimal results
  
- Most specific mount point matching for nested paths
  
- Busy/idle percentage calculations based on CPU time ratios
  
- Unified limits provide current usage alongside limits for safe scaling decisions
  
- Container-aware: respects cgroup limits, not host resources when containerized
  
- Graceful handling of over-provisioned scenarios (usage > limits)
  
- Zero headroom when at or over capacity
  
- Immutable readonly DTOs throughout
  
- Railway-oriented programming with Result<T> pattern
  
- 618 total tests, 1514 assertions (15 new CPU delta tests)
  
- **Container Metrics (Cgroups)**: Full Docker/Kubernetes resource monitoring
  
  - `SystemMetrics::container()` facade method for container metrics
  - `ContainerLimits`: Complete container resource limits and usage
  - `CgroupVersion` enum: V1, V2, NONE
  - Cgroup v1 support: CPU quota (`cpu.cfs_quota_us`), memory limits (`memory.limit_in_bytes`)
  - Cgroup v2 support: CPU quota (`cpu.max`), memory limits (`memory.max`)
  - CPU usage tracking with delta calculations for accurate utilization
  - Memory usage tracking (`memory.current`, `memory.usage_in_bytes`)
  - CPU throttling detection (`nr_throttled` counter)
  - OOM kill tracking (`oom_kill`, `under_oom` counters)
  - Helper methods: `hasCpuLimit()`, `hasMemoryLimit()`, `cpuUtilizationPercentage()`, `memoryUtilizationPercentage()`
  - Helper methods: `availableCpuCores()`, `availableMemoryBytes()`, `isCpuThrottled()`, `hasOomKills()`
  - `CgroupParser`: Parse cgroup v1 and v2 files from `/sys/fs/cgroup` and `/proc/self/cgroup`
  - `LinuxCgroupMetricsSource`: Linux cgroup metrics source
  - `CompositeContainerMetricsSource`: Auto-detection with graceful fallback
  - `ReadContainerMetricsAction`: Action for container metrics retrieval
  - `ContainerMetricsSource` contract interface
  
- **Storage Metrics**: Complete filesystem and disk I/O monitoring system
  
  - `SystemMetrics::storage()` facade method for storage metrics
  - `StorageSnapshot`: Complete storage state with filesystem and I/O data
  - `MountPoint`: Filesystem mount information with usage statistics
  - `DiskIOStats`: Disk I/O counters (reads, writes, bytes, I/O time)
  - Helper methods: `usedPercentage()`, `availablePercentage()`, `totalBytes()`
  - `FileSystemType` enum: ext4, xfs, btrfs, zfs, apfs, hfs+, ntfs, fat32, tmpfs, devtmpfs
  - Linux support via `/proc/mounts` and `/proc/diskstats` parsing
  - macOS support via `df` and `iostat` commands
  - Composite source with automatic platform detection
  - `ReadStorageMetricsAction` for storage retrieval
  - Parsers: `LinuxMountsParser`, `LinuxDiskstatsParser`, `MacOsDfParser`, `MacOsIostatParser`
  - Sources: `LinuxProcStorageMetricsSource`, `MacOsDfStorageMetricsSource`, `CompositeStorageMetricsSource`
  - `StorageMetricsSource` contract interface
  
- **Network Metrics**: Complete network interface and connection monitoring system
  
  - `SystemMetrics::network()` facade method for network metrics
  - `NetworkSnapshot`: Complete network state with interfaces and connections
  - `NetworkInterface`: Interface statistics and configuration
  - `NetworkInterfaceStats`: Detailed traffic counters (bytes, packets, errors, drops)
  - `NetworkConnectionStats`: TCP/UDP connection state counters
  - Helper methods: `totalBytes()`, `totalPackets()`, `totalErrors()`, `totalDrops()`
  - `NetworkInterfaceType` enum: ethernet, wifi, loopback, bridge, vlan, vpn, cellular, bluetooth, other
  - Linux support via `/proc/net/dev` and `/proc/net/tcp|udp` parsing
  - macOS support via `netstat` commands
  - Composite source with automatic platform detection
  - `ReadNetworkMetricsAction` for network retrieval
  - Parsers: `LinuxProcNetDevParser`, `LinuxProcNetTcpParser`, `MacOsNetstatParser`, `MacOsNetstatInterfaceParser`
  - Sources: `LinuxProcNetworkMetricsSource`, `MacOsNetstatNetworkMetricsSource`, `CompositeNetworkMetricsSource`
  - `NetworkMetricsSource` contract interface
  
- **Load Average Metrics**: System load average support without requiring delta calculations
  
  - `SystemMetrics::loadAverage()` facade method for instant load metrics
  - `LoadAverageSnapshot`: Raw load average values (1, 5, 15 minute intervals)
  - `NormalizedLoadAverage`: Load normalized by core count with percentage helpers
  - Helper methods: `oneMinutePercentage()`, `fiveMinutesPercentage()`, `fifteenMinutesPercentage()`
  - `normalized(CpuSnapshot)` method for capacity percentage calculation
  - Linux support via `/proc/loadavg` parsing
  - macOS support via `sysctl vm.loadavg` command
  - Composite source with automatic platform detection
  - `ReadLoadAverageAction` for load average retrieval
  - `LinuxProcLoadavgParser` and `MacOsSysctlLoadavgParser`
  - `LinuxProcLoadAverageSource`, `MacOsSysctlLoadAverageSource`, `CompositeLoadAverageSource`
  - `LoadAverageSource` contract interface
  
- **Process-Level Monitoring**: Complete process resource tracking system
  
  - `ProcessMetrics` facade for stateful tracking with start/sample/stop workflow
  - `ProcessTracker` class for object-oriented process monitoring
  - Support for tracking individual processes and process groups (parent + children)
  - Statistical aggregation: current, peak, and average resource usage
  - Manual sampling support for detailed statistics
  - `ProcessSnapshot`: Point-in-time process metrics (PID, PPID, CPU, memory, threads)
  - `ProcessDelta`: Delta calculations with CPU usage percentage
  - `ProcessStats`: Statistical aggregation with sample count and duration
  - `ProcessGroupSnapshot`: Process group metrics with aggregation methods
  - Linux support via `/proc/{pid}/stat` parsing
  - macOS support via `ps` and `pgrep` commands
  - Composite source with automatic platform detection
  - Actions: `ReadProcessMetricsAction`, `ReadProcessGroupMetricsAction`
  
- **Environment Detection**: Comprehensive system environment information
  
  - Virtualization detection (KVM, QEMU, VMware, Hyper-V, VirtualBox, Parallels, etc.)
  - Containerization detection (Docker, Kubernetes, containerd, cri-o, Podman, LXC)
  - Cgroup v1 and v2 detection with paths
  
- **CPU Metrics**: Raw time counters with per-core support
  
  - Total and per-core CPU times (user, nice, system, idle, iowait, irq, softirq, steal)
  - Helper methods: `total()`, `busy()`, `coreCount()`
  
- **Memory Metrics**: Complete memory and swap information
  
  - Total, free, available, used, buffers, and cached memory
  - Swap memory metrics (total, free, used)
  - Helper methods: `usedPercentage()`, `availablePercentage()`
  

### Changed

- Updated README with comprehensive examples and use cases
- Added documentation for all metric types

### Technical Details

- PHP 8.3+ requirement for modern readonly class support
- PHPStan Level 9 compliance (0 errors)
- Pure PHP implementation with no external dependencies
- Strict types throughout (`declare(strict_types=1)`)
- Result<T> pattern for explicit error handling without exceptions
- Immutable DTOs using readonly classes
- Action pattern for well-defined use cases
- Interface-driven architecture for swappable implementations
- Composite sources with graceful fallback mechanism
- PSR-4 autoloading
- Laravel Pint for code style enforcement
- Pest v4 for testing framework
- 618 tests with 1514 assertions (100% pass rate)

### Platform Support

- **Linux**: Full support via /proc and /sys filesystems
- **macOS**: Full support via sysctl, vm_stat, ps, netstat, df, iostat
- **Windows**: Not supported (will return appropriate errors)

### Known Limitations

- Modern macOS (especially Apple Silicon) may return zero values for CPU metrics due to deprecated kern.cp_time sysctl
- macOS swap metrics are best-effort due to dynamic swap management
- Container environments may have restricted access to certain metrics
- CPU usage percentage requires delta calculations (two snapshots)
