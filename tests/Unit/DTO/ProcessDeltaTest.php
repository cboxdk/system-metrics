<?php

declare(strict_types=1);

use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use Cbox\SystemMetrics\DTO\Metrics\Process\ProcessDelta;

it('can be instantiated with all values', function () {
    $cpuDelta = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $startTime = new DateTimeImmutable('2024-01-01 10:00:00');
    $endTime = new DateTimeImmutable('2024-01-01 10:00:10');

    $delta = new ProcessDelta(
        pid: 1234,
        cpuDelta: $cpuDelta,
        memoryDeltaBytes: 1048576,
        durationSeconds: 10.0,
        startTime: $startTime,
        endTime: $endTime
    );

    expect($delta->pid)->toBe(1234);
    expect($delta->cpuDelta)->toBe($cpuDelta);
    expect($delta->memoryDeltaBytes)->toBe(1048576);
    expect($delta->durationSeconds)->toBe(10.0);
    expect($delta->startTime)->toBe($startTime);
    expect($delta->endTime)->toBe($endTime);
});

it('calculates CPU usage percentage correctly', function () {
    // 150 ticks over 10 seconds = 1.5 seconds of CPU time = 15% usage
    $cpuDelta = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $delta = new ProcessDelta(
        pid: 1234,
        cpuDelta: $cpuDelta,
        memoryDeltaBytes: 0,
        durationSeconds: 10.0,
        startTime: new DateTimeImmutable,
        endTime: new DateTimeImmutable
    );

    expect($delta->cpuUsagePercentage())->toBe(15.0);
});

it('calculates 100% CPU usage correctly', function () {
    // 1000 ticks over 10 seconds = 10 seconds of CPU time = 100% usage
    $cpuDelta = new CpuTimes(
        user: 800,
        nice: 0,
        system: 200,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $delta = new ProcessDelta(
        pid: 1234,
        cpuDelta: $cpuDelta,
        memoryDeltaBytes: 0,
        durationSeconds: 10.0,
        startTime: new DateTimeImmutable,
        endTime: new DateTimeImmutable
    );

    expect($delta->cpuUsagePercentage())->toBe(100.0);
});

it('returns zero CPU usage when duration is zero', function () {
    $cpuDelta = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $delta = new ProcessDelta(
        pid: 1234,
        cpuDelta: $cpuDelta,
        memoryDeltaBytes: 0,
        durationSeconds: 0.0,
        startTime: new DateTimeImmutable,
        endTime: new DateTimeImmutable
    );

    expect($delta->cpuUsagePercentage())->toBe(0.0);
});

it('handles zero CPU ticks', function () {
    $cpuDelta = new CpuTimes(
        user: 0,
        nice: 0,
        system: 0,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $delta = new ProcessDelta(
        pid: 1234,
        cpuDelta: $cpuDelta,
        memoryDeltaBytes: 0,
        durationSeconds: 10.0,
        startTime: new DateTimeImmutable,
        endTime: new DateTimeImmutable
    );

    expect($delta->cpuUsagePercentage())->toBe(0.0);
});

it('handles negative memory delta', function () {
    $cpuDelta = new CpuTimes(
        user: 0,
        nice: 0,
        system: 0,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $delta = new ProcessDelta(
        pid: 1234,
        cpuDelta: $cpuDelta,
        memoryDeltaBytes: -1048576, // Memory decreased
        durationSeconds: 10.0,
        startTime: new DateTimeImmutable,
        endTime: new DateTimeImmutable
    );

    expect($delta->memoryDeltaBytes)->toBe(-1048576);
});

it('calculates multi-core CPU usage correctly', function () {
    // 2000 ticks over 10 seconds = 20 seconds of CPU time = 200% usage (2 cores fully utilized)
    $cpuDelta = new CpuTimes(
        user: 1500,
        nice: 0,
        system: 500,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $delta = new ProcessDelta(
        pid: 1234,
        cpuDelta: $cpuDelta,
        memoryDeltaBytes: 0,
        durationSeconds: 10.0,
        startTime: new DateTimeImmutable,
        endTime: new DateTimeImmutable
    );

    expect($delta->cpuUsagePercentage())->toBe(200.0);
});

it('is immutable', function () {
    $cpuDelta = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $delta = new ProcessDelta(
        pid: 1234,
        cpuDelta: $cpuDelta,
        memoryDeltaBytes: 1048576,
        durationSeconds: 10.0,
        startTime: new DateTimeImmutable,
        endTime: new DateTimeImmutable
    );

    $reflection = new ReflectionClass($delta);
    expect($reflection->isReadOnly())->toBeTrue();
});
