# Introduction

**PHPeek System Metrics** is a modern PHP library for accessing low-level system metrics from Linux and macOS platforms. Get real-time CPU, memory, storage, network, and container metrics in pure PHP—no extensions, no dependencies, just clean type-safe access to system data.

## Overview

SystemMetrics provides a clean, type-safe API for monitoring system resources through immutable DTOs and explicit error handling. Built for production environments, it offers comprehensive metrics collection with graceful degradation when system APIs are unavailable.

```php
use PHPeek\SystemMetrics\SystemMetrics;

$result = SystemMetrics::overview();
$overview = $result->getValue();

echo "OS: {$overview->environment->os->name}\n";
echo "CPU Cores: {$overview->cpu->coreCount()}\n";
echo "Memory: " . round($overview->memory->usedPercentage(), 1) . "%\n";
```

## Key Features

### ✅ Pure PHP Implementation
- No PHP extensions required
- No Composer dependencies
- Works out of the box on any Linux or macOS system
- Reads directly from `/proc`, `/sys` (Linux) and system commands (macOS)

### ✅ Type-Safe with Modern PHP
- Built for PHP 8.3+ with readonly classes
- Strict types everywhere with `declare(strict_types=1)`
- Immutable DTOs prevent accidental mutations
- Full PHPStan Level 9 compliance

### ✅ Explicit Error Handling
- Result<T> pattern instead of exceptions
- Explicit success/failure handling at compile time
- Functional programming style with `map()`, `onSuccess()`, `onFailure()`
- No uncaught exceptions in production

### ✅ Comprehensive Metrics
- **Environment Detection**: OS, kernel, architecture, virtualization, containers
- **CPU Metrics**: Raw time counters, per-core data, usage calculations
- **Memory Metrics**: Physical RAM, swap, buffers, cache
- **Load Average**: System load with per-core normalization
- **System Uptime**: Boot time tracking with human-readable format
- **Storage Metrics**: Filesystem usage, disk I/O statistics
- **Network Metrics**: Interface statistics, connection tracking
- **Container Metrics**: Cgroup v1/v2 support, Docker/Kubernetes aware
- **Process Metrics**: Individual process and process group monitoring
- **Unified Limits**: Environment-aware resource limits (host vs container)

### ✅ Production-Ready
- 89.9% test coverage with comprehensive test suite
- PSR-12 code style via Laravel Pint
- Graceful degradation when APIs unavailable
- Performance optimized with static data caching
- Cross-platform support (Linux & macOS)

## What Makes It Different

### Explicit Over Implicit
Unlike traditional monitoring libraries that throw exceptions on errors, SystemMetrics returns `Result<T>` objects that force you to handle both success and failure cases explicitly. This prevents production failures from uncaught exceptions.

### Immutable by Design
All data structures are readonly classes created with PHP 8.3's modern features. Once created, values cannot be modified, preventing entire classes of bugs related to state mutation.

### Graceful Degradation
Uses the Composite pattern with fallback sources. When a system API is unavailable (like `kern.cp_time` on Apple Silicon), the library returns valid zero-value structures instead of failing entirely.

### Container-Aware
Automatically detects when running inside Docker or Kubernetes and respects cgroup limits rather than reporting host resources. Critical for accurate monitoring in containerized environments.

## Requirements

- **PHP 8.3 or higher** - Uses readonly classes and modern type system
- **Linux or macOS** - Windows is not supported
- **Standard system access**:
  - Linux: Read access to `/proc` and `/sys` filesystems
  - macOS: Access to `sysctl`, `vm_stat`, `sw_vers` commands (pre-installed)

**Note:** No special permissions or root access required in standard environments. The library reads from world-readable files and executes standard system commands.

## Use Cases

### Application Monitoring
Monitor your PHP application's resource usage, detect memory leaks, track CPU utilization over time.

### Auto-Scaling Decisions
Make informed scaling decisions based on actual resource limits (respecting container constraints, not just host resources).

### Health Checks
Expose system metrics via health check endpoints for monitoring systems like Prometheus, Datadog, or New Relic.

### Resource-Aware Applications
Adjust behavior based on available resources (e.g., queue worker concurrency, cache sizes, batch processing sizes).

### Container Orchestration
Detect when running in containers, respect cgroup limits, prevent OOM kills by monitoring memory utilization.

### Performance Analysis
Track CPU usage deltas, identify bottlenecks, analyze I/O patterns, monitor network bandwidth.

## Quick Links

- [Installation](installation.md) - Get started with Composer
- [Quick Start](quickstart.md) - 30-second working example
- [Basic Usage](basic-usage/system-overview.md) - Common metrics and examples
- [Advanced Features](advanced-usage/container-metrics.md) - Container metrics, process tracking, custom implementations
- [Architecture](architecture/design-principles.md) - Design philosophy and patterns
- [API Reference](api-reference.md) - Complete method documentation

## Next Steps

1. **[Install the package](installation.md)** via Composer
2. **[Try the quick start example](quickstart.md)** to see it in action
3. **[Explore specific metrics](basic-usage/system-overview.md)** you need for your application
4. **[Learn about error handling](advanced-usage/error-handling.md)** with the Result<T> pattern

## Support

- **GitHub Issues**: [Report bugs or request features](https://github.com/gophpeek/system-metrics/issues)
- **Documentation**: You're reading it!
- **Contributing**: See [CONTRIBUTING.md](../CONTRIBUTING.md) for guidelines
- **Security**: Report vulnerabilities via [SECURITY.md](../SECURITY.md)
