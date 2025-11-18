# PHPeek System Metrics

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gophpeek/system-metrics.svg?style=flat-square)](https://packagist.org/packages/gophpeek/system-metrics)
[![Tests](https://img.shields.io/github/actions/workflow/status/gophpeek/system-metrics/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/gophpeek/system-metrics/actions/workflows/run-tests.yml)
[![PHPStan Level 9](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg?style=flat-square)](https://phpstan.org/)
[![Total Downloads](https://img.shields.io/packagist/dt/gophpeek/system-metrics.svg?style=flat-square)](https://packagist.org/packages/gophpeek/system-metrics)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3+-blue.svg?style=flat-square)](https://www.php.net)

**Get real-time system metrics from Linux and macOS in pure PHP.** No extensions, no dependencies, just clean type-safe access to CPU, memory, storage, network, and container metrics.

```php
use PHPeek\SystemMetrics\SystemMetrics;

$overview = SystemMetrics::overview()->getValue();

echo "OS: {$overview->environment->os->name}\n";
echo "CPU Cores: {$overview->cpu->coreCount()}\n";
echo "Memory: " . round($overview->memory->usedPercentage(), 1) . "%\n";
```

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Documentation](#documentation)
- [Testing](#testing)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Security](#security)
- [Credits](#credits)
- [License](#license)

## Features

**✨ Pure PHP Implementation**
- No PHP extensions required
- No Composer dependencies
- Works out of the box on any Linux or macOS system

**🔒 Type-Safe with Modern PHP**
- Built for PHP 8.3+ with readonly classes
- Strict types everywhere (`declare(strict_types=1)`)
- Full PHPStan Level 9 compliance

**🎯 Explicit Error Handling**
- Result<T> pattern instead of exceptions
- Explicit success/failure handling at compile time
- No uncaught exceptions in production

**📊 Comprehensive Metrics**
- Environment detection (OS, kernel, architecture, virtualization, containers)
- CPU metrics (raw time counters, per-core data, usage calculations)
- Memory metrics (physical RAM, swap, buffers, cache)
- Load average with per-core normalization
- System uptime tracking
- Storage metrics (filesystem usage, disk I/O)
- Network metrics (interface stats, connections)
- Container metrics (cgroup v1/v2, Docker, Kubernetes)
- Process metrics (individual and group monitoring)
- Unified limits API (environment-aware resource limits)

**🏗️ Production-Ready**
- 89.9% test coverage
- PSR-12 code style via Laravel Pint
- Graceful degradation when APIs unavailable
- Performance optimized with static data caching

## Requirements

- **PHP 8.3 or higher** (uses readonly classes)
- **Linux or macOS** (Windows not supported)
- **Standard system access**:
  - Linux: Read access to `/proc`, `/sys` filesystems
  - macOS: Access to `sysctl`, `vm_stat`, `sw_vers` commands

**Note:** No special permissions or root access required.

## Installation

```bash
composer require gophpeek/system-metrics
```

## Quick Start

### Complete System Overview

```php
use PHPeek\SystemMetrics\SystemMetrics;

$result = SystemMetrics::overview();

if ($result->isSuccess()) {
    $overview = $result->getValue();

    // Environment
    echo "OS: {$overview->environment->os->name} {$overview->environment->os->version}\n";
    echo "Architecture: {$overview->environment->architecture->kind->value}\n";

    // CPU
    echo "CPU Cores: {$overview->cpu->coreCount()}\n";

    // Memory
    $usedGB = round($overview->memory->usedBytes / 1024**3, 2);
    echo "Memory Used: {$usedGB} GB\n";
    echo "Memory Usage: " . round($overview->memory->usedPercentage(), 1) . "%\n";

    // Load Average
    echo "Load Average (1 min): {$overview->loadAverage->oneMinute}\n";
}
```

### Individual Metrics

```php
// Environment detection
$env = SystemMetrics::environment()->getValue();
echo "OS: {$env->os->family->value}\n";

// CPU metrics
$cpu = SystemMetrics::cpu()->getValue();
echo "CPU Cores: {$cpu->coreCount()}\n";

// Memory metrics
$mem = SystemMetrics::memory()->getValue();
echo "Memory: " . round($mem->usedPercentage(), 1) . "%\n";

// Load average
$load = SystemMetrics::loadAverage()->getValue();
echo "Load (1 min): {$load->oneMinute}\n";

// Storage metrics
$storage = SystemMetrics::storage()->getValue();
echo "Storage: " . round($storage->usedPercentage(), 1) . "%\n";

// Network metrics
$network = SystemMetrics::network()->getValue();
echo "Interfaces: " . count($network->interfaces) . "\n";

// Container metrics (cgroups)
$container = SystemMetrics::container()->getValue();
if ($container->hasCpuLimit()) {
    echo "Container CPU limit: {$container->cpuQuota} cores\n";
}

// Unified limits (environment-aware)
$limits = SystemMetrics::limits()->getValue();
echo "Available CPU: {$limits->availableCpuCores()} cores\n";
echo "Available Memory: " . round($limits->availableMemoryBytes() / 1024**3, 2) . " GB\n";

// Process monitoring
$process = ProcessMetrics::snapshot(getmypid())->getValue();
echo "Process Memory: " . round($process->resources->memoryRssBytes / 1024**2, 2) . " MB\n";
```

### CPU Usage Percentage

CPU metrics return raw time counters. To calculate usage percentage:

```php
// Convenience method (blocks for 1 second)
$delta = SystemMetrics::cpuUsage(1.0)->getValue();
echo "CPU Usage: " . round($delta->usagePercentage(), 1) . "%\n";

// Or manually with two snapshots
$snap1 = SystemMetrics::cpu()->getValue();
sleep(2);
$snap2 = SystemMetrics::cpu()->getValue();
$delta = CpuSnapshot::calculateDelta($snap1, $snap2);
echo "CPU Usage: " . round($delta->usagePercentage(), 1) . "%\n";
```

### Error Handling

All methods return `Result<T>` for explicit error handling:

```php
$result = SystemMetrics::memory();

if ($result->isSuccess()) {
    $memory = $result->getValue();
    echo "Memory: " . round($memory->usedPercentage(), 1) . "%\n";
} else {
    echo "Error: " . $result->getError()->getMessage() . "\n";
}

// Or use functional style
SystemMetrics::cpu()
    ->onSuccess(fn($cpu) => echo "CPU: {$cpu->coreCount()} cores\n")
    ->onFailure(fn($err) => error_log($err->getMessage()));
```

## Documentation

Comprehensive documentation is available in the [docs/](docs/) directory:

### Getting Started
- **[Introduction](docs/introduction.md)** - Overview and key features
- **[Installation](docs/installation.md)** - Installation and setup
- **[Quick Start](docs/quickstart.md)** - 30-second working example

### Basic Usage
- **[Environment Detection](docs/basic-usage/environment-detection.md)** - OS, kernel, architecture, containers
- **[CPU Metrics](docs/basic-usage/cpu-metrics.md)** - CPU time counters and core data
- **[Memory Metrics](docs/basic-usage/memory-metrics.md)** - Physical RAM and swap
- **[Load Average](docs/basic-usage/load-average.md)** - System load metrics
- **[System Uptime](docs/basic-usage/uptime.md)** - Boot time tracking
- **[Storage Metrics](docs/basic-usage/storage-metrics.md)** - Filesystem and disk I/O
- **[Network Metrics](docs/basic-usage/network-metrics.md)** - Interface statistics
- **[System Overview](docs/basic-usage/system-overview.md)** - Complete snapshot

### Advanced Features
- **[Container Metrics](docs/advanced-usage/container-metrics.md)** - Cgroup v1/v2, Docker, Kubernetes
- **[Process Metrics](docs/advanced-usage/process-metrics.md)** - Process monitoring and tracking
- **[Unified Limits](docs/advanced-usage/unified-limits.md)** - Environment-aware resource limits
- **[CPU Usage Calculation](docs/advanced-usage/cpu-usage-calculation.md)** - Delta between snapshots
- **[Error Handling](docs/advanced-usage/error-handling.md)** - Result<T> pattern deep dive
- **[Custom Implementations](docs/advanced-usage/custom-implementations.md)** - Extend with custom sources

### Architecture
- **[Design Principles](docs/architecture/design-principles.md)** - Architectural philosophy
- **[Result Pattern](docs/architecture/result-pattern.md)** - Error handling approach
- **[Composite Sources](docs/architecture/composite-sources.md)** - Fallback logic
- **[Immutable DTOs](docs/architecture/immutable-dtos.md)** - Data structures
- **[Action Pattern](docs/architecture/action-pattern.md)** - Use case encapsulation
- **[Performance Caching](docs/architecture/performance-caching.md)** - Optimization strategies

### Platform Support
- **[Linux](docs/platform-support/linux.md)** - Linux-specific implementation
- **[macOS](docs/platform-support/macos.md)** - macOS-specific implementation
- **[Comparison](docs/platform-support/comparison.md)** - Feature parity table

### Reference
- **[API Reference](docs/api-reference.md)** - Complete method documentation
- **[Testing Guide](docs/testing.md)** - Running tests and coverage
- **[Roadmap](docs/roadmap.md)** - Planned features

## Testing

```bash
# Run tests
composer test

# With coverage
composer test-coverage

# Static analysis
composer analyse

# Code style
composer format
```

See [Testing Guide](docs/testing.md) for details.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

Please review [our security policy](SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Sylvester Damgaard](https://github.com/sylvesterdamgaard)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
