# PHPeek System Metrics

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gophpeek/system-metrics.svg?style=flat-square)](https://packagist.org/packages/gophpeek/system-metrics)
[![Tests](https://img.shields.io/github/actions/workflow/status/gophpeek/system-metrics/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/gophpeek/system-metrics/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/gophpeek/system-metrics.svg?style=flat-square)](https://packagist.org/packages/gophpeek/system-metrics)

A modern PHP library for accessing low-level system metrics on Linux and macOS. Get raw CPU counters, memory statistics, environment detection, container information, and more with a clean, type-safe API powered by PHP 8.3's readonly classes.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/system-metrics.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/system-metrics)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require gophpeek/system-metrics
```

## Features

- **Environment Detection**: OS family, version, kernel info, CPU architecture, virtualization, containerization, cgroup support
- **CPU Metrics**: Raw time counters (user, system, idle, iowait, etc.) for total system and per-core
- **Memory Metrics**: Total, free, available, used, buffers, cached memory plus swap information in bytes
- **Result Pattern**: Explicit success/failure handling with type-safe Result<T> objects
- **Cross-Platform**: Native support for Linux and macOS
- **Type-Safe**: Leverages PHP 8.3 readonly classes and strict types throughout
- **Immutable DTOs**: All data transfer objects are immutable value objects
- **Interface-Driven**: Easily swap implementations or add custom sources

## Requirements

- PHP 8.3 or higher
- Linux or macOS operating system

## Design Principles

PHPeek System Metrics is built on a foundation of modern PHP best practices:

1. **Pure PHP**: No external dependencies or system extensions required - works out of the box
2. **Strict Types**: All code uses `declare(strict_types=1)` for maximum type safety
3. **Immutable DTOs**: All data transfer objects are readonly value objects that can't be modified
4. **Action Pattern**: Small, focused actions with well-defined input/output - easy to test and maintain
5. **Interface-Driven**: All core components are behind interfaces - easily swap implementations
6. **Result Pattern**: Explicit success/failure handling with `Result<T>` - no uncaught exceptions
7. **Layered Sources**: Composite pattern with fallback logic - graceful degradation when APIs unavailable

## Architecture

### Result<T> Pattern

Instead of throwing exceptions, all operations return a `Result<T>` object that explicitly represents success or failure:

```php
$result = SystemMetrics::cpu();

if ($result->isSuccess()) {
    $cpu = $result->getValue();
    // Work with CPU metrics
} else {
    $error = $result->getError();
    // Handle error gracefully
}
```

This approach provides:
- **Compile-time safety**: Forces you to handle errors explicitly
- **No uncaught exceptions**: Errors are values, not control flow
- **Functional style**: Use `map()`, `onSuccess()`, `onFailure()` for elegant error handling

### Composite Sources with Fallbacks

Each metric type uses a composite source that tries multiple implementations in order:

```
CompositeCpuMetricsSource
├── LinuxProcCpuMetricsSource (if on Linux)
├── MacOsSysctlCpuMetricsSource (if on macOS)
└── MinimalCpuMetricsSource (fallback with zeros)
```

This enables graceful degradation. For example, modern macOS systems lack the `kern.cp_time` sysctl, so the library returns valid data structures with zero values rather than failing.

### Swappable Implementations

All metric sources are configurable via `SystemMetricsConfig`:

```php
use PHPeek\SystemMetrics\Config\SystemMetricsConfig;

// Use your custom CPU source
SystemMetricsConfig::setCpuMetricsSource(new MyCustomCpuSource());

// All subsequent calls use your implementation
$cpu = SystemMetrics::cpu();
```

This makes it easy to:
- Integrate with native PHP extensions for better performance
- Add eBPF-based metrics on Linux
- Create test doubles for unit testing
- Implement platform-specific optimizations

## Usage

### Quick Start

```php
use PHPeek\SystemMetrics\SystemMetrics;

// Get complete system overview
$result = SystemMetrics::overview();

if ($result->isSuccess()) {
    $overview = $result->getValue();

    // Environment info
    echo "OS: {$overview->environment->os->name} {$overview->environment->os->version}\n";
    echo "Architecture: {$overview->environment->architecture->kind->value}\n";

    // CPU metrics
    echo "CPU Cores: {$overview->cpu->coreCount()}\n";
    echo "Total User Time: {$overview->cpu->total->user} ticks\n";

    // Memory metrics
    $memoryUsedGB = $overview->memory->usedBytes / 1024 / 1024 / 1024;
    echo "Memory Used: " . round($memoryUsedGB, 2) . " GB\n";
    echo "Memory Usage: " . round($overview->memory->usedPercentage(), 1) . "%\n";
}
```

### Individual Metrics

```php
use PHPeek\SystemMetrics\SystemMetrics;

// Environment detection
$envResult = SystemMetrics::environment();
if ($envResult->isSuccess()) {
    $env = $envResult->getValue();

    echo "OS Family: {$env->os->family->value}\n";
    echo "Kernel: {$env->kernel->release}\n";
    echo "Virtualization: {$env->virtualization->type->value}\n";

    if ($env->containerization->insideContainer) {
        echo "Running in: {$env->containerization->type->value}\n";
    }

    echo "Cgroup Version: {$env->cgroup->version->value}\n";
}

// CPU metrics
$cpuResult = SystemMetrics::cpu();
if ($cpuResult->isSuccess()) {
    $cpu = $cpuResult->getValue();

    echo "Total CPU Time: {$cpu->total->total()} ticks\n";
    echo "Busy Time: {$cpu->total->busy()} ticks\n";

    // Per-core metrics
    foreach ($cpu->perCore as $core) {
        echo "Core {$core->coreIndex}: {$core->times->user} user ticks\n";
    }
}

// Memory metrics
$memResult = SystemMetrics::memory();
if ($memResult->isSuccess()) {
    $mem = $memResult->getValue();

    $totalGB = $mem->totalBytes / 1024 / 1024 / 1024;
    $availableGB = $mem->availableBytes / 1024 / 1024 / 1024;

    echo "Total Memory: " . round($totalGB, 2) . " GB\n";
    echo "Available: " . round($availableGB, 2) . " GB\n";
    echo "Usage: " . round($mem->usedPercentage(), 1) . "%\n";

    if ($mem->swapTotalBytes > 0) {
        echo "Swap Usage: " . round($mem->swapUsedPercentage(), 1) . "%\n";
    }
}
```

### Error Handling

```php
use PHPeek\SystemMetrics\SystemMetrics;

$result = SystemMetrics::cpu();

// Pattern 1: Check and handle
if ($result->isFailure()) {
    $error = $result->getError();
    echo "Error: {$error->getMessage()}\n";
    exit(1);
}
$cpu = $result->getValue();

// Pattern 2: Use default value
$cpu = SystemMetrics::cpu()->getValueOr(null);
if ($cpu === null) {
    echo "Could not read CPU metrics\n";
}

// Pattern 3: Callbacks
SystemMetrics::memory()
    ->onSuccess(fn($mem) => echo "Memory: {$mem->totalBytes} bytes\n")
    ->onFailure(fn($err) => echo "Error: {$err->getMessage()}\n");
```

### Custom Implementations

```php
use PHPeek\SystemMetrics\Config\SystemMetricsConfig;
use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;

// Create your custom CPU metrics source
class MyCustomCpuSource implements CpuMetricsSource {
    public function read(): Result {
        // Your custom implementation
    }
}

// Configure globally
SystemMetricsConfig::setCpuMetricsSource(new MyCustomCpuSource());

// Now all calls use your custom source
$cpu = SystemMetrics::cpu();
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Sylvester Damgaard](https://github.com/sylvesterdamgaard)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
