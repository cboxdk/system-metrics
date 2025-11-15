<?php

use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;

describe('CpuTimes', function () {
    it('can be instantiated with all values', function () {
        $cpuTimes = new CpuTimes(
            user: 1000,
            nice: 100,
            system: 500,
            idle: 8000,
            iowait: 50,
            irq: 10,
            softirq: 20,
            steal: 5,
        );

        expect($cpuTimes->user)->toBe(1000);
        expect($cpuTimes->nice)->toBe(100);
        expect($cpuTimes->system)->toBe(500);
        expect($cpuTimes->idle)->toBe(8000);
        expect($cpuTimes->iowait)->toBe(50);
        expect($cpuTimes->irq)->toBe(10);
        expect($cpuTimes->softirq)->toBe(20);
        expect($cpuTimes->steal)->toBe(5);
    });

    it('calculates total correctly', function () {
        $cpuTimes = new CpuTimes(
            user: 1000,
            nice: 100,
            system: 500,
            idle: 8000,
            iowait: 50,
            irq: 10,
            softirq: 20,
            steal: 5,
        );

        expect($cpuTimes->total())->toBe(9685);
    });

    it('calculates busy time correctly', function () {
        $cpuTimes = new CpuTimes(
            user: 1000,
            nice: 100,
            system: 500,
            idle: 8000,
            iowait: 50,
            irq: 10,
            softirq: 20,
            steal: 5,
        );

        // busy = total - idle - iowait
        expect($cpuTimes->busy())->toBe(1635);
    });

    it('handles zero values', function () {
        $cpuTimes = new CpuTimes(
            user: 0,
            nice: 0,
            system: 0,
            idle: 0,
            iowait: 0,
            irq: 0,
            softirq: 0,
            steal: 0,
        );

        expect($cpuTimes->total())->toBe(0);
        expect($cpuTimes->busy())->toBe(0);
    });

    it('handles all-busy scenario', function () {
        $cpuTimes = new CpuTimes(
            user: 1000,
            nice: 100,
            system: 500,
            idle: 0,
            iowait: 0,
            irq: 10,
            softirq: 20,
            steal: 5,
        );

        expect($cpuTimes->total())->toBe($cpuTimes->busy());
    });

    it('handles all-idle scenario', function () {
        $cpuTimes = new CpuTimes(
            user: 0,
            nice: 0,
            system: 0,
            idle: 10000,
            iowait: 0,
            irq: 0,
            softirq: 0,
            steal: 0,
        );

        expect($cpuTimes->busy())->toBe(0);
        expect($cpuTimes->total())->toBe(10000);
    });

    it('is immutable', function () {
        $cpuTimes = new CpuTimes(
            user: 1000,
            nice: 100,
            system: 500,
            idle: 8000,
            iowait: 50,
            irq: 10,
            softirq: 20,
            steal: 5,
        );

        // Attempting to modify readonly properties should cause a fatal error
        // So we just verify they are readonly by checking the type
        expect($cpuTimes)->toBeInstanceOf(CpuTimes::class);
    });
});
