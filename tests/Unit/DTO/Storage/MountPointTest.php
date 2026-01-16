<?php

use Cbox\SystemMetrics\DTO\Metrics\Storage\FileSystemType;
use Cbox\SystemMetrics\DTO\Metrics\Storage\MountPoint;

describe('MountPoint', function () {
    it('can be instantiated with all values', function () {
        $mp = new MountPoint(
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

        expect($mp->device)->toBe('/dev/sda1');
        expect($mp->mountPoint)->toBe('/');
        expect($mp->fsType)->toBe(FileSystemType::EXT4);
        expect($mp->totalBytes)->toBe(100 * 1024 * 1024 * 1024);
        expect($mp->usedBytes)->toBe(60 * 1024 * 1024 * 1024);
        expect($mp->availableBytes)->toBe(40 * 1024 * 1024 * 1024);
        expect($mp->totalInodes)->toBe(1000000);
        expect($mp->usedInodes)->toBe(600000);
        expect($mp->freeInodes)->toBe(400000);
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

        expect($mp->usedPercentage())->toBe(60.0);
    });

    it('calculates available percentage correctly', function () {
        $mp = new MountPoint(
            device: '/dev/sda1',
            mountPoint: '/',
            fsType: FileSystemType::EXT4,
            totalBytes: 1000,
            usedBytes: 700,
            availableBytes: 300,
            totalInodes: 0,
            usedInodes: 0,
            freeInodes: 0,
        );

        expect($mp->availablePercentage())->toBe(30.0);
    });

    it('calculates inodes used percentage correctly', function () {
        $mp = new MountPoint(
            device: '/dev/sda1',
            mountPoint: '/',
            fsType: FileSystemType::EXT4,
            totalBytes: 1000,
            usedBytes: 500,
            availableBytes: 500,
            totalInodes: 1000,
            usedInodes: 250,
            freeInodes: 750,
        );

        expect($mp->inodesUsedPercentage())->toBe(25.0);
    });

    it('returns zero percentage when total bytes is zero', function () {
        $mp = new MountPoint(
            device: '/dev/sda1',
            mountPoint: '/',
            fsType: FileSystemType::EXT4,
            totalBytes: 0,
            usedBytes: 0,
            availableBytes: 0,
            totalInodes: 0,
            usedInodes: 0,
            freeInodes: 0,
        );

        expect($mp->usedPercentage())->toBe(0.0);
        expect($mp->availablePercentage())->toBe(0.0);
    });

    it('returns zero percentage when total inodes is zero', function () {
        $mp = new MountPoint(
            device: '/dev/sda1',
            mountPoint: '/',
            fsType: FileSystemType::EXT4,
            totalBytes: 1000,
            usedBytes: 500,
            availableBytes: 500,
            totalInodes: 0,
            usedInodes: 0,
            freeInodes: 0,
        );

        expect($mp->inodesUsedPercentage())->toBe(0.0);
    });
});
