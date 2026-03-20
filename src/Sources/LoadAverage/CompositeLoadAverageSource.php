<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\LoadAverage;

use Cbox\SystemMetrics\Contracts\LoadAverageSource;
use Cbox\SystemMetrics\DTO\Metrics\LoadAverageSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\UnsupportedOperatingSystemException;
use Cbox\SystemMetrics\Support\OsDetector;

/**
 * Composite load average source with automatic OS detection.
 *
 * Delegates to the appropriate platform-specific implementation
 * based on the detected operating system.
 */
final class CompositeLoadAverageSource implements LoadAverageSource
{
    private ?LoadAverageSource $source;

    public function __construct(?LoadAverageSource $source = null)
    {
        $this->source = $source ?? $this->createOsSpecificSource();
    }

    /**
     * Read load average using the OS-specific implementation.
     *
     * @return Result<LoadAverageSnapshot>
     */
    public function read(): Result
    {
        if ($this->source === null) {
            /** @var Result<LoadAverageSnapshot> */
            return Result::failure(
                UnsupportedOperatingSystemException::forOs(PHP_OS_FAMILY)
            );
        }

        return $this->source->read();
    }

    /**
     * Create OS-specific load average source.
     */
    private function createOsSpecificSource(): ?LoadAverageSource
    {
        if (OsDetector::isLinux()) {
            return new LinuxProcLoadAverageSource;
        }

        if (OsDetector::isMacOs()) {
            return new FallbackLoadAverageSource([
                new MacOsFFILoadAverageSource,       // 1. FFI (fast, no subprocess)
                new MacOsSysctlLoadAverageSource,    // 2. sysctl (fallback for FPM)
            ]);
        }

        return null;
    }
}
