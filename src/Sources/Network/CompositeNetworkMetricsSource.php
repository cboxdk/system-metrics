<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Network;

use PHPeek\SystemMetrics\Contracts\NetworkMetricsSource;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;
use PHPeek\SystemMetrics\Support\OsDetector;

/**
 * Composite network metrics source that routes to platform-specific implementations.
 */
final class CompositeNetworkMetricsSource implements NetworkMetricsSource
{
    public function __construct(
        private readonly ?NetworkMetricsSource $linuxSource = null,
        private readonly ?NetworkMetricsSource $macosSource = null,
    ) {}

    public function read(): Result
    {
        $osFamily = OsDetector::getFamily();

        return match ($osFamily) {
            'Linux' => $this->getLinuxSource()->read(),
            'Darwin' => $this->getMacosSource()->read(),
            default => Result::failure(
                new SystemMetricsException("Unsupported OS family: {$osFamily}")
            ),
        };
    }

    private function getLinuxSource(): NetworkMetricsSource
    {
        return $this->linuxSource ?? new LinuxProcNetworkMetricsSource;
    }

    private function getMacosSource(): NetworkMetricsSource
    {
        return $this->macosSource ?? new MacOsNetstatNetworkMetricsSource;
    }
}
