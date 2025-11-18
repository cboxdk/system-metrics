# Immutable DTOs

All data transfer objects are readonly value objects.

## Implementation

Uses PHP 8.3 readonly classes:

```php
readonly class CpuSnapshot {
    public function __construct(
        public CpuTimes $total,
        public array $perCore,
        public DateTimeImmutable $timestamp,
    ) {}

    public function coreCount(): int {
        return count($this->perCore);
    }
}

readonly class CpuTimes {
    public function __construct(
        public int $user,
        public int $system,
        public int $idle,
        public int $iowait,
        public int $irq,
        public int $softirq,
        public int $steal,
        public int $guest,
    ) {}

    public function total(): int {
        return $this->user + $this->system + $this->idle +
               $this->iowait + $this->irq + $this->softirq +
               $this->steal + $this->guest;
    }

    public function busy(): int {
        return $this->total() - $this->idle - $this->iowait;
    }
}
```

## Benefits

### No Accidental Mutations

```php
$cpu = SystemMetrics::cpu()->getValue();

// This would cause compile error:
// $cpu->total->user = 100;  // Error: Cannot modify readonly property
```

### Thread-Safe by Design

Readonly objects can be safely shared across threads without synchronization.

### Predictable Behavior

```php
function processCpu(CpuSnapshot $cpu) {
    // Can trust that $cpu won't change during execution
    $total1 = $cpu->total->total();
    doSomething();
    $total2 = $cpu->total->total();
    // Guaranteed: $total1 === $total2
}
```

### Clear Ownership Semantics

When you receive a readonly object:
- You know it won't change
- You can't change it
- Nobody else can change it

## Helper Methods

DTOs include calculated helper methods that are pure functions:

```php
// CpuSnapshot
$cpu->coreCount()  // Count of CPU cores

// CpuTimes
$cpu->total->total()  // Sum of all time fields
$cpu->total->busy()   // Total minus idle/iowait

// MemorySnapshot
$mem->usedPercentage()       // Used as percentage
$mem->availablePercentage()  // Available as percentage
$mem->swapUsedPercentage()   // Swap used as percentage

// LoadAverageSnapshot normalized
$normalized->oneMinutePercentage()     // Load as capacity %
$normalized->fiveMinutesPercentage()   // Load as capacity %
$normalized->fifteenMinutesPercentage()  // Load as capacity %

// UptimeSnapshot
$uptime->humanReadable()  // "5 days, 3 hours, 42 minutes"
$uptime->days()           // Days component
$uptime->hours()          // Hours component
$uptime->minutes()        // Minutes component
$uptime->totalHours()     // Total as decimal hours
$uptime->totalMinutes()   // Total as decimal minutes
```

All helpers are:
- Pure functions (no side effects)
- Deterministic (same input = same output)
- Readonly (don't modify state)

## Construction

DTOs use constructor property promotion for conciseness:

```php
// Before PHP 8.0 (verbose)
class CpuTimes {
    private int $user;
    private int $system;

    public function __construct(int $user, int $system) {
        $this->user = $user;
        $this->system = $system;
    }

    public function getUser(): int { return $this->user; }
    public function getSystem(): int { return $this->system; }
}

// PHP 8.3 (concise)
readonly class CpuTimes {
    public function __construct(
        public int $user,
        public int $system,
    ) {}
}
```

## Related Documentation

- [Design Principles](design-principles.md) - Overall architecture
- [API Reference](../api-reference.md) - All DTOs
