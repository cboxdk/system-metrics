<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Environment;

use PHPeek\SystemMetrics\Contracts\EnvironmentDetector;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\UnsupportedOperatingSystemException;
use PHPeek\SystemMetrics\Support\OsDetector;

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

        throw UnsupportedOperatingSystemException::forOs(OsDetector::getFamily());
    }
}
