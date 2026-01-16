<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Metrics\Memory;

/**
 * Complete snapshot of memory metrics in bytes.
 */
final readonly class MemorySnapshot
{
    public function __construct(
        public int $totalBytes,
        public int $freeBytes,
        public int $availableBytes,
        public int $usedBytes,
        public int $buffersBytes,
        public int $cachedBytes,
        public int $swapTotalBytes,
        public int $swapFreeBytes,
        public int $swapUsedBytes,
    ) {}

    /**
     * Get used memory percentage.
     */
    public function usedPercentage(): float
    {
        if ($this->totalBytes === 0) {
            return 0.0;
        }

        return ($this->usedBytes / $this->totalBytes) * 100;
    }

    /**
     * Get available memory percentage.
     */
    public function availablePercentage(): float
    {
        if ($this->totalBytes === 0) {
            return 0.0;
        }

        return ($this->availableBytes / $this->totalBytes) * 100;
    }

    /**
     * Get swap used percentage.
     */
    public function swapUsedPercentage(): float
    {
        if ($this->swapTotalBytes === 0) {
            return 0.0;
        }

        return ($this->swapUsedBytes / $this->swapTotalBytes) * 100;
    }
}
