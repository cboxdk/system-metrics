<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Cpu;

use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuCoreTimes;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use PHPeek\SystemMetrics\DTO\Result;

/**
 * Returns zero-filled CPU metrics as a last-resort fallback.
 *
 * This source always succeeds but provides no actual metrics.
 * Useful as the final fallback when all other CPU sources fail.
 */
final class MinimalCpuMetricsSource implements CpuMetricsSource
{
    public function read(): Result
    {
        // Get CPU count via sysctl if possible, otherwise default to 1
        $coreCount = 1;
        if (PHP_OS_FAMILY === 'Darwin') {
            $output = shell_exec('sysctl -n hw.ncpu 2>/dev/null');
            if ($output !== null && $output !== false) {
                $coreCount = max(1, (int) trim($output));
            }
        } elseif (PHP_OS_FAMILY === 'Linux') {
            $output = shell_exec('nproc 2>/dev/null');
            if ($output !== null && $output !== false) {
                $coreCount = max(1, (int) trim($output));
            }
        }

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
}
