# Installation

## Requirements

Before installing PHPeek System Metrics, ensure your environment meets these requirements:

### PHP Version
- **PHP 8.3 or higher** (required for readonly classes)
- No PHP extensions needed
- No Composer dependencies

### Operating System
- **Linux** (any modern distribution)
- **macOS** (any version with standard system tools)
- **Windows**: Not supported

### System Access
Different platforms require different system access:

**Linux:**
- Read access to `/proc` filesystem (usually world-readable)
- Read access to `/sys` filesystem (usually world-readable)
- Access to `/etc/os-release` for OS detection
- Read access to `/proc/self/cgroup` for container detection

**macOS:**
- Access to `sysctl` command (pre-installed)
- Access to `vm_stat` command (pre-installed)
- Access to `sw_vers` command (pre-installed)
- Access to `df` and `iostat` commands (pre-installed)

**Permissions:**
- No root or sudo required in standard environments
- Standard user access is sufficient
- Container environments may have restricted access to some `/proc` paths

## Installation via Composer

Install the package via Composer:

```bash
composer require gophpeek/system-metrics
```

That's it! The library has zero dependencies and works immediately.

## Verify Installation

Create a test script to verify the installation:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use PHPeek\SystemMetrics\SystemMetrics;

$result = SystemMetrics::environment();

if ($result->isSuccess()) {
    $env = $result->getValue();
    echo "✅ PHPeek System Metrics installed successfully!\n";
    echo "OS: {$env->os->name} {$env->os->version}\n";
    echo "Architecture: {$env->architecture->kind->value}\n";
} else {
    echo "❌ Installation verification failed\n";
    echo "Error: " . $result->getError()->getMessage() . "\n";
}
```

Run it:

```bash
php verify.php
```

Expected output:
```
✅ PHPeek System Metrics installed successfully!
OS: Ubuntu 22.04
Architecture: x86_64
```

## Configuration

### No Configuration Required

The library works out of the box with no configuration files, environment variables, or setup steps. It automatically detects your operating system and uses appropriate sources.

### Optional: Custom Implementations

If you want to use custom metric sources (e.g., cached Redis values, custom parsers), you can configure them globally:

```php
use PHPeek\SystemMetrics\Config\SystemMetricsConfig;
use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;

// Example: Set custom CPU metrics source
SystemMetricsConfig::setCpuMetricsSource(new YourCustomSource());

// Example: Set custom memory metrics source
SystemMetricsConfig::setMemoryMetricsSource(new YourCustomMemorySource());

// Example: Set custom environment detector
SystemMetricsConfig::setEnvironmentDetector(new YourCustomDetector());
```

See [Custom Implementations](advanced-usage/custom-implementations.md) for details.

### Optional: Clear Environment Cache

Environment detection results are cached automatically for performance. To clear the cache:

```php
use PHPeek\SystemMetrics\SystemMetrics;

SystemMetrics::clearEnvironmentCache();
```

This is rarely needed in production and is mostly useful for testing.

## Troubleshooting

### Permission Errors

If you get permission errors:

```php
$result = SystemMetrics::cpu();
if ($result->isFailure()) {
    echo $result->getError()->getMessage();
    // "InsufficientPermissionsException: Cannot read /proc/stat"
}
```

**Solutions:**
1. Check file permissions: `ls -l /proc/stat /proc/meminfo`
2. Verify user has read access (usually everyone does)
3. In containers, ensure `/proc` is mounted (it usually is)

### File Not Found Errors

If files are missing:

```php
$result = SystemMetrics::memory();
if ($result->isFailure()) {
    // "FileNotFoundException: /proc/meminfo not found"
}
```

**Solutions:**
1. Verify you're on Linux or macOS (Windows not supported)
2. Check if `/proc` is mounted: `mount | grep proc`
3. Some minimal containers may not have full `/proc` access

### macOS CPU Metrics Return Zero

On Apple Silicon or modern macOS, CPU time counters may return zero:

```php
$cpu = SystemMetrics::cpu()->getValue();
// $cpu->total->user may be 0 on Apple Silicon
```

This is expected behavior—modern macOS deprecated the `kern.cp_time` sysctl. The library gracefully returns zero values rather than failing. For CPU monitoring on macOS, consider using system tools like `top` or `Activity Monitor`.

See [Platform Support: macOS](platform-support/macos.md) for details.

### Unsupported Operating System

On Windows or other unsupported systems:

```php
$result = SystemMetrics::cpu();
if ($result->isFailure()) {
    // "UnsupportedOperatingSystemException: Windows is not supported"
}
```

The library only supports Linux and macOS. There are no plans for Windows support.

## Development Installation

For development and contributing:

```bash
# Clone the repository
git clone https://github.com/gophpeek/system-metrics.git
cd system-metrics

# Install development dependencies
composer install

# Run tests
composer test

# Run static analysis
composer analyse

# Format code
composer format
```

See [CONTRIBUTING.md](../CONTRIBUTING.md) for full development guidelines.

## Next Steps

- **[Quick Start Guide](quickstart.md)** - See a working example in 30 seconds
- **[Basic Usage](basic-usage/system-overview.md)** - Explore available metrics
- **[Error Handling](advanced-usage/error-handling.md)** - Learn the Result<T> pattern
- **[API Reference](api-reference.md)** - Complete method documentation
