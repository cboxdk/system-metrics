# Testing

Guide to running tests and understanding test coverage.

## Running Tests

### Quick Test Run

```bash
composer test
```

This runs the full test suite with Pest.

### With Coverage Report

```bash
composer test-coverage
```

Generates HTML coverage report in `coverage/` directory.

### Specific Test File

```bash
vendor/bin/pest tests/Unit/Parser/LinuxProcStatParserTest.php
```

### CI Mode (Strict Output)

```bash
vendor/bin/pest --ci
```

## Test Structure

```
tests/
├── Unit/                           # Unit tests
│   ├── DTO/                       # DTO tests
│   ├── Parser/                    # Parser tests
│   └── Support/                   # Support class tests
├── ExampleTest.php                # Integration tests
└── ArchTest.php                   # Architecture rules
```

## Test Categories

### Unit Tests (`tests/Unit/`)
Test individual components in isolation:
- Parsers (LinuxProcStatParser, etc.)
- DTOs (CpuTimes, MemorySnapshot, etc.)
- Support classes (FileReader, ProcessRunner, etc.)

### Integration Tests (`ExampleTest.php`)
Test the full stack on real system APIs:
- Actually reads from `/proc`, `/sys`, system commands
- Tests on the machine running the tests
- Platform-specific (tests run on Linux CI, macOS locally)

### Architecture Tests (`ArchTest.php`)
Enforce architectural rules:
- No debugging functions (dd, dump, ray)
- Consistent code organization
- Naming conventions

## Coverage

Current coverage: **89.9%** (94 tests, 238 assertions)

### Coverage by Component

- **Parsers:** 95%+
- **DTOs:** 100%
- **Support classes:** 90%+
- **Linux sources:** ~80% (limited on macOS CI)
- **macOS sources:** ~50% (limited on Linux CI)

### Why Not 100%?

Platform-specific code cannot be tested on both platforms simultaneously:
- Linux sources: 0% coverage when tests run on macOS
- macOS sources: 0% coverage when tests run on Linux

This is expected and acceptable.

## Writing Tests

### Pest Syntax

This project uses Pest v4:

```php
it('parses CPU times correctly', function () {
    $input = "cpu  100 0 50 200 0 0 0 0 0 0";
    $times = LinuxProcStatParser::parseCpuLine($input);

    expect($times->user)->toBe(100);
    expect($times->system)->toBe(50);
    expect($times->idle)->toBe(200);
});
```

### Testing with Result<T>

```php
it('returns success result', function () {
    $result = SystemMetrics::cpu();

    expect($result->isSuccess())->toBeTrue();
    expect($result->getValue())->toBeInstanceOf(CpuSnapshot::class);
});

it('handles failure gracefully', function () {
    // Set up failure condition
    $result = SystemMetrics::cpu();

    expect($result->isFailure())->toBeTrue();
    expect($result->getError())->toBeInstanceOf(Throwable::class);
});
```

### Testing DTOs

```php
it('calculates totals correctly', function () {
    $times = new CpuTimes(100, 50, 200, 0, 0, 0, 0, 0);

    expect($times->total())->toBe(350);
    expect($times->busy())->toBe(150);
});
```

## Code Quality

### Static Analysis

```bash
composer analyse
```

Runs PHPStan at Level 9 (strictest).

### Code Style

```bash
composer format
```

Formats code with Laravel Pint (PSR-12).

## Continuous Integration

GitHub Actions runs:
- Tests on Ubuntu (Linux) and macOS
- PHP 8.3 and 8.4
- Automatic code formatting
- PHPStan analysis
- Coverage generation

## Best Practices

1. **Test behavior, not implementation**
2. **Use descriptive test names**
3. **Keep tests independent** (no shared state)
4. **Mock external dependencies** (files, commands)
5. **Test edge cases** (empty values, errors, boundaries)
6. **Maintain high coverage** (aim for >85%)

## Related Documentation

- [Contributing](../CONTRIBUTING.md) - Contribution guidelines
- [Architecture](architecture/design-principles.md) - Design principles
