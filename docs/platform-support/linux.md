# Linux Platform Support

Complete Linux platform implementation details.

## Supported Distributions

All modern Linux distributions are supported:
- Ubuntu, Debian, Linux Mint
- RHEL, CentOS, Rocky Linux, AlmaLinux
- Fedora, openSUSE
- Arch Linux, Manjaro
- Alpine Linux
- And any distribution with standard `/proc` and `/sys` filesystems

## Data Sources

### Environment Detection
- `/etc/os-release` - OS name and version
- `/proc/version` - Kernel information
- `uname` command - Kernel release and architecture
- `/sys/class/dmi/id/` - Virtualization detection (DMI/SMBIOS)
- `/sys/hypervisor/type` - Hypervisor type
- `/proc/self/cgroup` - Container and cgroup detection
- `/.dockerenv` - Docker detection
- `/run/secrets/kubernetes.io` - Kubernetes detection

### CPU Metrics
- `/proc/stat` - CPU time counters (8 fields)
- `/proc/cpuinfo` - CPU model and core count
- Supports: user, system, idle, iowait, irq, softirq, steal, guest

### Memory Metrics
- `/proc/meminfo` - Complete memory statistics
- Reports: total, free, available, used, buffers, cached
- Swap: total, free, used

### Load Average
- `/proc/loadavg` - Load average (1, 5, 15 minutes)

### System Uptime
- `/proc/uptime` - Boot time calculation

### Storage Metrics
- `/proc/mounts` - Mounted filesystems
- `df` command - Filesystem usage
- `/proc/diskstats` - Disk I/O statistics

### Network Metrics
- `/proc/net/dev` - Interface statistics
- `/proc/net/tcp`, `/proc/net/udp` - Connection statistics
- `/sys/class/net/*/` - Interface type and status

### Container Metrics (Cgroups)
- **Cgroup v1**: `/sys/fs/cgroup/cpu/`, `/sys/fs/cgroup/memory/`
- **Cgroup v2**: `/sys/fs/cgroup/`
- CPU quota, usage, throttling
- Memory limits, usage, OOM kills

## Permissions

Standard user access is sufficient. All files are world-readable:

```bash
ls -l /proc/stat /proc/meminfo /proc/cpuinfo
# -r--r--r-- (world-readable)
```

### Container Restrictions

Inside containers (Docker/Kubernetes):
- `/proc` is typically mounted
- Some `/sys` paths may be restricted
- `/sys/class/dmi/id/` often unavailable (no hardware info)
- Cgroup paths show container-specific values

## Kernel Compatibility

- **Minimum**: Linux 2.6+ (ancient, any modern system works)
- **Recommended**: Linux 3.x+ for full cgroup support
- **Best**: Linux 4.x+ for cgroup v2 support

## Known Limitations

### Minimal Containers
Very minimal containers (e.g., FROM scratch) may lack:
- `/etc/os-release` → OS detection fails gracefully
- `/proc/diskstats` → Disk I/O unavailable
- Cgroup paths → Container metrics unavailable

### Security Modules
- AppArmor/SELinux may restrict `/proc` access
- Usually not an issue with standard policies

## Performance

### Read Operations
- CPU: ~1ms (single `/proc/stat` read)
- Memory: ~1ms (single `/proc/meminfo` read)
- Environment: ~5ms (multiple file reads, cached after first call)

### File Sizes
- `/proc/stat`: ~1-5 KB
- `/proc/meminfo`: ~1-2 KB
- `/proc/cpuinfo`: ~5-20 KB (varies by CPU count)

## Testing

Tested on:
- Ubuntu 20.04, 22.04, 24.04
- Debian 11, 12
- CentOS 7, 8
- Alpine Linux 3.18
- Arch Linux (rolling)

## Related Documentation

- [macOS Support](macos.md)
- [Platform Comparison](comparison.md)
- [Container Metrics](../advanced-usage/container-metrics.md)
