<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Testing;

use Cbox\SystemMetrics\Contracts\UptimeSource;
use Cbox\SystemMetrics\DTO\Metrics\UptimeSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;
use DateTimeImmutable;

/**
 * Fake UptimeSource for testing.
 */
final class FakeUptimeSource implements UptimeSource
{
    private ?UptimeSnapshot $snapshot = null;

    private ?SystemMetricsException $exception = null;

    /**
     * @return Result<UptimeSnapshot>
     */
    public function read(): Result
    {
        if ($this->exception !== null) {
            /** @var Result<UptimeSnapshot> */
            return Result::failure($this->exception);
        }

        return Result::success($this->snapshot ?? self::default());
    }

    public function set(UptimeSnapshot $snapshot): self
    {
        $this->snapshot = $snapshot;

        return $this;
    }

    public function failWith(SystemMetricsException $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    public static function default(): UptimeSnapshot
    {
        $now = new DateTimeImmutable;

        return new UptimeSnapshot(
            totalSeconds: 86400,  // 1 day
            bootTime: $now->modify('-1 day'),
            timestamp: $now,
        );
    }
}
