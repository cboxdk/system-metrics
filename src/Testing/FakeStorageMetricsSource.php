<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Testing;

use Cbox\SystemMetrics\Contracts\StorageMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Storage\FileSystemType;
use Cbox\SystemMetrics\DTO\Metrics\Storage\MountPoint;
use Cbox\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Fake StorageMetricsSource for testing.
 */
final class FakeStorageMetricsSource implements StorageMetricsSource
{
    private ?StorageSnapshot $snapshot = null;

    private ?SystemMetricsException $exception = null;

    /**
     * @return Result<StorageSnapshot>
     */
    public function read(): Result
    {
        if ($this->exception !== null) {
            /** @var Result<StorageSnapshot> */
            return Result::failure($this->exception);
        }

        return Result::success($this->snapshot ?? self::default());
    }

    public function set(StorageSnapshot $snapshot): self
    {
        $this->snapshot = $snapshot;

        return $this;
    }

    public function failWith(SystemMetricsException $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    public static function default(): StorageSnapshot
    {
        return new StorageSnapshot(
            mountPoints: [
                new MountPoint(
                    device: '/dev/sda1',
                    mountPoint: '/',
                    fsType: FileSystemType::EXT4,
                    totalBytes: 107_374_182_400,     // 100 GB
                    usedBytes: 53_687_091_200,       // 50 GB
                    availableBytes: 53_687_091_200,  // 50 GB
                    totalInodes: 6_553_600,
                    usedInodes: 500_000,
                    freeInodes: 6_053_600,
                ),
            ],
            diskIO: [],
        );
    }
}
