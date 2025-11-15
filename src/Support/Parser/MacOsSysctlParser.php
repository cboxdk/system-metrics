<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support\Parser;

use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuCoreTimes;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\ParseException;

/**
 * Parses macOS sysctl output for CPU metrics.
 */
final class MacOsSysctlParser
{
    /**
     * Parse kern.cp_time output (system-wide CPU).
     * Format: "{ user, nice, sys, idle }" or space-separated values
     *
     * @return Result<CpuTimes>
     */
    public function parseCpTime(string $content): Result
    {
        // Remove braces and split by comma or whitespace
        $content = trim($content, ' {}');
        $parts = preg_split('/[,\s]+/', $content);

        if ($parts === false || count($parts) < 4) {
            return Result::failure(
                ParseException::forCommand('sysctl kern.cp_time', 'Invalid format')
            );
        }

        // macOS provides: user, nice, sys, idle
        // We map to Linux-style CpuTimes with best effort
        return Result::success(new CpuTimes(
            user: (int) $parts[0],
            nice: (int) $parts[1],
            system: (int) $parts[2],
            idle: (int) $parts[3],
            iowait: 0,  // Not available on macOS
            irq: 0,     // Not available on macOS
            softirq: 0, // Not available on macOS
            steal: 0,   // Not available on macOS
        ));
    }

    /**
     * Parse kern.cp_times output (per-core CPU).
     * Format: "{ core0_user, core0_nice, core0_sys, core0_idle, core1_user, ... }"
     *
     * @return Result<list<CpuCoreTimes>>
     */
    public function parseCpTimes(string $content): Result
    {
        // Remove braces and split by comma or whitespace
        $content = trim($content, ' {}');
        $parts = preg_split('/[,\s]+/', $content);

        if ($parts === false) {
            return Result::failure(
                ParseException::forCommand('sysctl kern.cp_times', 'Invalid format')
            );
        }

        $perCore = [];
        $coreIndex = 0;
        $valuesPerCore = 4; // user, nice, sys, idle

        for ($i = 0; $i < count($parts); $i += $valuesPerCore) {
            if ($i + $valuesPerCore > count($parts)) {
                break;
            }

            $perCore[] = new CpuCoreTimes(
                coreIndex: $coreIndex,
                times: new CpuTimes(
                    user: (int) $parts[$i],
                    nice: (int) $parts[$i + 1],
                    system: (int) $parts[$i + 2],
                    idle: (int) $parts[$i + 3],
                    iowait: 0,
                    irq: 0,
                    softirq: 0,
                    steal: 0,
                )
            );

            $coreIndex++;
        }

        if (empty($perCore)) {
            return Result::failure(
                ParseException::forCommand('sysctl kern.cp_times', 'No core data found')
            );
        }

        return Result::success($perCore);
    }

    /**
     * Parse both kern.cp_time and kern.cp_times into CpuSnapshot.
     *
     * @return Result<CpuSnapshot>
     */
    public function parseSnapshot(string $cpTime, string $cpTimes): Result
    {
        $totalResult = $this->parseCpTime($cpTime);
        if ($totalResult->isFailure()) {
            return Result::failure($totalResult->getError());
        }

        $perCoreResult = $this->parseCpTimes($cpTimes);
        if ($perCoreResult->isFailure()) {
            return Result::failure($perCoreResult->getError());
        }

        return Result::success(new CpuSnapshot(
            total: $totalResult->getValue(),
            perCore: $perCoreResult->getValue(),
        ));
    }
}
