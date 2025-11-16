<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Metrics\Storage;

/**
 * Represents a filesystem mount point with usage statistics.
 */
final readonly class MountPoint
{
    public function __construct(
        public string $device,
        public string $mountPoint,
        public FileSystemType $fsType,
        public int $totalBytes,
        public int $usedBytes,
        public int $availableBytes,
        public int $totalInodes,
        public int $usedInodes,
        public int $freeInodes,
    ) {}

    /**
     * Calculate used percentage.
     */
    public function usedPercentage(): float
    {
        if ($this->totalBytes === 0) {
            return 0.0;
        }

        return ($this->usedBytes / $this->totalBytes) * 100;
    }

    /**
     * Calculate available percentage.
     */
    public function availablePercentage(): float
    {
        if ($this->totalBytes === 0) {
            return 0.0;
        }

        return ($this->availableBytes / $this->totalBytes) * 100;
    }

    /**
     * Calculate inodes used percentage.
     */
    public function inodesUsedPercentage(): float
    {
        if ($this->totalInodes === 0) {
            return 0.0;
        }

        return ($this->usedInodes / $this->totalInodes) * 100;
    }
}
