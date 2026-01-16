<?php

use Cbox\SystemMetrics\DTO\Metrics\Storage\FileSystemType;
use Cbox\SystemMetrics\DTO\Metrics\Storage\MountPoint;
use Cbox\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;

describe('StorageSnapshot Finder Methods', function () {
    beforeEach(function () {
        $this->mountPoints = [
            new MountPoint(
                device: '/dev/sda1',
                mountPoint: '/',
                fsType: FileSystemType::EXT4,
                totalBytes: 1000000,
                usedBytes: 400000,
                availableBytes: 600000,
                totalInodes: 10000,
                usedInodes: 4000,
                freeInodes: 6000,
            ),
            new MountPoint(
                device: '/dev/sda2',
                mountPoint: '/home',
                fsType: FileSystemType::EXT4,
                totalBytes: 2000000,
                usedBytes: 800000,
                availableBytes: 1200000,
                totalInodes: 20000,
                usedInodes: 8000,
                freeInodes: 12000,
            ),
            new MountPoint(
                device: '/dev/sdb1',
                mountPoint: '/mnt/data',
                fsType: FileSystemType::XFS,
                totalBytes: 5000000,
                usedBytes: 1000000,
                availableBytes: 4000000,
                totalInodes: 50000,
                usedInodes: 10000,
                freeInodes: 40000,
            ),
            new MountPoint(
                device: 'tmpfs',
                mountPoint: '/tmp',
                fsType: FileSystemType::TMPFS,
                totalBytes: 500000,
                usedBytes: 100000,
                availableBytes: 400000,
                totalInodes: 5000,
                usedInodes: 1000,
                freeInodes: 4000,
            ),
        ];

        $this->snapshot = new StorageSnapshot(
            mountPoints: $this->mountPoints,
            diskIO: [],
        );
    });

    describe('findMountPoint', function () {
        it('finds mount point for exact path', function () {
            $mount = $this->snapshot->findMountPoint('/home');

            expect($mount)->not->toBeNull();
            expect($mount->mountPoint)->toBe('/home');
            expect($mount->device)->toBe('/dev/sda2');
        });

        it('finds mount point for nested path', function () {
            $mount = $this->snapshot->findMountPoint('/home/user/documents');

            expect($mount)->not->toBeNull();
            expect($mount->mountPoint)->toBe('/home');
        });

        it('finds most specific mount point for deeply nested path', function () {
            $mount = $this->snapshot->findMountPoint('/mnt/data/project/files');

            expect($mount)->not->toBeNull();
            expect($mount->mountPoint)->toBe('/mnt/data');
            expect($mount->device)->toBe('/dev/sdb1');
        });

        it('returns root mount when path is on root', function () {
            $mount = $this->snapshot->findMountPoint('/var/log/test.log');

            expect($mount)->not->toBeNull();
            expect($mount->mountPoint)->toBe('/');
        });

        it('returns root mount for path under root', function () {
            $mount = $this->snapshot->findMountPoint('/var/log');

            expect($mount)->not->toBeNull();
            expect($mount->mountPoint)->toBe('/');
        });

        it('prefers longer matching prefix when multiple mounts match', function () {
            // /home/user could match both / and /home, should return /home
            $mount = $this->snapshot->findMountPoint('/home/user/file.txt');

            expect($mount)->not->toBeNull();
            expect($mount->mountPoint)->toBe('/home');
        });
    });

    describe('findDevice', function () {
        it('finds mount point by exact device name', function () {
            $mount = $this->snapshot->findDevice('/dev/sdb1');

            expect($mount)->not->toBeNull();
            expect($mount->device)->toBe('/dev/sdb1');
            expect($mount->mountPoint)->toBe('/mnt/data');
        });

        it('finds tmpfs device', function () {
            $mount = $this->snapshot->findDevice('tmpfs');

            expect($mount)->not->toBeNull();
            expect($mount->fsType)->toBe(FileSystemType::TMPFS);
        });

        it('returns null for non-existent device', function () {
            $mount = $this->snapshot->findDevice('/dev/nonexistent');

            expect($mount)->toBeNull();
        });
    });

    describe('findByFilesystemType', function () {
        it('finds all ext4 mount points', function () {
            $mounts = $this->snapshot->findByFilesystemType(FileSystemType::EXT4);

            expect($mounts)->toHaveCount(2);
            expect($mounts[0]->fsType)->toBe(FileSystemType::EXT4);
            expect($mounts[1]->fsType)->toBe(FileSystemType::EXT4);
        });

        it('finds single xfs mount point', function () {
            $mounts = $this->snapshot->findByFilesystemType(FileSystemType::XFS);

            expect($mounts)->toHaveCount(1);
            expect($mounts[0]->device)->toBe('/dev/sdb1');
        });

        it('returns empty array for filesystem type with no mounts', function () {
            $mounts = $this->snapshot->findByFilesystemType(FileSystemType::BTRFS);

            expect($mounts)->toBeEmpty();
        });

        it('returns re-indexed array', function () {
            $mounts = $this->snapshot->findByFilesystemType(FileSystemType::EXT4);

            expect(array_keys($mounts))->toBe([0, 1]);
        });
    });
});
