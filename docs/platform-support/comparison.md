# Platform Comparison

Feature parity comparison between Linux and macOS.

## Feature Matrix

| Feature | Linux | macOS | Notes |
|---------|-------|-------|-------|
| **Environment Detection** | | | |
| OS name/version | ✅ Full | ✅ Full | Both platforms supported |
| Kernel info | ✅ Full | ✅ Full | Complete information |
| Architecture | ✅ Full | ✅ Full | x86_64, ARM64 supported |
| Virtualization | ✅ Full | ⚠️ Limited | macOS detection is basic |
| Container detection | ✅ Full | ❌ None | No cgroups on macOS |
| Cgroup version/paths | ✅ Full | ❌ None | Linux only |
| **CPU Metrics** | | | |
| System-wide times | ✅ Full (8 fields) | ⚠️ Deprecated | May return zeros on modern macOS |
| Per-core times | ✅ Full | ⚠️ Deprecated | May return zeros on Apple Silicon |
| CPU usage calculation | ✅ Full | ⚠️ Limited | Requires non-zero counters |
| **Memory Metrics** | | | |
| Total/free/available | ✅ Full | ✅ Full | Both platforms supported |
| Buffers/cache | ✅ Full | ❌ None | Always 0 on macOS |
| Swap metrics | ✅ Full | ⚠️ Estimated | macOS uses dynamic swap |
| **Load Average** | ✅ Full | ✅ Full | Both platforms supported |
| **System Uptime** | ✅ Full | ✅ Full | Both platforms supported |
| **Storage Metrics** | | | |
| Filesystem usage | ✅ Full | ✅ Full | Both platforms supported |
| Disk I/O | ✅ Full | ⚠️ Limited | macOS iostat less detailed |
| **Network Metrics** | | | |
| Interface stats | ✅ Full | ✅ Full | Both platforms supported |
| Connection stats | ✅ Full | ⚠️ Limited | Linux provides more detail |
| **Container Metrics** | | | |
| Cgroup v1 support | ✅ Full | ❌ None | Linux only |
| Cgroup v2 support | ✅ Full | ❌ None | Linux only |
| CPU limits/usage | ✅ Full | ❌ None | Linux only |
| Memory limits/usage | ✅ Full | ❌ None | Linux only |
| Throttling detection | ✅ Full | ❌ None | Linux only |
| OOM kill tracking | ✅ Full | ❌ None | Linux only |
| **Process Metrics** | ✅ Full | ✅ Full | Both platforms supported |
| **Unified Limits API** | ✅ Full | ⚠️ Host only | macOS always uses host resources |

## Legend

- ✅ **Full**: Complete implementation with all features
- ⚠️ **Limited**: Partial implementation or known limitations
- ❌ **None**: Not supported on this platform

## Platform Recommendations

### For Production Linux Servers
✅ **Recommended** - Full feature set, all metrics available, container-aware.

### For macOS Development
⚠️ **Limited** - Good for development, but CPU metrics may be unavailable on Apple Silicon. Test on Linux for accurate metrics.

### For macOS Production
⚠️ **Not Recommended** - Running PHP production workloads on macOS is rare. If you must, be aware of CPU metric limitations.

## Implementation Strategy

The library uses the Composite pattern with platform-specific sources:

```
CompositeCpuMetricsSource
├── LinuxProcCpuMetricsSource (if Linux)
├── MacOsSysctlCpuMetricsSource (if macOS)
└── Returns failure if all sources fail
```

This enables graceful degradation when APIs are unavailable.

## Testing Approach

- **Linux**: Comprehensive testing on Ubuntu, Debian, CentOS, Alpine
- **macOS**: Testing on Intel and Apple Silicon
- **Cross-platform**: Architecture tests ensure consistent API across platforms

## Migration Path

If you're developing on macOS but deploying to Linux:

1. Use Docker for Linux development environment
2. Test metrics in Linux containers
3. Deploy to Linux servers for full feature set
4. Use CI/CD pipelines to verify on both platforms

## Related Documentation

- [Linux Support](linux.md) - Linux-specific details
- [macOS Support](macos.md) - macOS-specific details
- [Architecture: Composite Sources](../architecture/composite-sources.md) - Fallback implementation
