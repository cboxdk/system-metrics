<?php

use Cbox\SystemMetrics\DTO\Metrics\Storage\DiskIOStats;

describe('DiskIOStats', function () {
    it('can be instantiated with all values', function () {
        $stats = new DiskIOStats(
            device: 'sda',
            readsCompleted: 1000,
            readBytes: 1024 * 1024,
            writesCompleted: 500,
            writeBytes: 512 * 1024,
            ioTimeMs: 5000,
            weightedIOTimeMs: 6000,
        );

        expect($stats->device)->toBe('sda');
        expect($stats->readsCompleted)->toBe(1000);
        expect($stats->readBytes)->toBe(1024 * 1024);
        expect($stats->writesCompleted)->toBe(500);
        expect($stats->writeBytes)->toBe(512 * 1024);
        expect($stats->ioTimeMs)->toBe(5000);
        expect($stats->weightedIOTimeMs)->toBe(6000);
    });

    it('calculates total operations correctly', function () {
        $stats = new DiskIOStats(
            device: 'sda',
            readsCompleted: 1000,
            readBytes: 1024,
            writesCompleted: 500,
            writeBytes: 512,
            ioTimeMs: 0,
            weightedIOTimeMs: 0,
        );

        expect($stats->totalOperations())->toBe(1500);
    });

    it('calculates total bytes correctly', function () {
        $stats = new DiskIOStats(
            device: 'sda',
            readsCompleted: 0,
            readBytes: 1024 * 1024,
            writesCompleted: 0,
            writeBytes: 512 * 1024,
            ioTimeMs: 0,
            weightedIOTimeMs: 0,
        );

        expect($stats->totalBytes())->toBe(1024 * 1024 + 512 * 1024);
    });

    it('handles zero values', function () {
        $stats = new DiskIOStats(
            device: 'sda',
            readsCompleted: 0,
            readBytes: 0,
            writesCompleted: 0,
            writeBytes: 0,
            ioTimeMs: 0,
            weightedIOTimeMs: 0,
        );

        expect($stats->totalOperations())->toBe(0);
        expect($stats->totalBytes())->toBe(0);
    });
});
