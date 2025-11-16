# Changelog

All notable changes to `system-metrics` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.5.0 - 2025-01-XX

### Added
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

### Changed
- Updated README with System Uptime section including examples and use cases

### Technical Details
- PHPStan Level 9 compliance maintained (0 errors)
- Human-readable format: "5 days, 3 hours, 42 minutes"
- Proper singular/plural forms ("1 day" vs "2 days")
- Immutable readonly DTO
- Railway-oriented programming with Result<T> pattern

## 1.4.0 - 2025-01-XX

### Added
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
- 12 new comprehensive unit tests for container metrics (519 total tests, 1341 assertions)

### Changed
- Updated README with Container Metrics section including Docker/Kubernetes examples
- Added cgroup requirements to Linux platform documentation

### Technical Details
- PHPStan Level 9 compliance maintained (0 errors)
- Cgroup v1 and v2 auto-detection via `/sys/fs/cgroup/cgroup.controllers`
- CPU usage calculated using deltas (microseconds for v2, nanoseconds for v1)
- Unrealistic memory limits (> 8 EiB) treated as "no limit"
- Graceful handling when cgroups not available (returns CgroupVersion::NONE)
- Container-aware: reports actual container limits, not host resources
- Cache for CPU usage deltas to ensure accurate utilization percentages

## 1.3.0 - 2025-01-XX

### Added
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
- **SystemOverview Integration**: Storage and network included in system overview
  - `SystemMetrics::overview()` now includes storage and network snapshots
  - `SystemOverview` DTO expanded with storage and network properties
- 110 new comprehensive unit tests for storage and network (507 total tests, 1308 assertions)

### Changed
- Updated README with Storage Metrics and Network Metrics sections including usage examples
- Updated SystemOverview to include storage and network data
- Added `/proc/mounts`, `/proc/diskstats`, `/proc/net/dev`, `/proc/net/tcp`, `/proc/net/udp` to Linux requirements
- Added `df`, `iostat`, `netstat` commands to macOS command list

### Technical Details
- PHPStan Level 9 compliance maintained (0 errors)
- All DTOs use readonly classes for immutability
- Railway-oriented programming with Result<T> pattern
- Storage and network metrics are cumulative counters (since boot)
- Disk I/O sectors automatically converted to bytes (512 bytes/sector)
- Network interfaces include link state (up/down) and MTU information
- Partition devices automatically filtered (only whole disks reported)
- Connection statistics may be null on platforms without support
- Graceful handling of permission errors on restricted files

## 1.2.0 - 2025-01-XX

### Added
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
- 28 new comprehensive unit tests for load average (299 total tests, 723 assertions)

### Changed
- Updated README with Load Average section including usage examples and interpretation guide
- Added `/proc/loadavg` to Linux permission requirements documentation
- Added `sysctl vm.loadavg` to macOS command list in documentation

### Technical Details
- PHPStan Level 9 compliance maintained (0 errors)
- All DTOs use readonly classes for immutability
- Railway-oriented programming with Result<T> pattern
- Load average values are raw counters (number of processes in run queue)
- Normalization divides by core count to show system capacity (0-1.0 scale)
- Percentage helpers multiply normalized values by 100 for easier interpretation
- Graceful handling of zero core count edge case

## 1.1.0 - 2025-01-XX

### Added
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
- 52 new comprehensive unit tests for process monitoring (225 total tests, 547 assertions)

### Changed
- Updated README with process monitoring documentation and examples
- Removed "Process-Level Metrics not available" limitation from documentation

### Technical Details
- PHPStan Level 9 compliance maintained (0 errors)
- All DTOs use readonly classes for immutability
- Railway-oriented programming with Result<T> pattern
- Platform-specific implementations with graceful fallback
- Best-effort child process enumeration

## 1.0.0 - 2025-01-XX

### Added
- Initial production release
- Environment detection with comprehensive OS, kernel, and architecture information
- Virtualization detection (KVM, QEMU, VMware, Hyper-V, VirtualBox, Parallels, etc.)
- Containerization detection (Docker, Kubernetes, containerd, cri-o, Podman, LXC)
- Cgroup v1 and v2 detection with paths
- CPU metrics with raw time counters (user, nice, system, idle, iowait, irq, softirq, steal)
- Per-core CPU metrics support
- Memory metrics with total, free, available, used, buffers, and cached information
- Swap memory metrics (total, free, used)
- Result<T> pattern for explicit error handling without exceptions
- Composite sources with graceful fallback mechanism
- Immutable DTOs using PHP 8.3 readonly classes
- Action pattern for well-defined use cases
- Interface-driven architecture for swappable implementations
- Support for Linux (all distributions)
- Support for macOS (Intel and Apple Silicon)
- Helper methods on DTOs (total(), busy(), usedPercentage(), etc.)
- SystemMetrics facade for simplified API
- Comprehensive test suite (94 tests, 238 assertions)
- PHPStan Level 5 static analysis compliance
- Full documentation (README, CLAUDE.md, PRD-IMPLEMENTATION.md)

### Technical Details
- PHP 8.3+ requirement for modern readonly class support
- Pure PHP implementation with no external dependencies
- Strict types throughout (`declare(strict_types=1)`)
- PSR-4 autoloading
- Laravel Pint for code style enforcement
- Pest v4 for testing

### Platform Support
- **Linux**: Full support via /proc and /sys filesystems
- **macOS**: Full support via sysctl and vm_stat with graceful degradation
- **Windows**: Not supported (will return appropriate errors)

### Known Limitations
- Modern macOS (especially Apple Silicon) may return zero values for CPU metrics due to deprecated kern.cp_time sysctl
- macOS swap metrics are best-effort due to dynamic swap management
- Container environments may have restricted access to certain metrics
