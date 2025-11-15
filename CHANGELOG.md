# Changelog

All notable changes to `system-metrics` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
