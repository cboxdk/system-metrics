<?php

declare(strict_types=1);

use DateTimeImmutable;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessDelta;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessResourceUsage;
use PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessStats;

it('can be instantiated with all values', function () {
    $currentCpu = new CpuTimes(
        user: 300,
        nice: 0,
        system: 150,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $peakCpu = new CpuTimes(
        user: 500,
        nice: 0,
        system: 250,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $avgCpu = new CpuTimes(
        user: 400,
        nice: 0,
        system: 200,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $current = new ProcessResourceUsage(
        cpuTimes: $currentCpu,
        memoryRssBytes: 10485760,
        memoryVmsBytes: 20971520,
        threadCount: 4,
        openFileDescriptors: 10
    );

    $peak = new ProcessResourceUsage(
        cpuTimes: $peakCpu,
        memoryRssBytes: 15728640,
        memoryVmsBytes: 31457280,
        threadCount: 8,
        openFileDescriptors: 20
    );

    $average = new ProcessResourceUsage(
        cpuTimes: $avgCpu,
        memoryRssBytes: 12582912,
        memoryVmsBytes: 25165824,
        threadCount: 6,
        openFileDescriptors: 15
    );

    $deltaCpu = new CpuTimes(
        user: 300,
        nice: 0,
        system: 150,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $delta = new ProcessDelta(
        pid: 1234,
        cpuDelta: $deltaCpu,
        memoryDeltaBytes: 5242880,
        durationSeconds: 60.0,
        startTime: new DateTimeImmutable('2024-01-01 00:00:00'),
        endTime: new DateTimeImmutable('2024-01-01 00:01:00')
    );

    $stats = new ProcessStats(
        pid: 1234,
        current: $current,
        peak: $peak,
        average: $average,
        delta: $delta,
        sampleCount: 10,
        totalDurationSeconds: 60.0,
        processCount: 1
    );

    expect($stats->pid)->toBe(1234);
    expect($stats->current)->toBe($current);
    expect($stats->peak)->toBe($peak);
    expect($stats->average)->toBe($average);
    expect($stats->delta)->toBe($delta);
    expect($stats->sampleCount)->toBe(10);
    expect($stats->totalDurationSeconds)->toBe(60.0);
    expect($stats->processCount)->toBe(1);
});

it('handles single sample', function () {
    $cpu = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $resources = new ProcessResourceUsage(
        cpuTimes: $cpu,
        memoryRssBytes: 10485760,
        memoryVmsBytes: 20971520,
        threadCount: 4,
        openFileDescriptors: 10
    );

    $delta = new ProcessDelta(
        pid: 1234,
        cpuDelta: $cpu,
        memoryDeltaBytes: 0,
        durationSeconds: 0.0,
        startTime: new DateTimeImmutable,
        endTime: new DateTimeImmutable
    );

    $stats = new ProcessStats(
        pid: 1234,
        current: $resources,
        peak: $resources,
        average: $resources,
        delta: $delta,
        sampleCount: 1,
        totalDurationSeconds: 0.0,
        processCount: 1
    );

    expect($stats->sampleCount)->toBe(1);
    expect($stats->current)->toBe($stats->peak);
    expect($stats->current)->toBe($stats->average);
});

it('handles process group with multiple processes', function () {
    $cpu = new CpuTimes(
        user: 1000,
        nice: 0,
        system: 500,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $resources = new ProcessResourceUsage(
        cpuTimes: $cpu,
        memoryRssBytes: 52428800, // 50 MB
        memoryVmsBytes: 104857600, // 100 MB
        threadCount: 12,
        openFileDescriptors: 50
    );

    $delta = new ProcessDelta(
        pid: 1234,
        cpuDelta: $cpu,
        memoryDeltaBytes: 10485760,
        durationSeconds: 30.0,
        startTime: new DateTimeImmutable,
        endTime: new DateTimeImmutable('+30 seconds')
    );

    $stats = new ProcessStats(
        pid: 1234,
        current: $resources,
        peak: $resources,
        average: $resources,
        delta: $delta,
        sampleCount: 5,
        totalDurationSeconds: 30.0,
        processCount: 5 // Parent + 4 children
    );

    expect($stats->processCount)->toBe(5);
});

it('shows peak values are higher than or equal to current', function () {
    $currentCpu = new CpuTimes(
        user: 300,
        nice: 0,
        system: 150,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $peakCpu = new CpuTimes(
        user: 500,
        nice: 0,
        system: 250,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $avgCpu = new CpuTimes(
        user: 400,
        nice: 0,
        system: 200,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $current = new ProcessResourceUsage(
        cpuTimes: $currentCpu,
        memoryRssBytes: 10485760,
        memoryVmsBytes: 20971520,
        threadCount: 4,
        openFileDescriptors: 10
    );

    $peak = new ProcessResourceUsage(
        cpuTimes: $peakCpu,
        memoryRssBytes: 15728640,
        memoryVmsBytes: 31457280,
        threadCount: 8,
        openFileDescriptors: 20
    );

    $average = new ProcessResourceUsage(
        cpuTimes: $avgCpu,
        memoryRssBytes: 12582912,
        memoryVmsBytes: 25165824,
        threadCount: 6,
        openFileDescriptors: 15
    );

    $deltaCpu = new CpuTimes(
        user: 300,
        nice: 0,
        system: 150,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $delta = new ProcessDelta(
        pid: 1234,
        cpuDelta: $deltaCpu,
        memoryDeltaBytes: 5242880,
        durationSeconds: 60.0,
        startTime: new DateTimeImmutable,
        endTime: new DateTimeImmutable('+60 seconds')
    );

    $stats = new ProcessStats(
        pid: 1234,
        current: $current,
        peak: $peak,
        average: $average,
        delta: $delta,
        sampleCount: 10,
        totalDurationSeconds: 60.0
    );

    expect($stats->peak->memoryRssBytes)->toBeGreaterThanOrEqual($stats->current->memoryRssBytes);
    expect($stats->peak->memoryVmsBytes)->toBeGreaterThanOrEqual($stats->current->memoryVmsBytes);
    expect($stats->peak->threadCount)->toBeGreaterThanOrEqual($stats->current->threadCount);
    expect($stats->peak->cpuTimes->total())->toBeGreaterThanOrEqual($stats->current->cpuTimes->total());
});

it('shows average values between current and peak', function () {
    $currentCpu = new CpuTimes(
        user: 300,
        nice: 0,
        system: 150,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $peakCpu = new CpuTimes(
        user: 500,
        nice: 0,
        system: 250,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $avgCpu = new CpuTimes(
        user: 400,
        nice: 0,
        system: 200,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $current = new ProcessResourceUsage(
        cpuTimes: $currentCpu,
        memoryRssBytes: 10485760,
        memoryVmsBytes: 20971520,
        threadCount: 4,
        openFileDescriptors: 10
    );

    $peak = new ProcessResourceUsage(
        cpuTimes: $peakCpu,
        memoryRssBytes: 15728640,
        memoryVmsBytes: 31457280,
        threadCount: 8,
        openFileDescriptors: 20
    );

    $average = new ProcessResourceUsage(
        cpuTimes: $avgCpu,
        memoryRssBytes: 12582912,
        memoryVmsBytes: 25165824,
        threadCount: 6,
        openFileDescriptors: 15
    );

    $deltaCpu = new CpuTimes(
        user: 300,
        nice: 0,
        system: 150,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $delta = new ProcessDelta(
        pid: 1234,
        cpuDelta: $deltaCpu,
        memoryDeltaBytes: 2097152,
        durationSeconds: 60.0,
        startTime: new DateTimeImmutable,
        endTime: new DateTimeImmutable('+60 seconds')
    );

    $stats = new ProcessStats(
        pid: 1234,
        current: $current,
        peak: $peak,
        average: $average,
        delta: $delta,
        sampleCount: 10,
        totalDurationSeconds: 60.0
    );

    expect($stats->average->memoryRssBytes)->toBeGreaterThanOrEqual($stats->current->memoryRssBytes);
    expect($stats->average->memoryRssBytes)->toBeLessThanOrEqual($stats->peak->memoryRssBytes);
});

it('handles zero duration', function () {
    $cpu = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $resources = new ProcessResourceUsage(
        cpuTimes: $cpu,
        memoryRssBytes: 10485760,
        memoryVmsBytes: 20971520,
        threadCount: 4,
        openFileDescriptors: 10
    );

    $delta = new ProcessDelta(
        pid: 1234,
        cpuDelta: $cpu,
        memoryDeltaBytes: 0,
        durationSeconds: 0.0,
        startTime: new DateTimeImmutable,
        endTime: new DateTimeImmutable
    );

    $stats = new ProcessStats(
        pid: 1234,
        current: $resources,
        peak: $resources,
        average: $resources,
        delta: $delta,
        sampleCount: 2,
        totalDurationSeconds: 0.0
    );

    expect($stats->totalDurationSeconds)->toBe(0.0);
});

it('is immutable', function () {
    $cpu = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 0,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $resources = new ProcessResourceUsage(
        cpuTimes: $cpu,
        memoryRssBytes: 10485760,
        memoryVmsBytes: 20971520,
        threadCount: 4,
        openFileDescriptors: 10
    );

    $delta = new ProcessDelta(
        pid: 1234,
        cpuDelta: $cpu,
        memoryDeltaBytes: 1048576,
        durationSeconds: 60.0,
        startTime: new DateTimeImmutable,
        endTime: new DateTimeImmutable('+60 seconds')
    );

    $stats = new ProcessStats(
        pid: 1234,
        current: $resources,
        peak: $resources,
        average: $resources,
        delta: $delta,
        sampleCount: 10,
        totalDurationSeconds: 60.0
    );

    $reflection = new ReflectionClass($stats);
    expect($reflection->isReadOnly())->toBeTrue();
});
