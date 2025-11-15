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
 * Reads CPU metrics from macOS sysctl.
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
            // If kern.cp_time is not available, return a minimal result
            // This is expected on modern macOS systems
            return $this->createMinimalResult();
        }

        // Try to get per-core CPU times
        $cpTimesResult = $this->processRunner->execute('sysctl -n kern.cp_times');
        if ($cpTimesResult->isFailure()) {
            // Fallback to minimal result if per-core data unavailable
            return $this->createMinimalResult();
        }

        return $this->parser->parseSnapshot(
            cpTime: $cpTimeResult->getValue(),
            cpTimes: $cpTimesResult->getValue()
        );
    }

    /**
     * Create a failure result for systems where sysctl data is unavailable.
     *
     * @return Result<CpuSnapshot>
     */
    private function createMinimalResult(): Result
    {
        /** @var Result<CpuSnapshot> */
        return Result::failure(
            new \PHPeek\SystemMetrics\Exceptions\SystemMetricsException(
                'kern.cp_time and kern.cp_times are not available on this system. '
                . 'These sysctls are deprecated and no longer exposed on modern macOS/Apple Silicon.'
            )
        );
    }
}
