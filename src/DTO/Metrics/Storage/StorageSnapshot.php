<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Metrics\Storage;

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

    /**
     * Find mount point containing the given path.
     * Returns the most specific mount (longest matching prefix).
     */
    public function findMountPoint(string $path): ?MountPoint
    {
        $matches = [];

        foreach ($this->mountPoints as $mount) {
            if (str_starts_with($path, $mount->mountPoint)) {
                $matches[] = $mount;
            }
        }

        if (empty($matches)) {
            return null;
        }

        // Sort by mount point length descending (most specific first)
        usort($matches, fn (MountPoint $a, MountPoint $b) => strlen($b->mountPoint) - strlen($a->mountPoint));

        return $matches[0];
    }

    /**
     * Find mount point by device name.
     */
    public function findDevice(string $device): ?MountPoint
    {
        foreach ($this->mountPoints as $mount) {
            if ($mount->device === $device) {
                return $mount;
            }
        }

        return null;
    }

    /**
     * Find all mount points of a given filesystem type.
     *
     * @return MountPoint[]
     */
    public function findByFilesystemType(FileSystemType $type): array
    {
        return array_values(array_filter(
            $this->mountPoints,
            fn (MountPoint $mp) => $mp->fsType === $type
        ));
    }
}
