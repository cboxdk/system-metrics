<?php

declare(strict_types=1);

use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuCoreTimes;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use Cbox\SystemMetrics\DTO\Metrics\LoadAverageSnapshot;

it('can be instantiated with all values', function () {
    $load = new LoadAverageSnapshot(
        oneMinute: 2.45,
        fiveMinutes: 1.80,
        fifteenMinutes: 1.20
    );

    expect($load->oneMinute)->toBe(2.45);
    expect($load->fiveMinutes)->toBe(1.80);
    expect($load->fifteenMinutes)->toBe(1.20);
});

it('can normalize load average with CPU snapshot', function () {
    $load = new LoadAverageSnapshot(
        oneMinute: 4.0,
        fiveMinutes: 3.0,
        fifteenMinutes: 2.0
    );

    $cpuTimes = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 850,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $cpu = new CpuSnapshot(
        total: $cpuTimes,
        perCore: [
            new CpuCoreTimes(coreIndex: 0, times: $cpuTimes),
            new CpuCoreTimes(coreIndex: 1, times: $cpuTimes),
            new CpuCoreTimes(coreIndex: 2, times: $cpuTimes),
            new CpuCoreTimes(coreIndex: 3, times: $cpuTimes),
            new CpuCoreTimes(coreIndex: 4, times: $cpuTimes),
            new CpuCoreTimes(coreIndex: 5, times: $cpuTimes),
            new CpuCoreTimes(coreIndex: 6, times: $cpuTimes),
            new CpuCoreTimes(coreIndex: 7, times: $cpuTimes),
        ]
    );

    $normalized = $load->normalized($cpu);

    expect($normalized->oneMinute)->toBe(0.5); // 4.0 / 8 = 0.5
    expect($normalized->fiveMinutes)->toBe(0.375); // 3.0 / 8 = 0.375
    expect($normalized->fifteenMinutes)->toBe(0.25); // 2.0 / 8 = 0.25
    expect($normalized->coreCount)->toBe(8);
});

it('handles zero core count gracefully', function () {
    $load = new LoadAverageSnapshot(
        oneMinute: 2.0,
        fiveMinutes: 1.5,
        fifteenMinutes: 1.0
    );

    $cpuTimes = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 850,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $cpu = new CpuSnapshot(
        total: $cpuTimes,
        perCore: [] // No cores
    );

    $normalized = $load->normalized($cpu);

    expect($normalized->oneMinute)->toBe(0.0);
    expect($normalized->fiveMinutes)->toBe(0.0);
    expect($normalized->fifteenMinutes)->toBe(0.0);
    expect($normalized->coreCount)->toBe(0);
});

it('handles zero load values', function () {
    $load = new LoadAverageSnapshot(
        oneMinute: 0.0,
        fiveMinutes: 0.0,
        fifteenMinutes: 0.0
    );

    expect($load->oneMinute)->toBe(0.0);
    expect($load->fiveMinutes)->toBe(0.0);
    expect($load->fifteenMinutes)->toBe(0.0);
});

it('handles high load values', function () {
    $load = new LoadAverageSnapshot(
        oneMinute: 256.48,
        fiveMinutes: 128.92,
        fifteenMinutes: 64.23
    );

    expect($load->oneMinute)->toBe(256.48);
    expect($load->fiveMinutes)->toBe(128.92);
    expect($load->fifteenMinutes)->toBe(64.23);
});

it('normalized load equals 1.0 when load equals core count', function () {
    $load = new LoadAverageSnapshot(
        oneMinute: 8.0,
        fiveMinutes: 8.0,
        fifteenMinutes: 8.0
    );

    $cpuTimes = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 850,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $cpu = new CpuSnapshot(
        total: $cpuTimes,
        perCore: array_map(
            fn (int $i) => new CpuCoreTimes(coreIndex: $i, times: $cpuTimes),
            range(0, 7)
        )
    );

    $normalized = $load->normalized($cpu);

    expect($normalized->oneMinute)->toBe(1.0);
    expect($normalized->fiveMinutes)->toBe(1.0);
    expect($normalized->fifteenMinutes)->toBe(1.0);
});

it('normalized load exceeds 1.0 for overloaded systems', function () {
    $load = new LoadAverageSnapshot(
        oneMinute: 16.0,
        fiveMinutes: 12.0,
        fifteenMinutes: 10.0
    );

    $cpuTimes = new CpuTimes(
        user: 100,
        nice: 0,
        system: 50,
        idle: 850,
        iowait: 0,
        irq: 0,
        softirq: 0,
        steal: 0
    );

    $cpu = new CpuSnapshot(
        total: $cpuTimes,
        perCore: array_map(
            fn (int $i) => new CpuCoreTimes(coreIndex: $i, times: $cpuTimes),
            range(0, 7)
        )
    );

    $normalized = $load->normalized($cpu);

    expect($normalized->oneMinute)->toBe(2.0); // 16 / 8 = 2.0 (200% capacity)
    expect($normalized->fiveMinutes)->toBe(1.5); // 12 / 8 = 1.5 (150% capacity)
    expect($normalized->fifteenMinutes)->toBe(1.25); // 10 / 8 = 1.25 (125% capacity)
});

it('is immutable', function () {
    $load = new LoadAverageSnapshot(
        oneMinute: 2.45,
        fiveMinutes: 1.80,
        fifteenMinutes: 1.20
    );

    $reflection = new ReflectionClass($load);
    expect($reflection->isReadOnly())->toBeTrue();
});
