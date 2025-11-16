# E2E Testing for PHPeek/SystemMetrics

Comprehensive end-to-end testing infrastructure for validating system metrics across multiple container environments.

## Overview

This E2E test suite validates that PHPeek/SystemMetrics accurately reports system metrics when running inside:

- **Docker containers** with cgroup v1 (Ubuntu 20.04)
- **Docker containers** with cgroup v2 (Ubuntu 22.04)
- **Kubernetes pods** in Kind clusters with resource limits

The tests ensure metrics are accurate under various resource constraints (CPU limits, memory limits, throttling, OOM conditions).

## Test Structure

```
tests/E2E/
├── Docker/
│   ├── CgroupV1/           # Tests for cgroup v1 containers
│   │   ├── CpuLimitsTest.php
│   │   ├── MemoryLimitsTest.php
│   │   ├── ThrottlingTest.php
│   │   └── OomKillTest.php
│   ├── CgroupV2/           # Tests for cgroup v2 containers
│   │   ├── CpuLimitsTest.php
│   │   ├── MemoryLimitsTest.php
│   │   ├── ThrottlingTest.php
│   │   └── OomKillTest.php
│   └── Shared/             # Cross-cgroup tests (future)
├── Kubernetes/             # Tests for K8s pods
│   ├── PodLimitsTest.php
│   ├── ResourceQuotaTest.php
│   ├── CpuThrottlingTest.php
│   └── MemoryPressureTest.php
└── Support/                # Helper classes
    ├── DockerHelper.php
    ├── KindHelper.php
    └── MetricsValidator.php
```

## Infrastructure

```
e2e/
├── docker/                 # Dockerfiles for test containers
│   ├── Dockerfile.test-runner
│   ├── Dockerfile.cgroupv1
│   ├── Dockerfile.cgroupv2
│   └── stress-test.sh
├── compose/                # Docker Compose orchestration
│   └── docker-compose.yml
├── kind/                   # Kubernetes manifests
│   ├── cluster-config.yaml
│   ├── resource-quota.yaml
│   ├── pod-cpu-limit.yaml
│   └── pod-memory-limit.yaml
└── scripts/                # Execution scripts
    ├── run-e2e.sh
    ├── setup-kind.sh
    └── cleanup.sh
```

## Quick Start

### Prerequisites

- **Docker** (Docker Desktop or Docker Engine)
- **Docker Compose** (v2.0+ or docker-compose standalone)
- **Kind** (Kubernetes in Docker) - [Install](https://kind.sigs.k8s.io/docs/user/quick-start/#installation)
- **kubectl** - [Install](https://kubernetes.io/docs/tasks/tools/)
- **PHP 8.3+** with Composer

### Run All Tests

```bash
# Install dependencies
composer install

# Run complete E2E test suite
bash e2e/scripts/run-e2e.sh
```

### Run Specific Test Suites

```bash
# Docker tests only
bash e2e/scripts/run-e2e.sh --docker-only

# Kubernetes tests only
bash e2e/scripts/run-e2e.sh --k8s-only

# Run with automatic cleanup
bash e2e/scripts/run-e2e.sh --cleanup
```

### Manual Setup

```bash
# Setup Docker environment
cd e2e/compose
docker-compose up -d

# Setup Kind cluster
bash e2e/scripts/setup-kind.sh

# Run tests
vendor/bin/pest tests/E2E/Docker/CgroupV1/
vendor/bin/pest tests/E2E/Docker/CgroupV2/
vendor/bin/pest tests/E2E/Kubernetes/

# Cleanup
bash e2e/scripts/cleanup.sh
```

## Test Scenarios

### Docker CgroupV1 Tests

**Container**: `cgroupv1-target` (Ubuntu 20.04)
- **CPU Limit**: 0.5 cores (--cpus=0.5)
- **Memory Limit**: 256 MB (--mem-limit=256m)
- **Memory Reservation**: 128 MB (--mem-reservation=128m)

**Tests**:
1. **CpuLimitsTest**: CPU quota detection, per-core metrics, cgroup files
2. **MemoryLimitsTest**: Memory limit detection, consistency checks
3. **ThrottlingTest**: CPU throttling statistics, pressure indicators
4. **OomKillTest**: OOM event tracking (manual test, destructive)

### Docker CgroupV2 Tests

**Container**: `cgroupv2-target` (Ubuntu 22.04)
- **CPU Limit**: 1.0 cores (--cpus=1.0)
- **Memory Limit**: 512 MB (--mem-limit=512m)
- **Memory Reservation**: 256 MB (--mem-reservation=256m)

**Tests**:
1. **CpuLimitsTest**: cpu.max parsing, cpu.stat analysis, cpu.weight
2. **MemoryLimitsTest**: memory.max, memory.current, memory.stat
3. **ThrottlingTest**: Throttling via cpu.stat, PSI (Pressure Stall Information)
4. **OomKillTest**: memory.events tracking, memory.pressure PSI (manual test)

**Cgroup v2 Differences**:
- Unified hierarchy (`/sys/fs/cgroup` instead of `/sys/fs/cgroup/*`)
- Time in microseconds (usec) instead of jiffies
- Pressure Stall Information (PSI) for cpu.pressure and memory.pressure
- memory.high for throttling before OOM
- Enhanced event tracking in memory.events

### Kubernetes Tests

**Cluster**: `system-metrics-test` (Kind, 3-node: 1 control-plane, 2 workers)
**Namespace**: `metrics-test` with resource quotas

**Test Pods**:

1. **php-metrics-cpu-test**:
   - CPU Request: 100m (0.1 cores)
   - CPU Limit: 500m (0.5 cores)
   - Memory Request: 64Mi
   - Memory Limit: 256Mi

2. **php-metrics-memory-test**:
   - Memory Request: 128Mi
   - Memory Limit: 256Mi
   - Continuously allocates memory (200MB)

**Namespace Quota**:
- CPU Requests: 2 cores
- CPU Limits: 4 cores
- Memory Requests: 2Gi
- Memory Limits: 4Gi
- Max Pods: 10

**Tests**:
1. **PodLimitsTest**: CPU/memory limits, environment detection, system overview
2. **ResourceQuotaTest**: Quota enforcement, QoS classes (Guaranteed/Burstable)
3. **CpuThrottlingTest**: CPU throttling under load, per-core metrics
4. **MemoryPressureTest**: Memory pressure tracking, consistency under allocation

## Helper Classes

### DockerHelper

Provides Docker container operations:

```php
// Execute command in container
$output = DockerHelper::exec('cgroupv1-target', 'cat /proc/cpuinfo');

// Detect cgroup version
$version = DockerHelper::detectCgroupVersion('cgroupv1-target'); // 'v1' or 'v2'

// Run stress tests
DockerHelper::stressCpu('cgroupv1-target', durationSeconds: 5, workers: 2);
DockerHelper::stressMemory('cgroupv1-target', megabytes: 100, durationSeconds: 5);

// Run PHP code
$output = DockerHelper::runPhp('cgroupv1-target', 'echo "Hello";');

// Check container status
$running = DockerHelper::isRunning('cgroupv1-target');
```

### KindHelper

Provides Kubernetes cluster operations:

```php
// Ensure cluster exists
KindHelper::ensureCluster();

// Deploy test pods
KindHelper::deployTestPods();

// Execute command in pod
$output = KindHelper::execInPod('php-metrics-cpu-test', 'metrics-test', 'php -v');

// Run kubectl command
$output = KindHelper::kubectl('get pods -n metrics-test');

// Check pod status
$exists = KindHelper::podExists('php-metrics-cpu-test', 'metrics-test');

// Cleanup resources
KindHelper::cleanup();
```

### MetricsValidator

Provides validation assertions with tolerance:

```php
// Validate CPU quota
MetricsValidator::validateCpuQuota($cpu, expectedCores: 0.5, tolerancePercent: 10.0);

// Validate memory limit
MetricsValidator::validateMemoryLimit($memory, expectedBytes: 256 * 1024 * 1024, tolerancePercent: 5.0);

// Validate internal consistency
MetricsValidator::validateConsistency($cpu, $memory);

// Utility conversions
$bytes = MetricsValidator::mbToBytes(256); // 268435456
$cores = MetricsValidator::milliCoresToCores(500); // 0.5
```

## Test Patterns

### Basic Metrics Test

```php
it('detects CPU limit in container', function () {
    $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::cpu();
echo json_encode([
    'success' => $result->isSuccess(),
    'coreCount' => $result->isSuccess() ? $result->getValue()->coreCount() : null,
]);
PHP;

    $output = DockerHelper::runPhp('cgroupv1-target', $code);
    $data = json_decode($output, true);

    expect($data['success'])->toBeTrue();
    expect($data['coreCount'])->toBeGreaterThan(0.4);
    expect($data['coreCount'])->toBeLessThan(0.6); // ~0.5 cores
});
```

### Stress Test Pattern

```php
it('detects CPU activity during stress', function () {
    // Take baseline
    $baseline = getCpuBusyTime();

    // Generate load
    DockerHelper::stressCpu('cgroupv1-target', 3, 2);

    // Measure again
    $postStress = getCpuBusyTime();

    expect($postStress)->toBeGreaterThan($baseline);
});
```

### Cgroup File Reading

```php
it('reads cgroup v2 CPU limit', function () {
    $cpuMax = trim(DockerHelper::readFile('cgroupv2-target', '/sys/fs/cgroup/cpu.max'));

    [$max, $period] = explode(' ', $cpuMax);

    if ($max !== 'max') {
        $cpuLimit = (int)$max / (int)$period;
        expect($cpuLimit)->toBeGreaterThan(0.9);
        expect($cpuLimit)->toBeLessThan(1.1); // ~1.0 cores
    }
});
```

## Troubleshooting

### Docker Issues

**Containers not starting:**
```bash
cd e2e/compose
docker-compose logs
docker-compose down -v
docker-compose up -d
```

**Cgroup version detection fails:**
```bash
# Check cgroup version on host
stat -fc %T /sys/fs/cgroup/

# tmpfs = cgroup v2
# cgroup2fs = cgroup v2
# cgroup = cgroup v1
```

### Kubernetes Issues

**Kind cluster creation fails:**
```bash
# Delete and recreate
kind delete cluster --name system-metrics-test
bash e2e/scripts/setup-kind.sh
```

**Pods not ready:**
```bash
kubectl get pods -n metrics-test
kubectl describe pod php-metrics-cpu-test -n metrics-test
kubectl logs -n metrics-test php-metrics-cpu-test
```

**Resource quota exceeded:**
```bash
kubectl describe resourcequota metrics-quota -n metrics-test
kubectl delete pod -n metrics-test --all
```

### Test Failures

**Permission denied errors:**
- Ensure Docker daemon is running
- Check user has Docker permissions (`docker ps` works)

**Timeout errors:**
- Increase timeout in tests
- Check system resources (CPU, memory available)

**Assertion failures:**
- Check container resource limits match expected values
- Verify cgroup version matches test expectations
- Review actual vs. expected values in test output

## CI/CD Integration

### GitHub Actions

See `.github/workflows/e2e-tests.yml` for CI configuration.

**Jobs**:
1. **docker-e2e**: Docker cgroup v1 and v2 tests
2. **kubernetes-e2e**: Kind cluster tests

**Matrix**:
- Ubuntu 20.04, 22.04, 24.04
- PHP 8.3, 8.4

### Local CI Simulation

```bash
# Run tests as CI would
bash e2e/scripts/run-e2e.sh --cleanup

# Verify no resources left behind
docker ps -a | grep system-metrics
kind get clusters | grep system-metrics
```

## Performance

**Execution Time**:
- Docker setup: ~30 seconds
- Docker tests: ~2-3 minutes
- Kind setup: ~2-3 minutes
- Kubernetes tests: ~3-4 minutes
- **Total**: ~8-10 minutes

**Resource Usage**:
- Docker: ~1GB memory, ~2 CPU cores
- Kind: ~2GB memory, ~4 CPU cores
- Disk: ~5GB (Docker images, Kind cluster)

## Best Practices

1. **Always cleanup** after local testing: `bash e2e/scripts/cleanup.sh`
2. **Use tolerance** for metric assertions (±5-10%)
3. **Skip destructive tests** in CI (OOM kill tests)
4. **Check prerequisites** before running tests
5. **Review logs** on failures for debugging context

## Future Enhancements

- [ ] Shared Docker tests (cross-cgroup compatibility)
- [ ] Bare metal Linux tests (no containerization)
- [ ] Network metrics validation
- [ ] Disk I/O metrics validation
- [ ] Multi-container stress scenarios
- [ ] Performance regression detection
- [ ] Test result reporting and metrics

## References

- [Cgroups v1 Documentation](https://www.kernel.org/doc/Documentation/cgroup-v1/)
- [Cgroups v2 Documentation](https://www.kernel.org/doc/Documentation/cgroup-v2.txt)
- [Kubernetes Resource Management](https://kubernetes.io/docs/concepts/configuration/manage-resources-containers/)
- [Kind Documentation](https://kind.sigs.k8s.io/)
- [Docker Resource Constraints](https://docs.docker.com/config/containers/resource_constraints/)
