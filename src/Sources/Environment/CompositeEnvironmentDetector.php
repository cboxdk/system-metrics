<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Environment;

use Cbox\SystemMetrics\Contracts\EnvironmentDetector;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\UnsupportedOperatingSystemException;
use Cbox\SystemMetrics\Support\OsDetector;

/**
 * Routes environment detection to the appropriate OS-specific detector.
 */
final class CompositeEnvironmentDetector implements EnvironmentDetector
{
    private readonly EnvironmentDetector $detector;

    public function __construct(?EnvironmentDetector $detector = null)
    {
        $this->detector = $detector ?? $this->createDetector();
    }

    public function detect(): Result
    {
        return $this->detector->detect();
    }

    private function createDetector(): EnvironmentDetector
    {
        if (OsDetector::isLinux()) {
            return new LinuxEnvironmentDetector;
        }

        if (OsDetector::isMacOs()) {
            return new MacOsEnvironmentDetector;
        }

        if (OsDetector::isWindows()) {
            return new WindowsEnvironmentDetector;
        }

        throw UnsupportedOperatingSystemException::forOs(OsDetector::getFamily());
    }
}
