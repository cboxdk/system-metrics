<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Storage;

use Cbox\SystemMetrics\Contracts\StorageMetricsSource;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;
use Cbox\SystemMetrics\Support\OsDetector;

/**
 * Composite storage metrics source that routes to platform-specific implementations.
 */
final class CompositeStorageMetricsSource implements StorageMetricsSource
{
    public function __construct(
        private readonly ?StorageMetricsSource $linuxSource = null,
        private readonly ?StorageMetricsSource $macosSource = null,
        private readonly ?StorageMetricsSource $windowsSource = null,
        private readonly ?StorageMetricsSource $freebsdSource = null,
    ) {}

    public function read(): Result
    {
        $osFamily = OsDetector::getFamily();

        return match ($osFamily) {
            'Linux' => $this->getLinuxSource()->read(),
            'Darwin' => $this->getMacosSource()->read(),
            'Windows' => $this->getWindowsSource()->read(),
            'BSD' => $this->getFreeBSDSource()->read(),
            default => Result::failure(
                new SystemMetricsException("Unsupported OS family: {$osFamily}")
            ),
        };
    }

    private function getLinuxSource(): StorageMetricsSource
    {
        return $this->linuxSource ?? new FallbackStorageMetricsSource([
            new LinuxStatfsStorageMetricsSource,  // 1. FFI statfs64() (fast, no exec)
            new LinuxProcStorageMetricsSource,    // 2. df command (fallback)
        ]);
    }

    private function getMacosSource(): StorageMetricsSource
    {
        return $this->macosSource ?? new MacOsDfStorageMetricsSource;
    }

    private function getWindowsSource(): StorageMetricsSource
    {
        return $this->windowsSource ?? new WindowsFFIStorageMetricsSource;
    }

    private function getFreeBSDSource(): StorageMetricsSource
    {
        return $this->freebsdSource ?? new FreeBSDStatfsStorageMetricsSource;
    }
}
