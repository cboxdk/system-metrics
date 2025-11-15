<?php

use PHPeek\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;

describe('MemorySnapshot', function () {
    it('can be instantiated with all values', function () {
        $memory = new MemorySnapshot(
            totalBytes: 16 * 1024 * 1024 * 1024,
            freeBytes: 8 * 1024 * 1024 * 1024,
            availableBytes: 10 * 1024 * 1024 * 1024,
            usedBytes: 6 * 1024 * 1024 * 1024,
            buffersBytes: 512 * 1024 * 1024,
            cachedBytes: 1024 * 1024 * 1024,
            swapTotalBytes: 4 * 1024 * 1024 * 1024,
            swapFreeBytes: 3 * 1024 * 1024 * 1024,
            swapUsedBytes: 1024 * 1024 * 1024,
        );

        expect($memory->totalBytes)->toBe(16 * 1024 * 1024 * 1024);
        expect($memory->freeBytes)->toBe(8 * 1024 * 1024 * 1024);
        expect($memory->availableBytes)->toBe(10 * 1024 * 1024 * 1024);
        expect($memory->usedBytes)->toBe(6 * 1024 * 1024 * 1024);
        expect($memory->buffersBytes)->toBe(512 * 1024 * 1024);
        expect($memory->cachedBytes)->toBe(1024 * 1024 * 1024);
        expect($memory->swapTotalBytes)->toBe(4 * 1024 * 1024 * 1024);
        expect($memory->swapFreeBytes)->toBe(3 * 1024 * 1024 * 1024);
        expect($memory->swapUsedBytes)->toBe(1024 * 1024 * 1024);
    });

    it('calculates used percentage correctly', function () {
        $memory = new MemorySnapshot(
            totalBytes: 1000,
            freeBytes: 400,
            availableBytes: 500,
            usedBytes: 600,
            buffersBytes: 0,
            cachedBytes: 0,
            swapTotalBytes: 0,
            swapFreeBytes: 0,
            swapUsedBytes: 0,
        );

        expect($memory->usedPercentage())->toBe(60.0);
    });

    it('calculates available percentage correctly', function () {
        $memory = new MemorySnapshot(
            totalBytes: 1000,
            freeBytes: 400,
            availableBytes: 500,
            usedBytes: 500,
            buffersBytes: 0,
            cachedBytes: 0,
            swapTotalBytes: 0,
            swapFreeBytes: 0,
            swapUsedBytes: 0,
        );

        expect($memory->availablePercentage())->toBe(50.0);
    });

    it('calculates swap used percentage correctly', function () {
        $memory = new MemorySnapshot(
            totalBytes: 1000,
            freeBytes: 500,
            availableBytes: 500,
            usedBytes: 500,
            buffersBytes: 0,
            cachedBytes: 0,
            swapTotalBytes: 1000,
            swapFreeBytes: 750,
            swapUsedBytes: 250,
        );

        expect($memory->swapUsedPercentage())->toBe(25.0);
    });

    it('returns zero percentage when total is zero', function () {
        $memory = new MemorySnapshot(
            totalBytes: 0,
            freeBytes: 0,
            availableBytes: 0,
            usedBytes: 0,
            buffersBytes: 0,
            cachedBytes: 0,
            swapTotalBytes: 0,
            swapFreeBytes: 0,
            swapUsedBytes: 0,
        );

        expect($memory->usedPercentage())->toBe(0.0);
        expect($memory->availablePercentage())->toBe(0.0);
        expect($memory->swapUsedPercentage())->toBe(0.0);
    });

    it('handles 100 percent usage', function () {
        $memory = new MemorySnapshot(
            totalBytes: 1000,
            freeBytes: 0,
            availableBytes: 0,
            usedBytes: 1000,
            buffersBytes: 0,
            cachedBytes: 0,
            swapTotalBytes: 500,
            swapFreeBytes: 0,
            swapUsedBytes: 500,
        );

        expect($memory->usedPercentage())->toBe(100.0);
        expect($memory->availablePercentage())->toBe(0.0);
        expect($memory->swapUsedPercentage())->toBe(100.0);
    });

    it('handles fractional percentages', function () {
        $memory = new MemorySnapshot(
            totalBytes: 3,
            freeBytes: 1,
            availableBytes: 1,
            usedBytes: 2,
            buffersBytes: 0,
            cachedBytes: 0,
            swapTotalBytes: 3,
            swapFreeBytes: 2,
            swapUsedBytes: 1,
        );

        expect($memory->usedPercentage())->toBeFloat();
        expect($memory->usedPercentage())->toBeGreaterThan(66.0);
        expect($memory->usedPercentage())->toBeLessThan(67.0);
    });

    it('handles no swap scenario', function () {
        $memory = new MemorySnapshot(
            totalBytes: 1000,
            freeBytes: 500,
            availableBytes: 500,
            usedBytes: 500,
            buffersBytes: 0,
            cachedBytes: 0,
            swapTotalBytes: 0,
            swapFreeBytes: 0,
            swapUsedBytes: 0,
        );

        expect($memory->swapUsedPercentage())->toBe(0.0);
    });
});
