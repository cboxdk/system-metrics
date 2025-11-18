# Composite Sources with Fallbacks

Graceful degradation when system APIs are unavailable.

## Pattern

Each metric uses a composite source that tries multiple implementations in order:

```
CompositeCpuMetricsSource
├── Future: PHP Extension source
├── Future: eBPF source
├── Current: LinuxProcCpuMetricsSource (if Linux)
├── Current: MacOsSysctlCpuMetricsSource (if macOS)
└── Returns Result::failure if all fail
```

## Benefits

- **Graceful degradation** - Library continues to work when APIs are unavailable
- **Platform-specific optimizations** - Use best source for each platform
- **Future extensibility** - Easy to add new sources (eBPF, PHP extensions)
- **Transparent to users** - Fallback logic is automatic

## Example: macOS CPU Metrics

Modern macOS (especially Apple Silicon) deprecated `kern.cp_time` sysctl. Instead of failing completely, the library:

1. Tries `kern.cp_time` sysctl
2. If unavailable, returns valid zero-value structures
3. User code doesn't crash, metrics are just unavailable

```php
$cpu = SystemMetrics::cpu()->getValue();
// On Apple Silicon: $cpu->total->user may be 0
// But the library doesn't fail - structure is valid
```

## Implementation

```php
class CompositeCpuMetricsSource implements CpuMetricsSource
{
    private array $sources = [];

    public function __construct() {
        if (PHP_OS_FAMILY === 'Linux') {
            $this->sources[] = new LinuxProcCpuMetricsSource();
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $this->sources[] = new MacOsSysctlCpuMetricsSource();
        }
    }

    public function read(): Result {
        foreach ($this->sources as $source) {
            $result = $source->read();
            if ($result->isSuccess()) {
                return $result;
            }
        }
        return Result::failure(new SystemMetricsException('All sources failed'));
    }
}
```

## Future Sources

### PHP Extension

A PHP extension could provide:
- Zero overhead (C implementation)
- Direct syscalls
- No command spawning on macOS

### eBPF (Linux)

eBPF could enable:
- Advanced metrics (latency histograms, etc.)
- Lower overhead
- More detailed CPU/memory tracking

### Cloud Provider APIs

Cloud-specific sources for:
- AWS CloudWatch metrics
- GCP Monitoring metrics
- Azure Monitor metrics

All these would slot into the composite pattern transparently.

## Related Documentation

- [Platform Support](../platform-support/comparison.md) - Platform differences
- [Custom Implementations](../advanced-usage/custom-implementations.md) - Add your own sources
