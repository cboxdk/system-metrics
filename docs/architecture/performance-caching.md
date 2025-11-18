# Performance Caching

Automatic static data caching for optimal performance.

## What's Cached

**Environment detection results** are automatically cached after the first call:
- OS information (name, version, family)
- Kernel information (release, version, name)
- Architecture (x86_64, arm64, etc.)
- Virtualization type (KVM, VMware, etc.)
- Container type (Docker, Kubernetes, etc.)
- Cgroup version and paths

## What's NOT Cached

**Dynamic metrics** are never cached (always fresh reads):
- CPU metrics (times, usage, per-core data)
- Memory metrics (usage, available, swap)
- Load average
- System uptime
- Storage metrics (disk usage, I/O)
- Network metrics (bandwidth, packets)
- Container metrics (current usage)
- Process metrics
- All other time-sensitive metrics

## Implementation

```php
// First call: reads from system
$env1 = SystemMetrics::environment()->getValue();
// Reads ~10-15 files on Linux, executes ~5-8 commands on macOS
// Takes ~1-5ms

// Subsequent calls: cached
$env2 = SystemMetrics::environment()->getValue();
// Returns same object instance
// Takes ~0.001ms

// Same object instance
assert($env1 === $env2);  // true
```

## Why Cache Environment Data?

Environment data is **truly static** during process lifetime:
- OS version doesn't change
- CPU architecture doesn't change
- Virtualization type doesn't change
- Container configuration doesn't change

Re-reading these on every call wastes I/O:
- **Linux**: 10-15 file reads per detection
- **macOS**: 5-8 command executions per detection

## Performance Impact

### Without Caching
Every call to `SystemMetrics::environment()`:
- Linux: ~1-5ms (file I/O)
- macOS: ~10-15ms (command execution)

### With Caching
First call has same cost, subsequent calls:
- All platforms: ~0.001ms (memory access)

**Savings:** 1000-15000x faster for cached calls.

## Cache Control

Clearing the cache is rarely needed:

```php
// Clear cache if needed (e.g., for testing)
SystemMetrics::clearEnvironmentCache();

// Force fresh detection
$env = SystemMetrics::environment()->getValue();
```

**When to clear:**
- Testing scenarios
- Process lifetime is very long (days/weeks) and you suspect OS changes
- Never needed in normal production use

## Safety

Caching is completely safe because:
1. **Immutable data** - Cached objects are readonly
2. **Process-local** - Cache doesn't persist across process restarts
3. **Truly static** - Cached data genuinely doesn't change

## Memory Usage

Negligible - environment snapshot is typically:
- 1-2 KB of memory
- Single object instance
- Automatically freed when process ends

## Design Decision

Alternative approaches considered:
- **No caching**: Wasteful I/O on repeated calls
- **TTL-based caching**: Unnecessary complexity for static data
- **Manual caching**: Error-prone, users would forget
- **Automatic caching** (chosen): Best performance, zero configuration

## Related Documentation

- [Introduction](../introduction.md) - Performance characteristics
- [Environment Detection](../basic-usage/environment-detection.md) - What gets cached
- [Design Principles](design-principles.md) - Architectural decisions
