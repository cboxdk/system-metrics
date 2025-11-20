<?php

use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuCoreDelta;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuDelta;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;

it('calculates total system CPU usage percentage correctly (0-100%)', function () {
    $delta = new CpuDelta(
        totalDelta: new CpuTimes(
            user: 40, nice: 0, system: 10, idle: 150,
            iowait: 0, irq: 0, softirq: 0, steal: 0
        ),
        perCoreDelta: [
            new CpuCoreDelta(0, new CpuTimes(20, 0, 5, 75, 0, 0, 0, 0)),
            new CpuCoreDelta(1, new CpuTimes(20, 0, 5, 75, 0, 0, 0, 0)),
        ],
        durationSeconds: 1.0,
        startTime: new DateTimeImmutable('2024-01-01 10:00:00'),
        endTime: new DateTimeImmutable('2024-01-01 10:00:01')
    );

    // total busy = 50, total ticks = 200, raw = 25%
    // 2 cores, so normalized total = 25%
    expect($delta->usagePercentage())->toBe(25.0);
});

it('calculates per-core average usage percentage', function () {
    $delta = new CpuDelta(
        totalDelta: new CpuTimes(
            user: 40, nice: 0, system: 10, idle: 150,
            iowait: 0, irq: 0, softirq: 0, steal: 0
        ),
        perCoreDelta: [
            new CpuCoreDelta(0, new CpuTimes(20, 0, 5, 75, 0, 0, 0, 0)),
            new CpuCoreDelta(1, new CpuTimes(20, 0, 5, 75, 0, 0, 0, 0)),
        ],
        durationSeconds: 1.0,
        startTime: new DateTimeImmutable('2024-01-01 10:00:00'),
        endTime: new DateTimeImmutable('2024-01-01 10:00:01')
    );

    // total busy = 50, total ticks = 200, raw = 25%
    // per-core = 25% / 2 = 12.5%
    expect($delta->usagePercentagePerCore())->toBe(12.5);
});

it('calculates user percentage correctly', function () {
    $delta = new CpuDelta(
        totalDelta: new CpuTimes(
            user: 30, nice: 0, system: 10, idle: 60,
            iowait: 0, irq: 0, softirq: 0, steal: 0
        ),
        perCoreDelta: [],
        durationSeconds: 1.0,
        startTime: new DateTimeImmutable('2024-01-01 10:00:00'),
        endTime: new DateTimeImmutable('2024-01-01 10:00:01')
    );

    // user = 30, total = 100, percentage = 30%
    expect($delta->userPercentage())->toBe(30.0);
});

it('calculates system percentage correctly', function () {
    $delta = new CpuDelta(
        totalDelta: new CpuTimes(
            user: 30, nice: 0, system: 20, idle: 50,
            iowait: 0, irq: 0, softirq: 0, steal: 0
        ),
        perCoreDelta: [],
        durationSeconds: 1.0,
        startTime: new DateTimeImmutable('2024-01-01 10:00:00'),
        endTime: new DateTimeImmutable('2024-01-01 10:00:01')
    );

    // system = 20, total = 100, percentage = 20%
    expect($delta->systemPercentage())->toBe(20.0);
});

it('calculates idle percentage correctly', function () {
    $delta = new CpuDelta(
        totalDelta: new CpuTimes(
            user: 10, nice: 0, system: 5, idle: 85,
            iowait: 0, irq: 0, softirq: 0, steal: 0
        ),
        perCoreDelta: [],
        durationSeconds: 1.0,
        startTime: new DateTimeImmutable('2024-01-01 10:00:00'),
        endTime: new DateTimeImmutable('2024-01-01 10:00:01')
    );

    // idle = 85, total = 100, percentage = 85%
    expect($delta->idlePercentage())->toBe(85.0);
});

it('calculates iowait percentage correctly', function () {
    $delta = new CpuDelta(
        totalDelta: new CpuTimes(
            user: 10, nice: 0, system: 5, idle: 75,
            iowait: 10, irq: 0, softirq: 0, steal: 0
        ),
        perCoreDelta: [],
        durationSeconds: 1.0,
        startTime: new DateTimeImmutable('2024-01-01 10:00:00'),
        endTime: new DateTimeImmutable('2024-01-01 10:00:01')
    );

    // iowait = 10, total = 100, percentage = 10%
    expect($delta->iowaitPercentage())->toBe(10.0);
});

it('returns zero percentage when duration is zero', function () {
    $delta = new CpuDelta(
        totalDelta: new CpuTimes(100, 50, 200, 0, 0, 0, 0, 0),
        perCoreDelta: [],
        durationSeconds: 0.0,
        startTime: new DateTimeImmutable('2024-01-01 10:00:00'),
        endTime: new DateTimeImmutable('2024-01-01 10:00:00')
    );

    expect($delta->usagePercentage())->toBe(0.0);
    expect($delta->userPercentage())->toBe(0.0);
    expect($delta->systemPercentage())->toBe(0.0);
    expect($delta->idlePercentage())->toBe(0.0);
});

it('returns zero percentage when total delta is zero', function () {
    $delta = new CpuDelta(
        totalDelta: new CpuTimes(0, 0, 0, 0, 0, 0, 0, 0),
        perCoreDelta: [],
        durationSeconds: 1.0,
        startTime: new DateTimeImmutable('2024-01-01 10:00:00'),
        endTime: new DateTimeImmutable('2024-01-01 10:00:01')
    );

    expect($delta->usagePercentage())->toBe(0.0);
    expect($delta->userPercentage())->toBe(0.0);
});

it('gets core usage percentage by index', function () {
    $delta = new CpuDelta(
        totalDelta: new CpuTimes(100, 0, 0, 300, 0, 0, 0, 0),
        perCoreDelta: [
            new CpuCoreDelta(0, new CpuTimes(50, 0, 0, 150, 0, 0, 0, 0)),
            new CpuCoreDelta(1, new CpuTimes(50, 0, 0, 150, 0, 0, 0, 0)),
        ],
        durationSeconds: 1.0,
        startTime: new DateTimeImmutable('2024-01-01 10:00:00'),
        endTime: new DateTimeImmutable('2024-01-01 10:00:01')
    );

    // Core 0: busy=50, total=200, usage=25%
    expect($delta->coreUsagePercentage(0))->toBe(25.0);
    expect($delta->coreUsagePercentage(1))->toBe(25.0);
    expect($delta->coreUsagePercentage(999))->toBeNull();
});

it('finds the busiest core', function () {
    $delta = new CpuDelta(
        totalDelta: new CpuTimes(150, 0, 0, 250, 0, 0, 0, 0),
        perCoreDelta: [
            new CpuCoreDelta(0, new CpuTimes(50, 0, 0, 150, 0, 0, 0, 0)), // 25% usage
            new CpuCoreDelta(1, new CpuTimes(100, 0, 0, 100, 0, 0, 0, 0)), // 50% usage
        ],
        durationSeconds: 1.0,
        startTime: new DateTimeImmutable('2024-01-01 10:00:00'),
        endTime: new DateTimeImmutable('2024-01-01 10:00:01')
    );

    $busiest = $delta->busiestCore();
    expect($busiest)->not->toBeNull();
    expect($busiest->coreIndex)->toBe(1);
    expect($busiest->usagePercentage())->toBe(50.0);
});

it('finds the idlest core', function () {
    $delta = new CpuDelta(
        totalDelta: new CpuTimes(150, 0, 0, 250, 0, 0, 0, 0),
        perCoreDelta: [
            new CpuCoreDelta(0, new CpuTimes(50, 0, 0, 150, 0, 0, 0, 0)), // 25% usage
            new CpuCoreDelta(1, new CpuTimes(100, 0, 0, 100, 0, 0, 0, 0)), // 50% usage
        ],
        durationSeconds: 1.0,
        startTime: new DateTimeImmutable('2024-01-01 10:00:00'),
        endTime: new DateTimeImmutable('2024-01-01 10:00:01')
    );

    $idlest = $delta->idlestCore();
    expect($idlest)->not->toBeNull();
    expect($idlest->coreIndex)->toBe(0);
    expect($idlest->usagePercentage())->toBe(25.0);
});

it('returns null for busiest/idlest when no cores', function () {
    $delta = new CpuDelta(
        totalDelta: new CpuTimes(100, 0, 0, 300, 0, 0, 0, 0),
        perCoreDelta: [],
        durationSeconds: 1.0,
        startTime: new DateTimeImmutable('2024-01-01 10:00:00'),
        endTime: new DateTimeImmutable('2024-01-01 10:00:01')
    );

    expect($delta->busiestCore())->toBeNull();
    expect($delta->idlestCore())->toBeNull();
});

it('returns zero usage percentage when no cores', function () {
    $delta = new CpuDelta(
        totalDelta: new CpuTimes(100, 0, 0, 300, 0, 0, 0, 0),
        perCoreDelta: [],
        durationSeconds: 1.0,
        startTime: new DateTimeImmutable('2024-01-01 10:00:00'),
        endTime: new DateTimeImmutable('2024-01-01 10:00:01')
    );

    expect($delta->usagePercentage())->toBe(0.0);
    expect($delta->usagePercentagePerCore())->toBe(0.0);
});

it('handles fully utilized multi-core system', function () {
    // Multi-core system with both cores fully utilized
    $delta = new CpuDelta(
        totalDelta: new CpuTimes(
            user: 200, nice: 0, system: 0, idle: 0,
            iowait: 0, irq: 0, softirq: 0, steal: 0
        ),
        perCoreDelta: [
            new CpuCoreDelta(0, new CpuTimes(100, 0, 0, 0, 0, 0, 0, 0)),
            new CpuCoreDelta(1, new CpuTimes(100, 0, 0, 0, 0, 0, 0, 0)),
        ],
        durationSeconds: 1.0,
        startTime: new DateTimeImmutable('2024-01-01 10:00:00'),
        endTime: new DateTimeImmutable('2024-01-01 10:00:01')
    );

    // Both cores 100% busy: total system usage = 100%
    expect($delta->usagePercentage())->toBe(100.0);
    // Per-core average: 100% / 2 = 50%
    expect($delta->usagePercentagePerCore())->toBe(50.0);
});

it('is immutable', function () {
    $delta = new CpuDelta(
        totalDelta: new CpuTimes(100, 0, 0, 300, 0, 0, 0, 0),
        perCoreDelta: [],
        durationSeconds: 1.0,
        startTime: new DateTimeImmutable('2024-01-01 10:00:00'),
        endTime: new DateTimeImmutable('2024-01-01 10:00:01')
    );

    expect($delta)->toBeInstanceOf(CpuDelta::class);

    $reflection = new ReflectionClass($delta);
    expect($reflection->isReadOnly())->toBeTrue();
});
