<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Testing;

use Cbox\SystemMetrics\Contracts\SystemLimitsSource;
use Cbox\SystemMetrics\DTO\Metrics\LimitSource;
use Cbox\SystemMetrics\DTO\Metrics\SystemLimits;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Fake SystemLimitsSource for testing.
 */
final class FakeSystemLimitsSource implements SystemLimitsSource
{
    private ?SystemLimits $snapshot = null;

    private ?SystemMetricsException $exception = null;

    /**
     * @return Result<SystemLimits>
     */
    public function read(): Result
    {
        if ($this->exception !== null) {
            /** @var Result<SystemLimits> */
            return Result::failure($this->exception);
        }

        return Result::success($this->snapshot ?? self::default());
    }

    public function set(SystemLimits $snapshot): self
    {
        $this->snapshot = $snapshot;

        return $this;
    }

    public function failWith(SystemMetricsException $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    public static function default(): SystemLimits
    {
        return new SystemLimits(
            source: LimitSource::HOST,
            cpuCores: 4,
            memoryBytes: 8_589_934_592,             // 8 GB
            currentCpuCores: 4,
            currentMemoryBytes: 4_294_967_296.0,    // 4 GB used
            swapBytes: 2_147_483_648,               // 2 GB
            currentSwapBytes: 536_870_912.0,        // 512 MB used
        );
    }
}
