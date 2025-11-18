# Design Principles

Core architectural philosophy and design decisions behind PHPeek System Metrics.

## Pure PHP

**No external dependencies.** The library reads directly from system sources:
- Linux: `/proc`, `/sys` filesystems
- macOS: System commands (`sysctl`, `vm_stat`, etc.)

**Benefits:**
- Works out of the box
- No compilation needed
- No version conflicts
- Easy deployment

## Strict Types

**`declare(strict_types=1)` everywhere.** Full type safety:
- PHPStan Level 9 compliance
- No implicit type coercion
- Catch type errors at static analysis time

**Benefits:**
- Prevents type-related bugs
- Self-documenting code
- Better IDE support
- Refactoring safety

## Immutable DTOs

**All DTOs use PHP 8.3 readonly classes:**

```php
readonly class CpuSnapshot {
    public function __construct(
        public CpuTimes $total,
        public array $perCore,
        public DateTimeImmutable $timestamp,
    ) {}
}
```

**Benefits:**
- No accidental mutations
- Thread-safe by design
- Predictable behavior
- Clear ownership semantics

See [Immutable DTOs](immutable-dtos.md) for details.

## Result Pattern

**No uncaught exceptions.** All operations return `Result<T>`:

```php
$result = SystemMetrics::cpu();
if ($result->isSuccess()) {
    $cpu = $result->getValue();
}
```

**Benefits:**
- Explicit error handling
- No surprise exceptions
- Functional programming style
- Type-safe error propagation

See [Result Pattern](result-pattern.md) for details.

## Interface-Driven

**All sources implement interfaces:**

```php
interface CpuMetricsSource {
    public function read(): Result;
}
```

**Benefits:**
- Easy to swap implementations
- Dependency injection friendly
- Testable with stubs
- Clear contracts

See [Custom Implementations](../advanced-usage/custom-implementations.md).

## Action Pattern

**Small, focused use cases:**

```php
class ReadCpuMetricsAction {
    public function execute(): Result {
        return $this->source->read();
    }
}
```

**Benefits:**
- Single responsibility
- Easy to test
- Clear boundaries
- Composable

See [Action Pattern](action-pattern.md) for details.

## Related Documentation

- [Result Pattern](result-pattern.md)
- [Composite Sources](composite-sources.md)
- [Immutable DTOs](immutable-dtos.md)
- [Action Pattern](action-pattern.md)
- [Performance Caching](performance-caching.md)
