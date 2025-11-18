# macOS Platform Support

macOS platform implementation details and limitations.

## Supported Versions

All modern macOS versions are supported:
- macOS 10.x (Yosemite through Catalina)
- macOS 11.x (Big Sur)
- macOS 12.x (Monterey)
- macOS 13.x (Ventura)
- macOS 14.x+ (Sonoma and newer)

Both Intel and Apple Silicon (ARM64) architectures.

## Data Sources

### Environment Detection
- `sw_vers -productVersion` - macOS version
- `uname` command - Kernel info and architecture
- Limited virtualization detection
- No container detection (always returns `ContainerType::NONE`)

### CPU Metrics
- `sysctl kern.cp_time` - System-wide CPU times (deprecated on modern macOS)
- `sysctl kern.cp_times` - Per-core CPU times (deprecated on modern macOS)
- `sysctl hw.ncpu` - CPU core count

**Modern macOS (Apple Silicon):** CPU sysctls are deprecated and may return zero values.

### Memory Metrics
- `vm_stat` - Virtual memory statistics
- `sysctl hw.memsize` - Total RAM
- Swap is estimated (dynamic swap files)

### Load Average
- `sysctl vm.loadavg` - Load average (1, 5, 15 minutes)

### System Uptime
- `sysctl kern.boottime` - Boot time

### Storage Metrics
- `df` command - Filesystem usage
- `iostat` - Disk I/O statistics (limited)

### Network Metrics
- `netstat -ibn` - Interface statistics
- `netstat -an` - Connection statistics
- Limited compared to Linux

### Container Metrics
Not available - macOS has no cgroup support.

## Permissions

Standard user access is sufficient. All commands are pre-installed and world-executable.

## Known Limitations

### CPU Metrics on Apple Silicon

Modern macOS (especially Apple Silicon) has deprecated CPU time sysctls:

```bash
sysctl kern.cp_time
# sysctl: unknown oid 'kern.cp_time'
```

**Result:** CPU time counters return zero. The library doesn't fail, but metrics are unavailable.

**Workaround:** Use system tools like `top`, `ps`, or Activity Monitor for CPU monitoring on modern Macs.

### No Cgroup Support

macOS has no cgroup concept:
- `SystemMetrics::container()` returns `CgroupVersion::NONE`
- Container metrics unavailable
- Unified Limits API falls back to host resources

### Limited Virtualization Detection

Unlike Linux, macOS provides minimal virtualization information. Detection is basic and may miss some VM types.

### Dynamic Swap

macOS uses dynamic swap files that are created/removed on demand. Swap metrics are best-effort estimates.

### Buffers and Cache

macOS doesn't expose buffers/cache separately like Linux:
- `MemorySnapshot::buffersBytes` always returns 0
- `MemorySnapshot::cachedBytes` always returns 0

## Performance

### Command Execution
- CPU: ~5-10ms (sysctl command execution)
- Memory: ~5-10ms (vm_stat command execution)
- Environment: ~10-15ms (multiple commands, cached after first call)

Commands are slower than Linux file reads due to process spawning overhead.

## Testing

Tested on:
- macOS 13.x (Ventura) - Intel
- macOS 14.x (Sonoma) - Apple Silicon
- macOS 15.x (Sequoia) - Apple Silicon

## Recommendations

### For macOS Developers

If you're developing on macOS but deploying to Linux:
- Test on Linux for accurate metrics
- Use Docker for Linux development environment
- CPU metrics work fully on Linux in production

### For macOS Production

If running production PHP on macOS (rare):
- Be aware of CPU metric limitations
- Consider alternative monitoring tools
- Memory and load metrics work reliably

## Related Documentation

- [Linux Support](linux.md)
- [Platform Comparison](comparison.md)
- [Environment Detection](../basic-usage/environment-detection.md)
