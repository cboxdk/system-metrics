# Error Handling with Result<T>

Master the Result<T> pattern for explicit error handling without exceptions.

## Overview

All SystemMetrics methods return `Result<T>` objects instead of throwing exceptions. This forces explicit error handling at compile time and prevents uncaught exceptions in production.

```php
use PHPeek\SystemMetrics\SystemMetrics;

$result = SystemMetrics::cpu();  // Returns Result<CpuSnapshot>
```

## Basic Pattern

### Check Before Using

```php
$result = SystemMetrics::cpu();

if ($result->isSuccess()) {
    $cpu = $result->getValue();
    echo "CPU cores: {$cpu->coreCount()}\n";
} else {
    $error = $result->getError();
    echo "Error: {$error->getMessage()}\n";
}
```

### Use Default Value

```php
$cpu = SystemMetrics::cpu()->getValueOr(null);

if ($cpu === null) {
    echo "Could not read CPU metrics\n";
} else {
    echo "CPU cores: {$cpu->coreCount()}\n";
}
```

## Functional Style

### Callbacks

```php
SystemMetrics::memory()
    ->onSuccess(function($mem) {
        echo "Memory: {$mem->totalBytes} bytes\n";
    })
    ->onFailure(function($error) {
        error_log("Memory read failed: " . $error->getMessage());
    });
```

### Transformations

```php
$result = SystemMetrics::cpu()->map(function($cpu) {
    return [
        'cores' => $cpu->coreCount(),
        'busy_percentage' => ($cpu->total->busy() / $cpu->total->total()) * 100,
    ];
});

if ($result->isSuccess()) {
    $data = $result->getValue();  // Array with 'cores' and 'busy_percentage'
}
```

## Error Types

### FileNotFoundException

File doesn't exist on the filesystem:

```php
$result = SystemMetrics::cpu();

if ($result->isFailure()) {
    $error = $result->getError();
    if ($error instanceof FileNotFoundException) {
        echo "/proc/stat not found - not on Linux?\n";
    }
}
```

### InsufficientPermissionsException

Lack permission to read file or execute command:

```php
$result = SystemMetrics->memory();

if ($result->isFailure()) {
    $error = $result->getError();
    if ($error instanceof InsufficientPermissionsException) {
        echo "Cannot read /proc/meminfo - permission denied\n";
    }
}
```

### ParseException

File format is unexpected or corrupted:

```php
$result = SystemMetrics::cpu();

if ($result->isFailure()) {
    $error = $result->getError();
    if ($error instanceof ParseException) {
        echo "/proc/stat has unexpected format\n";
    }
}
```

### UnsupportedOperatingSystemException

Operating system is not supported:

```php
$result = SystemMetrics::cpu();

if ($result->isFailure()) {
    $error = $result->getError();
    if ($error instanceof UnsupportedOperatingSystemException) {
        echo "Windows is not supported\n";
    }
}
```

### SystemMetricsException

Generic error (command failed, etc.):

```php
$result = SystemMetrics::memory();

if ($result->isFailure()) {
    $error = $result->getError();
    if ($error instanceof SystemMetricsException) {
        echo "System command failed: {$error->getMessage()}\n";
    }
}
```

## Complete Example

```php
use PHPeek\SystemMetrics\SystemMetrics;
use PHPeek\SystemMetrics\Exceptions\{
    FileNotFoundException,
    InsufficientPermissionsException,
    ParseException,
    UnsupportedOperatingSystemException,
    SystemMetricsException
};

$result = SystemMetrics::cpu();

if ($result->isSuccess()) {
    $cpu = $result->getValue();
    echo "CPU cores: {$cpu->coreCount()}\n";
} else {
    $error = $result->getError();

    match (true) {
        $error instanceof FileNotFoundException =>
            echo "❌ Required file not found: {$error->getMessage()}\n",
        $error instanceof InsufficientPermissionsException =>
            echo "❌ Permission denied: {$error->getMessage()}\n",
        $error instanceof ParseException =>
            echo "❌ Parse error: {$error->getMessage()}\n",
        $error instanceof UnsupportedOperatingSystemException =>
            echo "❌ Unsupported OS: {$error->getMessage()}\n",
        default =>
            echo "❌ Error: {$error->getMessage()}\n",
    };
}
```

## Best Practices

### Always Check Results

```php
// ✅ Good - explicit error handling
$result = SystemMetrics::cpu();
if ($result->isSuccess()) {
    $cpu = $result->getValue();
    // Use $cpu safely
}

// ❌ Bad - can throw if result is failure
$cpu = SystemMetrics::cpu()->getValue();
```

### Provide Fallbacks

```php
// ✅ Good - provides default
$cpu = SystemMetrics::cpu()->getValueOr(null);

// ✅ Good - handles failure
$memory = SystemMetrics::memory();
if ($memory->isFailure()) {
    // Use estimated values or skip this feature
}
```

### Log Failures

```php
SystemMetrics::cpu()
    ->onFailure(function($error) {
        error_log("Failed to read CPU: " . $error->getMessage());
        error_log("Stack trace: " . $error->getTraceAsString());
    });
```

## Testing with Stubs

For testing, you can create Result objects:

```php
use PHPeek\SystemMetrics\DTO\Result;

// Success result
$result = Result::success($fakeCpuSnapshot);

// Failure result
$result = Result::failure(new FileNotFoundException('/proc/stat'));
```

## Why Not Exceptions?

**Traditional approach (exceptions):**
- Can forget to catch, leading to uncaught exceptions in production
- Silent failures if caught too broadly
- No compile-time checking

**Result<T> approach:**
- Explicit handling required
- Failures are visible in type system
- Cannot accidentally ignore errors
- Functional programming patterns available

## Related Documentation

- [API Reference](../api-reference.md) - All return types
- [Custom Implementations](custom-implementations.md) - Creating custom sources
