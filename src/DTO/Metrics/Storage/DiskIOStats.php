<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Metrics\Storage;

/**
 * Disk I/O statistics for a device.
 */
final readonly class DiskIOStats
{
    public function __construct(
        public string $device,
        public int $readsCompleted,
        public int $readBytes,
        public int $writesCompleted,
        public int $writeBytes,
        public int $ioTimeMs,
        public int $weightedIOTimeMs,
    ) {}

    /**
     * Total number of I/O operations (reads + writes).
     */
    public function totalOperations(): int
    {
        return $this->readsCompleted + $this->writesCompleted;
    }

    /**
     * Total bytes transferred (read + write).
     */
    public function totalBytes(): int
    {
        return $this->readBytes + $this->writeBytes;
    }
}
