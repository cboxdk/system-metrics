<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Process;

use Cbox\SystemMetrics\Contracts\ProcessMetricsSource;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\UnsupportedOperatingSystemException;
use Cbox\SystemMetrics\Support\OsDetector;

/**
 * Composite source that delegates to OS-specific implementations.
 */
final class CompositeProcessMetricsSource implements ProcessMetricsSource
{
    public function __construct(
        private readonly ?ProcessMetricsSource $source = null,
    ) {}

    public function read(int $pid): Result
    {
        $source = $this->source ?? $this->createOsSpecificSource();

        if ($source === null) {
            /** @var Result<\Cbox\SystemMetrics\DTO\Metrics\Process\ProcessSnapshot> */
            return Result::failure(
                UnsupportedOperatingSystemException::forOs(PHP_OS_FAMILY)
            );
        }

        return $source->read($pid);
    }

    public function readProcessGroup(int $rootPid): Result
    {
        $source = $this->source ?? $this->createOsSpecificSource();

        if ($source === null) {
            /** @var Result<\Cbox\SystemMetrics\DTO\Metrics\Process\ProcessGroupSnapshot> */
            return Result::failure(
                UnsupportedOperatingSystemException::forOs(PHP_OS_FAMILY)
            );
        }

        return $source->readProcessGroup($rootPid);
    }

    private function createOsSpecificSource(): ?ProcessMetricsSource
    {
        if (OsDetector::isLinux()) {
            return new LinuxProcProcessMetricsSource;
        }

        if (OsDetector::isMacOs()) {
            return new MacOsPsProcessMetricsSource;
        }

        return null;
    }
}
