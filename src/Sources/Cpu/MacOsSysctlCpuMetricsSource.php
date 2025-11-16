<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Cpu;

use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;
use PHPeek\SystemMetrics\Contracts\ProcessRunnerInterface;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuCoreTimes;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Support\Parser\MacOsSysctlParser;
use PHPeek\SystemMetrics\Support\ProcessRunner;

/**
 * Reads CPU metrics from macOS sysctl or top command.
 *
 * On older macOS systems, uses kern.cp_time and kern.cp_times sysctls.
 * On modern macOS/Apple Silicon, falls back to parsing `top` command output.
 */
final class MacOsSysctlCpuMetricsSource implements CpuMetricsSource
{
    public function __construct(
        private readonly ProcessRunnerInterface $processRunner = new ProcessRunner,
        private readonly MacOsSysctlParser $parser = new MacOsSysctlParser,
    ) {}

    public function read(): Result
    {
        // Note: kern.cp_time and kern.cp_times are not available on all macOS versions
        // (especially newer macOS and Apple Silicon Macs).
        // These sysctls are deprecated and no longer exposed on modern systems.

        // Try to get system-wide CPU time
        $cpTimeResult = $this->processRunner->execute('sysctl -n kern.cp_time');
        if ($cpTimeResult->isFailure()) {
            // If kern.cp_time is not available, fall back to top command
            // This is expected on modern macOS systems
            return $this->readFromTop();
        }

        // Try to get per-core CPU times
        $cpTimesResult = $this->processRunner->execute('sysctl -n kern.cp_times');
        if ($cpTimesResult->isFailure()) {
            // Fallback to top if per-core data unavailable
            return $this->readFromTop();
        }

        return $this->parser->parseSnapshot(
            cpTime: $cpTimeResult->getValue(),
            cpTimes: $cpTimesResult->getValue()
        );
    }

    /**
     * Read CPU metrics using top command (fallback for modern macOS).
     *
     * @return Result<CpuSnapshot>
     */
    private function readFromTop(): Result
    {
        // Use top to get CPU usage (can't use pipes due to escapeshellcmd in ProcessRunner)
        // Output format: "CPU usage: 20.22% user, 15.63% sys, 64.14% idle"
        $topResult = $this->processRunner->execute('top -l 1 -n 0');

        if ($topResult->isFailure()) {
            /** @var Result<CpuSnapshot> */
            return Result::failure(
                new \PHPeek\SystemMetrics\Exceptions\SystemMetricsException(
                    'Unable to read CPU metrics: kern.cp_time unavailable and top command failed'
                )
            );
        }

        $output = $topResult->getValue();

        // Find the CPU usage line in the output
        // Parse: "CPU usage: 20.22% user, 15.63% sys, 64.14% idle"
        if (! preg_match('/CPU usage: (\d+\.\d+)% user, (\d+\.\d+)% sys, (\d+\.\d+)% idle/', $output, $matches)) {
            /** @var Result<CpuSnapshot> */
            return Result::failure(
                new \PHPeek\SystemMetrics\Exceptions\ParseException(
                    'Unable to parse top CPU output (looking for "CPU usage:" line)'
                )
            );
        }

        $userPercent = (float) $matches[1];
        $sysPercent = (float) $matches[2];
        $idlePercent = (float) $matches[3];

        // Get CPU count
        $ncpuResult = $this->processRunner->execute('sysctl -n hw.ncpu');
        $coreCount = $ncpuResult->isSuccess() ? (int) trim($ncpuResult->getValue()) : 1;

        // Note: top gives percentages, not absolute ticks
        // We'll use a pseudo-tick value (percentage * 100) for consistency
        // This means each 1% = 100 ticks
        $totalTicks = 10000; // 100% = 10000 ticks

        $userTicks = (int) ($userPercent * 100);
        $sysTicks = (int) ($sysPercent * 100);
        $idleTicks = (int) ($idlePercent * 100);

        $cpuTimes = new CpuTimes(
            user: $userTicks,
            nice: 0,
            system: $sysTicks,
            idle: $idleTicks,
            iowait: 0,
            irq: 0,
            softirq: 0,
            steal: 0
        );

        // Create per-core times (all cores assumed to have equal load distribution)
        $perCore = [];
        for ($i = 0; $i < $coreCount; $i++) {
            $perCore[] = new CpuCoreTimes(
                coreIndex: $i,
                times: $cpuTimes // Note: top doesn't provide per-core data
            );
        }

        return Result::success(new CpuSnapshot(
            total: $cpuTimes,
            perCore: $perCore
        ));
    }
}
