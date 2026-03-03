<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Testing;

use Cbox\SystemMetrics\Contracts\MemoryMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Fake MemoryMetricsSource for testing.
 */
final class FakeMemoryMetricsSource implements MemoryMetricsSource
{
    private ?MemorySnapshot $snapshot = null;

    private ?SystemMetricsException $exception = null;

    /**
     * @return Result<MemorySnapshot>
     */
    public function read(): Result
    {
        if ($this->exception !== null) {
            /** @var Result<MemorySnapshot> */
            return Result::failure($this->exception);
        }

        return Result::success($this->snapshot ?? self::default());
    }

    public function set(MemorySnapshot $snapshot): self
    {
        $this->snapshot = $snapshot;

        return $this;
    }

    public function failWith(SystemMetricsException $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    public static function default(): MemorySnapshot
    {
        return new MemorySnapshot(
            totalBytes: 8_589_934_592,       // 8 GB
            freeBytes: 2_147_483_648,        // 2 GB
            availableBytes: 4_294_967_296,   // 4 GB
            usedBytes: 4_294_967_296,        // 4 GB
            buffersBytes: 536_870_912,       // 512 MB
            cachedBytes: 1_610_612_736,      // 1.5 GB
            swapTotalBytes: 2_147_483_648,   // 2 GB
            swapFreeBytes: 1_610_612_736,    // 1.5 GB
            swapUsedBytes: 536_870_912,      // 512 MB
        );
    }
}
