---
title: "Platform Support"
description: "Platform-specific implementation details for Linux and macOS systems"
weight: 70
---

# Platform Support

Cbox System Metrics supports Linux and macOS with platform-specific implementations that leverage native system APIs.

## Platform Pages

- **[Comparison](comparison)** - Feature comparison across Linux and macOS
- **[Linux](linux)** - Linux-specific implementation details
- **[macOS](macos)** - macOS-specific implementation details and limitations

## Supported Platforms

### ✅ Linux

Full support on all modern distributions:
- Uses `/proc` and `/sys` filesystems for metrics
- Comprehensive environment detection
- Full cgroup v1 and v2 support
- Container-aware (Docker, Podman, LXC, Kubernetes)

### ✅ macOS

Good support with some limitations:
- Uses `sysctl`, `vm_stat`, and system commands
- Limited on Apple Silicon (some CPU metrics return zero)
- No cgroup support (container detection simplified)
- Full memory and network metrics

### ❌ Windows

**Not supported** - Windows uses fundamentally different APIs (WMI, Performance Counters) that would require a complete rewrite. There are no plans for Windows support.

## Platform-Specific Considerations

Different platforms have different capabilities and limitations. See the individual platform pages for detailed information about what works and what doesn't.

The library uses the **Composite pattern** with platform-specific sources and fallbacks, ensuring graceful degradation when APIs are unavailable.

See **[Comparison](comparison)** for a detailed feature matrix.
