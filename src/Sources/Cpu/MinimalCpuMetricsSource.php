<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Cpu;

use Cbox\SystemMetrics\Contracts\CpuMetricsSource;
use Cbox\SystemMetrics\Contracts\ProcessRunnerInterface;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuCoreTimes;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Support\ProcessRunner;

/**
 * Returns zero-filled CPU metrics as a last-resort fallback.
 *
 * This source always succeeds but provides no actual metrics.
 * Useful as the final fallback when all other CPU sources fail.
 *
 * Uses ProcessRunner for consistent command execution through the
 * security whitelist, even for simple fallback operations.
 */
final class MinimalCpuMetricsSource implements CpuMetricsSource
{
    public function __construct(
        private readonly ProcessRunnerInterface $processRunner = new ProcessRunner,
    ) {}

    public function read(): Result
    {
        $coreCount = $this->detectCoreCount();

        // Create zero-filled CPU times
        $zeroTimes = new CpuTimes(
            user: 0,
            nice: 0,
            system: 0,
            idle: 0,
            iowait: 0,
            irq: 0,
            softirq: 0,
            steal: 0
        );

        // Create per-core times (all zeros)
        $perCore = [];
        for ($i = 0; $i < $coreCount; $i++) {
            $perCore[] = new CpuCoreTimes(
                coreIndex: $i,
                times: $zeroTimes
            );
        }

        return Result::success(new CpuSnapshot(
            total: $zeroTimes,
            perCore: $perCore
        ));
    }

    /**
     * Detect CPU core count using OS-specific commands.
     *
     * Falls back to 1 if detection fails.
     */
    private function detectCoreCount(): int
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            $result = $this->processRunner->execute('sysctl -n hw.ncpu');
            if ($result->isSuccess()) {
                return max(1, (int) trim($result->getValue()));
            }
        } elseif (PHP_OS_FAMILY === 'Linux') {
            $result = $this->processRunner->execute('nproc');
            if ($result->isSuccess()) {
                return max(1, (int) trim($result->getValue()));
            }
        }

        return 1;
    }
}
