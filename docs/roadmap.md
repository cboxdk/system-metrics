# Roadmap

Future features and planned improvements.

## Version 0.2 (Planned)

### Enhanced Process Metrics
- [ ] Process tree navigation
- [ ] Process resource limits (ulimit)
- [ ] Process I/O statistics
- [ ] Process network connections

### Extended Storage Metrics
- [ ] Inode usage details
- [ ] Filesystem type-specific metrics
- [ ] RAID status detection
- [ ] LVM support

### Network Enhancements
- [ ] Per-interface bandwidth calculation helpers
- [ ] Packet loss rate calculation
- [ ] Network protocol statistics (TCP, UDP, ICMP)
- [ ] Routing table information

### Battery Metrics (macOS/Linux laptops)
- [ ] Battery charge level
- [ ] Power consumption
- [ ] Time remaining estimates
- [ ] Charging status

## Version 0.3 (Future)

### Performance Optimizations
- [ ] PHP extension for zero-overhead metrics (C extension)
- [ ] eBPF integration for advanced Linux metrics
- [ ] Async metrics collection
- [ ] Batched metric reads

### Advanced Container Support
- [ ] Kubernetes pod resource recommendations
- [ ] Docker Swarm metrics
- [ ] Podman-specific optimizations
- [ ] Container network metrics

### Extended Virtualization
- [ ] Cloud provider detection (AWS, GCP, Azure)
- [ ] Instance type/size detection
- [ ] Spot instance detection
- [ ] Hypervisor version information

### GPU Metrics (if feasible)
- [ ] GPU usage and temperature
- [ ] GPU memory usage
- [ ] CUDA/OpenCL detection
- [ ] Multi-GPU support

## Version 1.0 (Stable Release)

### API Stability
- [ ] Semantic versioning guarantee
- [ ] Deprecation policy
- [ ] Migration guides
- [ ] LTS support plan

### Documentation
- [ ] Interactive examples
- [ ] Video tutorials
- [ ] Integration guides (Laravel, Symfony, etc.)
- [ ] Performance tuning guide

### Enterprise Features
- [ ] Prometheus exporter
- [ ] StatsD integration
- [ ] Datadog integration
- [ ] Custom metric plugins API

## What We Won't Do

### ❌ Windows Support
Windows has fundamentally different APIs (WMI, Performance Counters) that would require a complete rewrite. Not planned.

### ❌ Real-Time Streaming
Streaming metrics would require background processes or extensions. Use external monitoring tools instead.

### ❌ Historical Data Storage
This library provides instant snapshots only. Use time-series databases (InfluxDB, Prometheus) for historical data.

### ❌ Alerting System
Out of scope. Use monitoring platforms (Grafana, Datadog, New Relic) for alerting.

### ❌ Cross-Server Metrics
This library monitors the local system only. Use distributed monitoring for multi-server metrics.

## Community Requests

Have a feature request? [Open an issue](https://github.com/gophpeek/system-metrics/issues) to discuss!

**Criteria for new features:**
- Must be universally useful (not niche)
- Must work on Linux and/or macOS
- Must maintain zero dependencies
- Must fit the "instant snapshot" model
- Must not require background processes

## Version History

See [CHANGELOG.md](../CHANGELOG.md) for complete version history.

## Related Documentation

- [Introduction](introduction.md) - Current features
- [Contributing](../CONTRIBUTING.md) - How to contribute
- [GitHub Issues](https://github.com/gophpeek/system-metrics/issues) - Report bugs or request features
