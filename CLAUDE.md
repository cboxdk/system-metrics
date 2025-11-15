# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**PHPeek/SystemMetrics** is a modern PHP 8.3+ library for accessing low-level system metrics on Linux and macOS. It provides a clean, type-safe API for reading environment detection, CPU metrics, and memory metrics through immutable DTOs and explicit error handling.

- **Namespace**: `PHPeek\SystemMetrics`
- **Package Name**: `gophpeek/system-metrics`
- **PHP Version**: 8.3+ (strict requirement, enables readonly classes)
- **Testing Framework**: Pest v4
- **Code Style**: Laravel Pint (automated via GitHub Actions)
- **Test Coverage**: 62.9% (94 tests, 238 assertions)

## Architecture

### Design Principles

1. **Pure PHP**: No external dependencies, no system extensions required
2. **Strict Types**: All code uses `declare(strict_types=1)`
3. **Immutable DTOs**: All data transfer objects are readonly value objects
4. **Action Pattern**: Small, focused actions with well-defined input/output
5. **Interface-Driven**: All core components behind interfaces for swappability
6. **Result Pattern**: Explicit success/failure handling with `Result<T>` wrapper
7. **Layered Sources**: Composite pattern with fallback logic

### Directory Structure

```
src/
├── Contracts/              # Interface definitions
│   ├── EnvironmentDetector.php
│   ├── CpuMetricsSource.php
│   └── MemoryMetricsSource.php
├── DTO/                    # Data Transfer Objects (all readonly)
│   ├── Environment/        # OS, kernel, architecture, virtualization, containers, cgroups
│   ├── Metrics/
│   │   ├── Cpu/           # CPU times, snapshots, per-core data
│   │   └── Memory/        # Memory snapshots with bytes
│   ├── Result.php         # Result<T> pattern for error handling
│   └── SystemOverview.php # Combined snapshot
├── Actions/               # Use case implementations
│   ├── DetectEnvironmentAction.php
│   ├── ReadCpuMetricsAction.php
│   ├── ReadMemoryMetricsAction.php
│   └── SystemOverviewAction.php
├── Sources/               # OS-specific implementations
│   ├── Environment/       # Linux & macOS environment detectors
│   ├── Cpu/              # Linux /proc/stat & macOS sysctl
│   └── Memory/           # Linux /proc/meminfo & macOS vm_stat
├── Support/              # Helper classes
│   ├── FileReader.php    # Safe file reading with Result<T>
│   ├── ProcessRunner.php # Command execution with Result<T>
│   ├── OsDetector.php    # Runtime OS detection
│   └── Parser/           # Format-specific parsers
├── Config/
│   └── SystemMetricsConfig.php  # Dependency injection configuration
└── SystemMetrics.php     # Main facade

tests/
├── Unit/                 # Unit tests for parsers, DTOs, support classes
│   ├── DTO/
│   ├── Parser/
│   └── Support/
├── ExampleTest.php       # Integration tests
└── ArchTest.php          # Architecture rules (no dd/dump/ray)
```

### Key Architectural Patterns

#### 1. Result<T> Pattern

All operations that can fail return `Result<T>` instead of throwing exceptions:

```php
$result = SystemMetrics::cpu();

if ($result->isSuccess()) {
    $cpu = $result->getValue();
    echo "CPU cores: {$cpu->coreCount()}\n";
} else {
    $error = $result->getError();
    echo "Error: {$error->getMessage()}\n";
}
```

**Benefits:**
- Explicit error handling at compile time
- No uncaught exceptions
- Functional programming style with `map()`, `onSuccess()`, `onFailure()`

#### 2. Composite Pattern with Fallbacks

Each metric type uses a Composite source that tries multiple implementations:

```php
CompositeCpuMetricsSource
├── Future: PHP Extension source
├── Future: eBPF source
├── Current: LinuxProcCpuMetricsSource (if Linux)
├── Current: MacOsSysctlCpuMetricsSource (if macOS)
└── Fallback: MinimalCpuMetricsSource (zeros)
```

This enables graceful degradation when APIs are unavailable (e.g., modern macOS lacks kern.cp_time).

#### 3. Action Pattern

Each use case is encapsulated in a focused Action class:

```php
// DetectEnvironmentAction
$action = new DetectEnvironmentAction($detector);
$result = $action->execute(); // Returns Result<EnvironmentSnapshot>

// ReadCpuMetricsAction
$action = new ReadCpuMetricsAction($source);
$result = $action->execute(); // Returns Result<CpuSnapshot>
```

Actions are thin orchestrators that delegate to sources/detectors.

#### 4. Immutable DTOs with Helper Methods

All DTOs are readonly classes with calculated helper methods:

```php
final readonly class CpuTimes {
    public function __construct(
        public int $user,
        public int $system,
        public int $idle,
        // ... 5 more fields
    ) {}

    public function total(): int { /* sum all fields */ }
    public function busy(): int { /* total - idle - iowait */ }
}
```

#### 5. Interface-Driven Configuration

All sources are swappable via SystemMetricsConfig:

```php
// Default configuration
SystemMetricsConfig::setCpuMetricsSource(new CompositeCpuMetricsSource());

// Custom implementation
SystemMetricsConfig::setCpuMetricsSource(new MyCustomCpuSource());
```

## Development Commands

### Testing
```bash
# Run all tests (fast)
composer test

# Run tests with coverage report
composer test-coverage

# Run specific test file
vendor/bin/pest tests/Unit/Parser/LinuxProcStatParserTest.php

# Run tests in CI mode (strict output)
vendor/bin/pest --ci
```

### Code Quality
```bash
# Format code (Laravel Pint)
composer format

# Install dependencies
composer install

# Update dependencies
composer update
```

## API Usage

### Quick Start

```php
use PHPeek\SystemMetrics\SystemMetrics;

// Complete system overview
$result = SystemMetrics::overview();

if ($result->isSuccess()) {
    $overview = $result->getValue();

    // Environment
    echo "OS: {$overview->environment->os->name} {$overview->environment->os->version}\n";
    echo "Kernel: {$overview->environment->kernel->release}\n";
    echo "Architecture: {$overview->environment->architecture->kind->value}\n";

    // CPU
    echo "CPU Cores: {$overview->cpu->coreCount()}\n";
    echo "Total CPU Time: {$overview->cpu->total->total()} ticks\n";

    // Memory
    $usedGB = $overview->memory->usedBytes / 1024 / 1024 / 1024;
    echo "Memory Used: " . round($usedGB, 2) . " GB\n";
    echo "Memory Usage: " . round($overview->memory->usedPercentage(), 1) . "%\n";
}
```

### Individual Metrics

```php
// Environment detection only
$envResult = SystemMetrics::environment();
if ($envResult->isSuccess()) {
    $env = $envResult->getValue();
    echo "Running on: {$env->os->family->value}\n";

    if ($env->containerization->insideContainer) {
        echo "Container type: {$env->containerization->type->value}\n";
    }
}

// CPU metrics only
$cpuResult = SystemMetrics::cpu();
if ($cpuResult->isSuccess()) {
    $cpu = $cpuResult->getValue();
    echo "Busy CPU time: {$cpu->total->busy()} ticks\n";

    foreach ($cpu->perCore as $core) {
        echo "Core {$core->coreIndex}: {$core->times->user} user ticks\n";
    }
}

// Memory metrics only
$memResult = SystemMetrics::memory();
if ($memResult->isSuccess()) {
    $mem = $memResult->getValue();
    echo "Available: " . ($mem->availableBytes / 1024 / 1024 / 1024) . " GB\n";
    echo "Swap used: {$mem->swapUsedBytes} bytes\n";
}
```

### Error Handling Patterns

```php
// Pattern 1: Check and handle
$result = SystemMetrics::cpu();
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

// Pattern 4: Functional mapping
$result = SystemMetrics::cpu()->map(fn($cpu) => [
    'cores' => $cpu->coreCount(),
    'busy_percentage' => ($cpu->total->busy() / $cpu->total->total()) * 100,
]);
```

### Custom Implementations

```php
use PHPeek\SystemMetrics\Config\SystemMetricsConfig;
use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;

// Create custom CPU source
class MyCustomCpuSource implements CpuMetricsSource {
    public function read(): Result {
        // Your custom implementation (e.g., from PHP extension)
    }
}

// Configure globally
SystemMetricsConfig::setCpuMetricsSource(new MyCustomCpuSource());

// All subsequent calls use your custom source
$cpu = SystemMetrics::cpu();
```

## Development Workflow

### When Adding New Code

1. All new classes go in `src/` with namespace `PHPeek\SystemMetrics`
2. Write Pest tests in `tests/` (unit tests in `tests/Unit/`, integration in root)
3. Follow existing patterns:
   - DTOs are readonly value objects
   - Sources return `Result<T>`
   - Use dependency injection via constructor
4. Run `composer format` before committing (or let CI auto-fix)
5. Ensure tests pass: `composer test`

### Code Style Rules

- **No debugging functions**: dd, dump, ray are forbidden (enforced by ArchTest)
- **PHP 8.3 features**: Use readonly classes, constructor property promotion, match expressions
- **Strict types**: All files must have `declare(strict_types=1)`
- **PSR-4 autoloading**: Follow namespace conventions strictly
- **Pint formatting**: Code style is automated; manual formatting not needed

### Testing Practices

- Use Pest's `it()` function-based syntax
- Unit tests cover parsers, DTOs, support classes individually
- Integration tests (ExampleTest.php) test the full stack on real system APIs
- Architecture tests prevent debugging functions in production code
- Tests execute in random order to catch hidden dependencies
- Coverage reports available via `composer test-coverage`

### Platform-Specific Considerations

**Linux:**
- Uses `/proc/stat` for CPU metrics
- Uses `/proc/meminfo` for memory metrics
- Full environment detection via `/etc/os-release`, `/sys/class/dmi/id/`, `/proc/self/cgroup`

**macOS:**
- Uses `sysctl kern.cp_time` for CPU (with fallback for modern systems)
- Uses `vm_stat` and `sysctl hw.memsize` for memory
- Limited environment detection (no cgroups, simplified container detection)

**Graceful Degradation:**
- Modern macOS lacks `kern.cp_time` → returns zero values with correct structure
- Missing permissions → returns Result<T> failure instead of throwing
- Unavailable commands → Composite sources try next fallback

## CI/CD Pipeline

- **Tests**: Run on push for PHP changes across Ubuntu/macOS with PHP 8.3 and 8.4
- **Code Style**: Automated Pint formatting on push, auto-commits style fixes
- **Coverage**: Generated but not enforced (current: 62.9%)
- **Dependabot**: Automated dependency updates with auto-merge
- **Changelog**: Automated changelog updates

## Current Implementation Status

**✅ Fully Implemented (v0.1):**
- Environment detection (OS, kernel, architecture, virtualization, containers, cgroups)
- CPU metrics (raw time counters, system-wide and per-core)
- Memory metrics (raw bytes, total/free/available/used, swap)
- All DTOs, contracts, actions, sources, parsers
- Comprehensive test suite (62.9% coverage)
- Cross-platform support (Linux & macOS)

**🔜 Planned (v0.2+):**
- Disk/storage metrics
- Network interface metrics
- I/O statistics
- Process-level metrics
- PHP extension for zero-overhead metrics
- eBPF integration for advanced Linux metrics

## Notes for AI Assistants

- **Architecture is stable**: The Result<T> pattern and Action-based architecture are intentional design decisions, not technical debt
- **Test coverage gaps**: Linux-specific sources have 0% coverage because tests run on macOS - this is expected
- **Helper methods**: DTOs have calculated methods (total(), busy(), usedPercentage()) beyond the PRD - these are valuable additions
- **Readonly everywhere**: PHP 8.3+ readonly classes enable immutability without boilerplate
- **No Windows support**: By design, this library focuses on Unix-like systems
