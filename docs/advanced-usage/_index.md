---
title: "Advanced Usage"
description: "Advanced features including container metrics, process tracking, and custom implementations"
weight: 30
---

# Advanced Usage

This section covers advanced features and complex scenarios for experienced users who need deeper integration and customization.

## Advanced Features

- **[Container Metrics](container-metrics)** - Cgroup v1/v2 support, Docker/Kubernetes awareness
- **[Process Metrics](process-metrics)** - Individual process and process group monitoring
- **[Unified Limits](unified-limits)** - Environment-aware resource limits (host vs container)
- **[CPU Usage Calculation](cpu-usage-calculation)** - Deep dive into delta calculations and percentages
- **[Error Handling](error-handling)** - Master the Result<T> pattern for robust applications
- **[Custom Implementations](custom-implementations)** - Extend the library with custom metric sources

## Container Awareness

Cbox System Metrics automatically detects when running inside Docker or Kubernetes containers and respects cgroup limits rather than reporting host resources. This is critical for accurate monitoring in containerized environments.

See **[Container Metrics](container-metrics)** for detailed examples.

## Process Tracking

Track individual processes or process groups to monitor resource usage of spawned processes, detect memory leaks, and analyze CPU consumption.

See **[Process Metrics](process-metrics)** for tracking patterns.

## Custom Implementations

For advanced use cases, you can replace the built-in metric sources with your own implementations. This is useful for caching, custom parsers, or integration with external systems.

See **[Custom Implementations](custom-implementations)** for details.
