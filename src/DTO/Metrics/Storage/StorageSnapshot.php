<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Metrics\Storage;

/**
 * Complete storage metrics snapshot.
 */
final readonly class StorageSnapshot
{
    /**
     * @param  MountPoint[]  $mountPoints
     * @param  DiskIOStats[]  $diskIO
     */
    public function __construct(
        public array $mountPoints,
        public array $diskIO,
    ) {}

    /**
     * Total bytes across all mount points.
     */
    public function totalBytes(): int
    {
        return array_sum(array_map(fn (MountPoint $mp) => $mp->totalBytes, $this->mountPoints));
    }

    /**
     * Total used bytes across all mount points.
     */
    public function usedBytes(): int
    {
        return array_sum(array_map(fn (MountPoint $mp) => $mp->usedBytes, $this->mountPoints));
    }

    /**
     * Total available bytes across all mount points.
     */
    public function availableBytes(): int
    {
        return array_sum(array_map(fn (MountPoint $mp) => $mp->availableBytes, $this->mountPoints));
    }

    /**
     * Overall used percentage across all mount points.
     */
    public function usedPercentage(): float
    {
        $total = $this->totalBytes();
        if ($total === 0) {
            return 0.0;
        }

        return ($this->usedBytes() / $total) * 100;
    }
}
