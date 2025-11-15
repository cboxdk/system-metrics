# Changelog

All notable changes to `system-metrics` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
