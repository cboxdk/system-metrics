<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Testing;

use Cbox\SystemMetrics\Contracts\CpuMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuCoreTimes;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Fake CpuMetricsSource for testing.
 */
final class FakeCpuMetricsSource implements CpuMetricsSource
{
    private ?CpuSnapshot $snapshot = null;

    private ?SystemMetricsException $exception = null;

    /**
     * @return Result<CpuSnapshot>
     */
    public function read(): Result
    {
        if ($this->exception !== null) {
            /** @var Result<CpuSnapshot> */
            return Result::failure($this->exception);
        }

        return Result::success($this->snapshot ?? self::default());
    }

    public function set(CpuSnapshot $snapshot): self
    {
        $this->snapshot = $snapshot;

        return $this;
    }

    public function failWith(SystemMetricsException $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    public static function default(int $cores = 4): CpuSnapshot
    {
        $perCore = [];
        for ($i = 0; $i < $cores; $i++) {
            $perCore[] = new CpuCoreTimes(
                coreIndex: $i,
                times: new CpuTimes(
                    user: 1000 + ($i * 100),
                    nice: 0,
                    system: 500 + ($i * 50),
                    idle: 8000,
                    iowait: 10,
                    irq: 0,
                    softirq: 5,
                    steal: 0,
                ),
            );
        }

        return new CpuSnapshot(
            total: new CpuTimes(
                user: 4000,
                nice: 0,
                system: 2000,
                idle: 32000,
                iowait: 40,
                irq: 0,
                softirq: 20,
                steal: 0,
            ),
            perCore: $perCore,
        );
    }
}
