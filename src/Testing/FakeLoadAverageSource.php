<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Testing;

use Cbox\SystemMetrics\Contracts\LoadAverageSource;
use Cbox\SystemMetrics\DTO\Metrics\LoadAverageSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Fake LoadAverageSource for testing.
 */
final class FakeLoadAverageSource implements LoadAverageSource
{
    private ?LoadAverageSnapshot $snapshot = null;

    private ?SystemMetricsException $exception = null;

    /**
     * @return Result<LoadAverageSnapshot>
     */
    public function read(): Result
    {
        if ($this->exception !== null) {
            /** @var Result<LoadAverageSnapshot> */
            return Result::failure($this->exception);
        }

        return Result::success($this->snapshot ?? self::default());
    }

    public function set(LoadAverageSnapshot $snapshot): self
    {
        $this->snapshot = $snapshot;

        return $this;
    }

    public function failWith(SystemMetricsException $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    public static function default(): LoadAverageSnapshot
    {
        return new LoadAverageSnapshot(
            oneMinute: 0.5,
            fiveMinutes: 0.3,
            fifteenMinutes: 0.2,
        );
    }
}
