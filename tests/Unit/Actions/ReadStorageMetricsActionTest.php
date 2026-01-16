<?php

declare(strict_types=1);

use Cbox\SystemMetrics\Actions\ReadStorageMetricsAction;
use Cbox\SystemMetrics\Contracts\StorageMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Storage\DiskIOStats;
use Cbox\SystemMetrics\DTO\Metrics\Storage\FileSystemType;
use Cbox\SystemMetrics\DTO\Metrics\Storage\MountPoint;
use Cbox\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\UnsupportedOperatingSystemException;

describe('ReadStorageMetricsAction', function () {
    it('uses default source when none provided', function () {
        $action = new ReadStorageMetricsAction;
        $result = $action->execute();

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('can execute with custom source', function () {
        $mockSource = new class implements StorageMetricsSource
        {
            public function read(): Result
            {
                return Result::success(
                    new StorageSnapshot(
                        mountPoints: [
                            new MountPoint(
                                device: '/dev/sda1',
                                mountPoint: '/',
                                fsType: FileSystemType::EXT4,
                                totalBytes: 100_000_000_000,
                                usedBytes: 60_000_000_000,
                                availableBytes: 40_000_000_000,
                                totalInodes: 1_000_000,
                                usedInodes: 600_000,
                                freeInodes: 400_000,
                            ),
                        ],
                        diskIO: [
                            new DiskIOStats(
                                device: 'sda',
                                readsCompleted: 1000,
                                readBytes: 1_048_576,
                                writesCompleted: 500,
                                writeBytes: 524_288,
                                ioTimeMs: 7500,
                                weightedIOTimeMs: 12000,
                            ),
                        ]
                    )
                );
            }
        };

        $action = new ReadStorageMetricsAction($mockSource);
        $result = $action->execute();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot)->toBeInstanceOf(StorageSnapshot::class);
        expect($snapshot->mountPoints)->toHaveCount(1);
        expect($snapshot->mountPoints[0]->device)->toBe('/dev/sda1');
        expect($snapshot->diskIO)->toHaveCount(1);
        expect($snapshot->diskIO[0]->device)->toBe('sda');
    });

    it('propagates source errors', function () {
        $mockSource = new class implements StorageMetricsSource
        {
            public function read(): Result
            {
                return Result::failure(
                    UnsupportedOperatingSystemException::forOs('Windows')
                );
            }
        };

        $action = new ReadStorageMetricsAction($mockSource);
        $result = $action->execute();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(UnsupportedOperatingSystemException::class);
    });

    it('handles empty mount points and disk IO', function () {
        $mockSource = new class implements StorageMetricsSource
        {
            public function read(): Result
            {
                return Result::success(
                    new StorageSnapshot(
                        mountPoints: [],
                        diskIO: []
                    )
                );
            }
        };

        $action = new ReadStorageMetricsAction($mockSource);
        $result = $action->execute();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot->mountPoints)->toBeEmpty();
        expect($snapshot->diskIO)->toBeEmpty();
    });

    it('handles multiple mount points and disks', function () {
        $mockSource = new class implements StorageMetricsSource
        {
            public function read(): Result
            {
                return Result::success(
                    new StorageSnapshot(
                        mountPoints: [
                            new MountPoint(
                                device: '/dev/sda1',
                                mountPoint: '/',
                                fsType: FileSystemType::EXT4,
                                totalBytes: 100_000_000_000,
                                usedBytes: 60_000_000_000,
                                availableBytes: 40_000_000_000,
                                totalInodes: 1_000_000,
                                usedInodes: 600_000,
                                freeInodes: 400_000,
                            ),
                            new MountPoint(
                                device: '/dev/sdb1',
                                mountPoint: '/data',
                                fsType: FileSystemType::XFS,
                                totalBytes: 200_000_000_000,
                                usedBytes: 100_000_000_000,
                                availableBytes: 100_000_000_000,
                                totalInodes: 2_000_000,
                                usedInodes: 1_000_000,
                                freeInodes: 1_000_000,
                            ),
                        ],
                        diskIO: [
                            new DiskIOStats(
                                device: 'sda',
                                readsCompleted: 1000,
                                readBytes: 1_048_576,
                                writesCompleted: 500,
                                writeBytes: 524_288,
                                ioTimeMs: 7500,
                                weightedIOTimeMs: 12000,
                            ),
                            new DiskIOStats(
                                device: 'sdb',
                                readsCompleted: 2000,
                                readBytes: 2_097_152,
                                writesCompleted: 1000,
                                writeBytes: 1_048_576,
                                ioTimeMs: 15000,
                                weightedIOTimeMs: 24000,
                            ),
                        ]
                    )
                );
            }
        };

        $action = new ReadStorageMetricsAction($mockSource);
        $result = $action->execute();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot->mountPoints)->toHaveCount(2);
        expect($snapshot->mountPoints[0]->mountPoint)->toBe('/');
        expect($snapshot->mountPoints[1]->mountPoint)->toBe('/data');
        expect($snapshot->diskIO)->toHaveCount(2);
        expect($snapshot->diskIO[0]->device)->toBe('sda');
        expect($snapshot->diskIO[1]->device)->toBe('sdb');
    });
});
