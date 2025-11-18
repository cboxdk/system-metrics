# Result<T> Pattern

Explicit error handling without exceptions.

## Philosophy

Return `Result<T>` objects that force explicit error handling at compile time instead of throwing exceptions.

## Benefits

- **No uncaught exceptions** - Failures are explicit in the type system
- **Visible in type system** - `Result<T>` clearly indicates operation can fail
- **Cannot ignore errors** - Must check success/failure explicitly
- **Functional programming** - Supports map, flatMap, onSuccess, onFailure

## Implementation

```php
interface ResultInterface {
    public function isSuccess(): bool;
    public function isFailure(): bool;
    public function getValue(): mixed;
    public function getValueOr(mixed $default): mixed;
    public function getError(): ?Throwable;
    public function map(callable $fn): Result;
    public function onSuccess(callable $fn): Result;
    public function onFailure(callable $fn): Result;
}
```

## Usage

### Check Before Using

```php
$result = SystemMetrics::cpu();

if ($result->isSuccess()) {
    $cpu = $result->getValue();
    // Use $cpu safely
} else {
    $error = $result->getError();
    // Handle error
}
```

### Provide Default Value

```php
$cpu = SystemMetrics::cpu()->getValueOr(null);

if ($cpu === null) {
    // Handle failure
}
```

### Functional Style

```php
SystemMetrics::memory()
    ->map(fn($mem) => $mem->usedPercentage())
    ->onSuccess(fn($pct) => echo "Memory: {$pct}%\n")
    ->onFailure(fn($err) => error_log($err->getMessage()));
```

## Comparison with Exceptions

### Traditional Exceptions

```php
// Easy to forget try/catch
$cpu = getCpuMetrics();  // Can throw!

// Broad catches hide errors
try {
    // lots of code
} catch (Exception $e) {
    // What threw?
}
```

### Result<T> Pattern

```php
// Must handle explicitly
$result = SystemMetrics::cpu();
if ($result->isSuccess()) {
    // Can only get value after checking
    $cpu = $result->getValue();
}

// Errors are explicit
if ($result->isFailure()) {
    $error = $result->getError();
    // Know exactly what failed
}
```

## Related Documentation

- [Error Handling](../advanced-usage/error-handling.md) - Complete guide
- [API Reference](../api-reference.md) - All Result<T> methods
