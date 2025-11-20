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

#### 6. Performance Optimization: Static Data Caching

Environment detection results are automatically cached after the first call:

```php
// First call reads from system (disk I/O, syscalls)
$result1 = SystemMetrics::environment();

// Subsequent calls return cached result (no I/O)
$result2 = SystemMetrics::environment(); // Instant, same object
```

**Cached (Static) Data:**
- OS information (name, version, family)
- Kernel information (release, version, name)
- Architecture (x86_64, arm64, etc.)
- Virtualization type (KVM, VMware, VirtualBox, etc.)
- Container type (Docker, Podman, LXC)
- Cgroup version and paths

**Not Cached (Dynamic) Data:**
- CPU metrics (times, usage, per-core data)
- Memory metrics (usage, available, swap)
- Storage metrics (disk usage, I/O)
- Network metrics (bandwidth, packets)
- Load average, uptime, and all other time-sensitive metrics

**Cache Control:**
```php
// Clear cache if needed (rare, mostly for testing)
SystemMetrics::clearEnvironmentCache();

// Force fresh detection
SystemMetrics::clearEnvironmentCache();
$result = SystemMetrics::environment();
```

**Benefits:**
- Eliminates redundant disk I/O for static data (10-15 file reads on Linux, 5-8 syscalls on macOS)
- Reduces overhead from ~1-5ms to ~0.001ms per call after first detection
- Automatic - no configuration needed
- Safe - only caches data that never changes during process lifetime

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

# PHPeek Documentation Guide

This guide explains how to structure documentation for PHPeek packages to ensure optimal display and navigation on phpeek.com.

## Core Concepts

### Major Version Management
- PHPeek displays ONE entry per major version (v1, v2, v3)
- System automatically tracks the latest release within each major version
- URLs use major version: `/docs/{package}/v1`, `/docs/{package}/v2`
- When you release v1.2.1 after v1.2.0, the website updates automatically

### Files NOT Used on PHPeek.com

**README.md - GitHub Only**
- ⚠️ README.md is **NEVER** displayed on PHPeek.com
- README.md is only for GitHub repository display
- All documentation must be in the `/docs` folder
- Do NOT reference README.md in your docs

**Files Used on PHPeek.com**
- All `.md` files in the `/docs` folder
- All image/asset files within `/docs`
- `_index.md` files for directory landing pages (optional but recommended)

## Directory Structure

### Recommended Structure
```
docs/
├── introduction.md              # What is this package?
├── installation.md              # How to install
├── quickstart.md               # 5-minute getting started
├── basic-usage/                # Core features
│   ├── _index.md              # Optional: Section overview
│   ├── feature-one.md
│   └── feature-two.md
├── advanced-usage/             # Complex scenarios
│   ├── _index.md
│   └── advanced-feature.md
├── api-reference.md            # Complete API docs
└── testing.md                  # How to test
```

### Directory Naming Rules
- ✅ Use lowercase with hyphens: `basic-usage/`, `advanced-features/`
- ✅ Keep names short: `api-reference/`, `platform-support/`
- ✅ Max 2-3 levels of nesting
- ❌ Don't use spaces or special characters
- ❌ Don't create deeply nested structures (>3 levels)

## Metadata (Frontmatter)

### Required Fields
Every `.md` file **MUST** have frontmatter with `title` and `description`:

```yaml
---
title: "Page Title"           # REQUIRED
description: "Brief summary"  # REQUIRED
weight: 99                    # OPTIONAL (default: 99)
hidden: false                 # OPTIONAL (default: false)
---
```

### How Metadata Is Used

**Title**
- Navigation sidebar link text
- Page header `<h1>` tag
- Browser tab title
- SEO meta tags
- Social media sharing

**Description**
- SEO meta description
- Search engine result snippets
- Social media preview text
- May influence click-through rate

**Weight**
- Controls navigation order (lower = first)
- Default is 99
- Same weight = alphabetical by title
- Only affects current directory

**Hidden**
- Set to `true` to hide from navigation
- Page still accessible via direct URL
- Useful for drafts or deprecated content

### Metadata Best Practices

**Title Guidelines**
```yaml
# ✅ Good titles
title: "CPU Metrics"
title: "Error Handling"
title: "API Reference"

# ❌ Avoid
title: "Page 1"                    # Generic
title: "System Metrics CPU Stuff"  # Too long, redundant
title: "cpu-metrics"               # Not Title Case
```

**Description Guidelines**
```yaml
# ✅ Good descriptions (60-160 chars, action-oriented)
description: "Get raw CPU time counters and per-core metrics from the system"
description: "Master the Result<T> pattern for explicit error handling"
description: "Monitor resource usage for individual processes or process groups"

# ❌ Avoid
description: "This page describes CPU metrics"  # Too generic
description: "CPU stuff"                        # Too vague
description: "A very long description that goes on and on..."  # Too long (>160 chars)
```

**Weight Organization**
```yaml
# Recommended weight ranges:
1-10:   Critical pages (introduction, installation, quickstart)
11-30:  Common features (basic usage)
31-70:  Advanced features
71-99:  Reference material (API docs, appendices)

# Example:
# docs/introduction.md
weight: 1

# docs/installation.md
weight: 2

# docs/quickstart.md
weight: 3

# docs/basic-usage/cpu-metrics.md
weight: 10
```

## Links and URLs

### Internal Documentation Links

Use **relative paths** to link between documentation pages:

```markdown
# Link to sibling file in same directory
[Installation Guide](installation)

# Link to file in parent directory
[Back to Introduction](../introduction)

# Link to file in subdirectory
[CPU Metrics](basic-usage/cpu-metrics)

# Link to file in different subdirectory
[Platform Comparison](../platform-support/comparison)

# Link with anchor to heading
[Error Handling](advanced-usage/error-handling#result-pattern)
```

**Link Best Practices**
- ✅ Use descriptive link text: `[View API Reference](api-reference)`
- ✅ Remove `.md` extension: `[Guide](installation)` not `[Guide](installation.md)`
- ✅ Use relative paths: `[Guide](../guide)`
- ❌ Don't use generic text: `[Click here](guide)` or `[Read more](docs)`
- ❌ Don't hardcode absolute URLs: `[Guide](/docs/package/v1/guide)`
- ❌ Don't link to README.md (it's not displayed)

### External Links

```markdown
# Always use full URLs with https://
[GitHub Repository](https://github.com/owner/repo)
[Official Website](https://example.com)

# ✅ Good
[Documentation](https://example.com/docs)

# ❌ Avoid
[Documentation](example.com/docs)  # Missing https://
```

## Images and Assets

### Image References

Use **relative paths** for images:

```markdown
# Image in same directory
![Performance Chart](performance.png)

# Image in subdirectory
![Diagram](images/architecture.png)

# Image in parent images folder
![Logo](../images/logo.svg)

# Image with alt text and tooltip
![Chart](chart.png "CPU Performance Over Time")
```

**Image Best Practices**
- ✅ Always include alt text: `![Diagram](image.png)` not `![](image.png)`
- ✅ Use relative paths
- ✅ Organize in `/docs/images/` or feature-specific folders
- ✅ Supported formats: `.png`, `.jpg`, `.jpeg`, `.gif`, `.svg`, `.webp`
- ❌ Don't use absolute URLs
- ❌ Don't reference images outside `/docs` folder

### Asset Organization

```
docs/
├── images/              # Shared images
│   ├── logo.png
│   └── architecture.svg
├── basic-usage/
│   ├── cpu-chart.png   # Feature-specific image
│   └── cpu-metrics.md
└── screenshots/         # UI screenshots
    └── dashboard.png
```

## Code Blocks

### Syntax Highlighting

Always specify the language after the opening fence:

````markdown
```php
use PHPeek\SystemMetrics\SystemMetrics;

$cpu = SystemMetrics::cpu()->get();
echo "Cores: {$cpu->cores}\n";
```
````

**Supported Languages**
- PHP, JavaScript, Bash, JSON, YAML, XML, HTML, Markdown, SQL, Dockerfile

**Code Block Best Practices**
````markdown
# ✅ Good - Language specified
```php
$metrics = SystemMetrics::cpu()->get();
```

# ❌ Avoid - No language
```
$metrics = SystemMetrics::cpu()->get();
```
````

## Index Files (_index.md)

### Purpose
- Creates landing pages for directory sections
- Provides section overview
- Optional but recommended for better UX

### When to Use

**✅ Create _index.md for:**
- Major sections with 3+ child pages
- Directories needing explanation
- Sections requiring custom intro text

**❌ Skip _index.md for:**
- Simple directories with 1-2 pages
- Self-explanatory sections

### Example _index.md

```markdown
---
title: "Basic Usage"
description: "Essential features for getting started with the package"
weight: 1
---

# Basic Usage

This section covers the fundamental features you'll use daily:

- CPU and memory monitoring
- Disk usage tracking
- Network statistics
- System uptime

Start with the "System Overview" guide for a quick introduction.
```

## Complete Example

**File**: `docs/basic-usage/cpu-metrics.md`

```markdown
---
title: "CPU Metrics"
description: "Get raw CPU time counters and per-core metrics from the system"
weight: 10
---

# CPU Metrics

Monitor CPU usage and performance with real-time metrics.

## Getting CPU Statistics

```php
use PHPeek\SystemMetrics\SystemMetrics;

$cpu = SystemMetrics::cpu()->get();

echo "CPU Cores: {$cpu->cores}\n";
echo "User Time: {$cpu->user}ms\n";
echo "System Time: {$cpu->system}ms\n";
```

## Per-Core Metrics

```php
foreach ($cpu->perCore as $core) {
    echo "Core {$core->id}: {$core->usage}%\n";
}
```

## Performance Considerations

![CPU Performance Chart](../images/cpu-performance.png)

The metrics collection is highly optimized:
- No system calls for static data
- Efficient caching for hardware info
- Minimal overhead (<1ms per call)

See [Performance Caching](../architecture/performance-caching) for details.

## Platform Support

- ✅ Linux: Full support via `/proc/stat`
- ✅ macOS: Full support via `host_processor_info()`

See [Platform Comparison](../platform-support/comparison) for detailed differences.
```

## Quality Checklist

Before publishing, verify:

### Metadata
- [ ] Every `.md` file has `title` and `description`
- [ ] Titles are unique and descriptive (Title Case)
- [ ] Descriptions are 60-160 characters
- [ ] Weight values create logical ordering
- [ ] No generic titles like "Page 1", "Document"

### Structure
- [ ] Major sections have `_index.md` files
- [ ] Directory nesting is shallow (max 2-3 levels)
- [ ] File names use lowercase-with-hyphens
- [ ] Directory names are short and descriptive

### Content
- [ ] Code blocks specify language
- [ ] Images have alt text
- [ ] Links use relative paths
- [ ] No references to README.md
- [ ] All internal links tested

### Files
- [ ] All documentation in `/docs` folder
- [ ] No absolute URLs for internal content
- [ ] Images stored within `/docs` directory
- [ ] No spaces or special characters in filenames

## Troubleshooting

### Navigation Not Showing
- Check frontmatter exists and is valid YAML
- Verify `title` and `description` are present
- Ensure file has `.md` extension
- Confirm `hidden: false` (or field omitted)
- Verify file is in `/docs` folder (not root)

### Images Not Loading
- Use relative paths: `![](../images/file.png)`
- Verify image exists in repository
- Check file extension is supported
- Ensure image is within `/docs` directory

### Wrong Page Order
- Add `weight` to frontmatter
- Lower numbers appear first (1, 2, 3...)
- Default weight is 99
- Same weight = alphabetical by title

### Code Not Highlighting
- Specify language: \`\`\`php not just \`\`\`
- Supported: php, js, bash, json, yaml, xml, html, md, sql, dockerfile
- Check spelling of language name
- Ensure code block is properly closed

## URL Structure

Your documentation will be available at:

```
https://phpeek.com/docs/{package}/{major_version}/{page_path}

Examples:
/docs/system-metrics/v1/introduction
/docs/system-metrics/v1/basic-usage/cpu-metrics
/docs/system-metrics/v2/advanced-usage/custom-implementations
```

**How URLs Are Generated**
```
File: docs/basic-usage/cpu-metrics.md
URL:  /docs/system-metrics/v1/basic-usage/cpu-metrics

File: docs/introduction.md
URL:  /docs/system-metrics/v1/introduction
```

## SEO Tips

**Title Impact**
- Shown in Google search results
- Used in social media shares
- Displayed in browser tabs
- Should be unique and descriptive

**Description Impact**
- Shown as snippet in search results
- Used in social media previews
- Should be 120 characters ideal
- Should explain page value to users

**Best Practices**
- ✅ Unique title per page
- ✅ Descriptive URLs (via good filenames)
- ✅ 60-160 character descriptions
- ✅ Include relevant keywords naturally
- ❌ Don't stuff keywords
- ❌ Don't use duplicate titles
- ❌ Don't create duplicate content
