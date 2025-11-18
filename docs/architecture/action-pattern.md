# Action Pattern

Small, focused use case implementations.

## Pattern

Each use case is encapsulated in an Action class:

```php
class ReadCpuMetricsAction
{
    public function __construct(
        private CpuMetricsSource $source
    ) {}

    public function execute(): Result
    {
        return $this->source->read();
    }
}
```

## Benefits

### Single Responsibility

Each action does ONE thing:
- `ReadCpuMetricsAction` - Reads CPU metrics
- `DetectEnvironmentAction` - Detects environment
- `ReadMemoryMetricsAction` - Reads memory metrics

### Easy Dependency Injection

```php
$action = new ReadCpuMetricsAction(
    new RedisCachedCpuSource($redis)
);

$result = $action->execute();
```

### Testable in Isolation

```php
// Unit test with stub
$stub = $this->createStub(CpuMetricsSource::class);
$stub->method('read')->willReturn(Result::success($fakeCpu));

$action = new ReadCpuMetricsAction($stub);
$result = $action->execute();

$this->assertTrue($result->isSuccess());
```

### Clear Boundaries

Actions define clear entry points:
- Input: Constructor dependencies
- Output: `Result<T>` from `execute()`
- Side effects: Explicit and documented

## Implementation

All actions follow the same structure:

```php
class SomeAction
{
    public function __construct(
        private SomeSource $source
    ) {}

    public function execute(): Result
    {
        // Thin orchestration - delegate to sources
        return $this->source->doSomething();
    }
}
```

Actions are **thin orchestrators** - they delegate actual work to sources/detectors/parsers.

## Usage via Facade

The `SystemMetrics` facade uses actions internally:

```php
class SystemMetrics
{
    public static function cpu(): Result
    {
        $action = new ReadCpuMetricsAction(
            SystemMetricsConfig::getCpuMetricsSource()
        );
        return $action->execute();
    }
}
```

Users typically use the facade, but can instantiate actions directly for dependency injection.

## Available Actions

- `DetectEnvironmentAction` - Environment detection
- `ReadCpuMetricsAction` - CPU metrics
- `ReadMemoryMetricsAction` - Memory metrics
- `ReadLoadAverageAction` - Load average
- `ReadUptimeAction` - System uptime
- `ReadStorageMetricsAction` - Storage/disk metrics
- `ReadNetworkMetricsAction` - Network metrics
- `ReadContainerMetricsAction` - Container/cgroup metrics
- `SystemOverviewAction` - Complete system snapshot
- And more...

See [API Reference](../api-reference.md) for complete list.

## Related Documentation

- [Design Principles](design-principles.md) - Overall architecture
- [Custom Implementations](../advanced-usage/custom-implementations.md) - Inject custom sources
