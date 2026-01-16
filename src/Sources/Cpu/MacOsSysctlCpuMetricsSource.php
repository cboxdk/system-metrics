<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Cpu;

use Cbox\SystemMetrics\Contracts\CpuMetricsSource;
use Cbox\SystemMetrics\Contracts\ProcessRunnerInterface;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Support\Parser\MacOsSysctlParser;
use Cbox\SystemMetrics\Support\ProcessRunner;

/**
 * Reads CPU metrics from macOS sysctl kern.cp_time API.
 *
 * This source works on older macOS systems that still expose kern.cp_time
 * and kern.cp_times sysctls. On modern macOS (especially Apple Silicon),
 * these sysctls are deprecated and unavailable.
 *
 * For modern macOS, use MacOsHostProcessorInfoSource instead (via FFI).
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
            /** @var Result<CpuSnapshot> */
            return Result::failure(
                new \Cbox\SystemMetrics\Exceptions\SystemMetricsException(
                    'kern.cp_time sysctl not available (use MacOsHostProcessorInfoSource for modern macOS)'
                )
            );
        }

        // Try to get per-core CPU times
        $cpTimesResult = $this->processRunner->execute('sysctl -n kern.cp_times');
        if ($cpTimesResult->isFailure()) {
            /** @var Result<CpuSnapshot> */
            return Result::failure(
                new \Cbox\SystemMetrics\Exceptions\SystemMetricsException(
                    'kern.cp_times sysctl not available (use MacOsHostProcessorInfoSource for modern macOS)'
                )
            );
        }

        return $this->parser->parseSnapshot(
            cpTime: $cpTimeResult->getValue(),
            cpTimes: $cpTimesResult->getValue()
        );
    }
}
