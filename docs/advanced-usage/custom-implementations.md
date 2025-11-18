# Custom Implementations

Create custom metric sources for caching, alternative data sources, or testing.

## Overview

All metric sources implement interfaces, making them easy to swap out with custom implementations. This is useful for:

- Caching metrics in Redis/Memcached
- Using alternative data sources (PHP extensions, eBPF)
- Testing with stub implementations
- Adding custom processing or filtering

## Available Interfaces

```php
use PHPeek\SystemMetrics\Contracts\{
    EnvironmentDetector,
    CpuMetricsSource,
    MemoryMetricsSource,
    LoadAverageSource,
    UptimeSource,
    StorageMetricsSource,
    NetworkMetricsSource
};
```

## Creating a Custom Source

### Example: Redis-Cached CPU Metrics

```php
use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Sources\Cpu\LinuxProcCpuMetricsSource;

class RedisCachedCpuSource implements CpuMetricsSource
{
    public function __construct(
        private Redis $redis,
        private CpuMetricsSource $fallback = new LinuxProcCpuMetricsSource(),
        private int $ttl = 1  // Cache for 1 second
    ) {}

    public function read(): Result
    {
        $cacheKey = 'system_metrics:cpu';

        // Try cache first
        if ($cached = $this->redis->get($cacheKey)) {
            $snapshot = unserialize($cached);
            return Result::success($snapshot);
        }

        // Cache miss - read from system
        $result = $this->fallback->read();

        if ($result->isSuccess()) {
            $this->redis->setex(
                $cacheKey,
                $this->ttl,
                serialize($result->getValue())
            );
        }

        return $result;
    }
}
```

### Configuring Custom Source Globally

```php
use PHPeek\SystemMetrics\Config\SystemMetricsConfig;

// Set custom CPU source
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
SystemMetricsConfig::setCpuMetricsSource(new RedisCachedCpuSource($redis));

// All subsequent calls use Redis cache
$cpu = SystemMetrics::cpu();
```

## Testing with Stubs

### Simple Stub

```php
use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\{CpuSnapshot, CpuTimes};

$stub = new class implements CpuMetricsSource {
    public function read(): Result {
        return Result::success(new CpuSnapshot(
            total: new CpuTimes(100, 50, 500, 0, 0, 0, 0, 0),
            perCore: [],
            timestamp: new DateTimeImmutable()
        ));
    }
};

SystemMetricsConfig::setCpuMetricsSource($stub);
```

### PHPUnit Mock

```php
use PHPUnit\Framework\TestCase;
use PHPeek\SystemMetrics\Contracts\MemoryMetricsSource;
use PHPeek\SystemMetrics\Config\SystemMetricsConfig;

class MyTest extends TestCase
{
    public function testMemoryUsage()
    {
        // Create mock
        $mock = $this->createMock(MemoryMetricsSource::class);
        $mock->method('read')
            ->willReturn(Result::success($this->createFakeMemorySnapshot()));

        // Configure globally
        SystemMetricsConfig::setMemoryMetricsSource($mock);

        // Test your code
        $result = SystemMetrics::memory();
        $this->assertTrue($result->isSuccess());
    }

    private function createFakeMemorySnapshot()
    {
        return new MemorySnapshot(
            totalBytes: 16 * 1024 ** 3,  // 16 GB
            freeBytes: 8 * 1024 ** 3,     // 8 GB
            availableBytes: 10 * 1024 ** 3,
            usedBytes: 6 * 1024 ** 3,
            buffersBytes: 0,
            cachedBytes: 0,
            swapTotalBytes: 0,
            swapFreeBytes: 0,
            swapUsedBytes: 0,
            timestamp: new DateTimeImmutable()
        );
    }
}
```

## Using Dependency Injection

Actions can be instantiated with custom sources:

```php
use PHPeek\SystemMetrics\Actions\ReadCpuMetricsAction;
use PHPeek\SystemMetrics\Sources\Cpu\LinuxProcCpuMetricsSource;

// Direct instantiation with custom source
$action = new ReadCpuMetricsAction(
    new RedisCachedCpuSource($redis)
);

$result = $action->execute();
```

## Example: Logging Source Wrapper

```php
use PHPeek\SystemMetrics\Contracts\MemoryMetricsSource;
use PHPeek\SystemMetrics\DTO\Result;
use Psr\Log\LoggerInterface;

class LoggingMemorySource implements MemoryMetricsSource
{
    public function __construct(
        private MemoryMetricsSource $inner,
        private LoggerInterface $logger
    ) {}

    public function read(): Result
    {
        $start = microtime(true);
        $result = $this->inner->read();
        $duration = (microtime(true) - $start) * 1000;  // ms

        if ($result->isSuccess()) {
            $mem = $result->getValue();
            $this->logger->debug('Memory read succeeded', [
                'duration_ms' => round($duration, 2),
                'used_gb' => round($mem->usedBytes / 1024**3, 2),
            ]);
        } else {
            $this->logger->error('Memory read failed', [
                'duration_ms' => round($duration, 2),
                'error' => $result->getError()->getMessage(),
            ]);
        }

        return $result;
    }
}
```

## Example: Rate-Limited Source

```php
use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;
use PHPeek\SystemMetrics\DTO\Result;

class RateLimitedCpuSource implements CpuMetricsSource
{
    private ?float $lastRead = null;
    private ?Result $lastResult = null;

    public function __construct(
        private CpuMetricsSource $inner,
        private float $minInterval = 0.1  // Min 100ms between reads
    ) {}

    public function read(): Result
    {
        $now = microtime(true);

        // Return cached result if within rate limit
        if ($this->lastRead !== null &&
            ($now - $this->lastRead) < $this->minInterval) {
            return $this->lastResult;
        }

        // Read fresh value
        $this->lastResult = $this->inner->read();
        $this->lastRead = $now;

        return $this->lastResult;
    }
}
```

## Configuration Methods

```php
use PHPeek\SystemMetrics\Config\SystemMetricsConfig;

// CPU metrics source
SystemMetricsConfig::setCpuMetricsSource($customCpuSource);

// Memory metrics source
SystemMetricsConfig::setMemoryMetricsSource($customMemorySource);

// Environment detector
SystemMetricsConfig::setEnvironmentDetector($customDetector);

// Other sources...
SystemMetricsConfig::setLoadAverageSource($customLoadSource);
SystemMetricsConfig::setUptimeSource($customUptimeSource);
SystemMetricsConfig::setStorageMetricsSource($customStorageSource);
SystemMetricsConfig::setNetworkMetricsSource($customNetworkSource);
```

## Best Practices

1. **Implement the interface completely** - All methods must return `Result<T>`
2. **Handle errors gracefully** - Return `Result::failure()` instead of throwing
3. **Preserve immutability** - Return readonly DTOs
4. **Add proper timestamps** - Include accurate `timestamp` fields
5. **Test thoroughly** - Verify both success and failure cases
6. **Document behavior** - Explain caching, rate limiting, etc.

## Related Documentation

- [Error Handling](error-handling.md) - Result<T> pattern
- [Architecture: Design Principles](../architecture/design-principles.md) - Interface-driven design
- [API Reference](../api-reference.md) - All interfaces and DTOs
