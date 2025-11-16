<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Storage;

use PHPeek\SystemMetrics\Contracts\StorageMetricsSource;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;
use PHPeek\SystemMetrics\Support\OsDetector;

/**
 * Composite storage metrics source that routes to platform-specific implementations.
 */
final class CompositeStorageMetricsSource implements StorageMetricsSource
{
    public function __construct(
        private readonly ?StorageMetricsSource $linuxSource = null,
        private readonly ?StorageMetricsSource $macosSource = null,
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

    private function getLinuxSource(): StorageMetricsSource
    {
        return $this->linuxSource ?? new LinuxProcStorageMetricsSource;
    }

    private function getMacosSource(): StorageMetricsSource
    {
        return $this->macosSource ?? new MacOsDfStorageMetricsSource;
    }
}
