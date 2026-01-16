<?php

use Cbox\SystemMetrics\DTO\Metrics\Storage\DiskIOStats;
use Cbox\SystemMetrics\DTO\Metrics\Storage\FileSystemType;
use Cbox\SystemMetrics\DTO\Metrics\Storage\MountPoint;
use Cbox\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;

describe('StorageSnapshot', function () {
    it('can be instantiated with mount points and disk IO', function () {
        $mp1 = new MountPoint(
            device: '/dev/sda1',
            mountPoint: '/',
            fsType: FileSystemType::EXT4,
            totalBytes: 100 * 1024 * 1024 * 1024,
            usedBytes: 60 * 1024 * 1024 * 1024,
            availableBytes: 40 * 1024 * 1024 * 1024,
            totalInodes: 1000000,
            usedInodes: 600000,
            freeInodes: 400000,
        );

        $mp2 = new MountPoint(
            device: '/dev/sdb1',
            mountPoint: '/data',
            fsType: FileSystemType::XFS,
            totalBytes: 200 * 1024 * 1024 * 1024,
            usedBytes: 100 * 1024 * 1024 * 1024,
            availableBytes: 100 * 1024 * 1024 * 1024,
            totalInodes: 2000000,
            usedInodes: 1000000,
            freeInodes: 1000000,
        );

        $diskIO = new DiskIOStats(
            device: 'sda',
            readsCompleted: 1000,
            readBytes: 1024 * 1024,
            writesCompleted: 500,
            writeBytes: 512 * 1024,
            ioTimeMs: 5000,
            weightedIOTimeMs: 6000,
        );

        $snapshot = new StorageSnapshot(
            mountPoints: [$mp1, $mp2],
            diskIO: [$diskIO],
        );

        expect($snapshot->mountPoints)->toHaveCount(2);
        expect($snapshot->diskIO)->toHaveCount(1);
    });

    it('calculates total bytes across all mount points', function () {
        $mp1 = new MountPoint(
            device: '/dev/sda1',
            mountPoint: '/',
            fsType: FileSystemType::EXT4,
            totalBytes: 1000,
            usedBytes: 600,
            availableBytes: 400,
            totalInodes: 0,
            usedInodes: 0,
            freeInodes: 0,
        );

        $mp2 = new MountPoint(
            device: '/dev/sdb1',
            mountPoint: '/data',
            fsType: FileSystemType::XFS,
            totalBytes: 2000,
            usedBytes: 1000,
            availableBytes: 1000,
            totalInodes: 0,
            usedInodes: 0,
            freeInodes: 0,
        );

        $snapshot = new StorageSnapshot(mountPoints: [$mp1, $mp2], diskIO: []);

        expect($snapshot->totalBytes())->toBe(3000);
    });

    it('calculates used bytes across all mount points', function () {
        $mp1 = new MountPoint(
            device: '/dev/sda1',
            mountPoint: '/',
            fsType: FileSystemType::EXT4,
            totalBytes: 1000,
            usedBytes: 600,
            availableBytes: 400,
            totalInodes: 0,
            usedInodes: 0,
            freeInodes: 0,
        );

        $mp2 = new MountPoint(
            device: '/dev/sdb1',
            mountPoint: '/data',
            fsType: FileSystemType::XFS,
            totalBytes: 2000,
            usedBytes: 1000,
            availableBytes: 1000,
            totalInodes: 0,
            usedInodes: 0,
            freeInodes: 0,
        );

        $snapshot = new StorageSnapshot(mountPoints: [$mp1, $mp2], diskIO: []);

        expect($snapshot->usedBytes())->toBe(1600);
    });

    it('calculates available bytes across all mount points', function () {
        $mp1 = new MountPoint(
            device: '/dev/sda1',
            mountPoint: '/',
            fsType: FileSystemType::EXT4,
            totalBytes: 1000,
            usedBytes: 600,
            availableBytes: 400,
            totalInodes: 0,
            usedInodes: 0,
            freeInodes: 0,
        );

        $mp2 = new MountPoint(
            device: '/dev/sdb1',
            mountPoint: '/data',
            fsType: FileSystemType::XFS,
            totalBytes: 2000,
            usedBytes: 1000,
            availableBytes: 1000,
            totalInodes: 0,
            usedInodes: 0,
            freeInodes: 0,
        );

        $snapshot = new StorageSnapshot(mountPoints: [$mp1, $mp2], diskIO: []);

        expect($snapshot->availableBytes())->toBe(1400);
    });

    it('calculates used percentage correctly', function () {
        $mp = new MountPoint(
            device: '/dev/sda1',
            mountPoint: '/',
            fsType: FileSystemType::EXT4,
            totalBytes: 1000,
            usedBytes: 600,
            availableBytes: 400,
            totalInodes: 0,
            usedInodes: 0,
            freeInodes: 0,
        );

        $snapshot = new StorageSnapshot(mountPoints: [$mp], diskIO: []);

        expect($snapshot->usedPercentage())->toBe(60.0);
    });

    it('returns zero percentage when total is zero', function () {
        $snapshot = new StorageSnapshot(mountPoints: [], diskIO: []);

        expect($snapshot->usedPercentage())->toBe(0.0);
    });

    it('handles empty mount points array', function () {
        $snapshot = new StorageSnapshot(mountPoints: [], diskIO: []);

        expect($snapshot->totalBytes())->toBe(0);
        expect($snapshot->usedBytes())->toBe(0);
        expect($snapshot->availableBytes())->toBe(0);
    });
});
