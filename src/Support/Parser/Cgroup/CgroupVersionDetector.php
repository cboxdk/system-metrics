<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support\Parser\Cgroup;

use PHPeek\SystemMetrics\DTO\Metrics\Container\CgroupVersion;

/**
 * Detects the cgroup version available on the system.
 */
final class CgroupVersionDetector
{
    private ?CgroupVersion $detectedVersion = null;

    /**
     * Detect cgroup version.
     */
    public function detect(): CgroupVersion
    {
        if ($this->detectedVersion !== null) {
            return $this->detectedVersion;
        }

        // Check for cgroup v2 (unified hierarchy)
        if (file_exists('/sys/fs/cgroup/cgroup.controllers')) {
            $this->detectedVersion = CgroupVersion::V2;

            return $this->detectedVersion;
        }

        // Check for cgroup v1 (separate controllers)
        if (file_exists('/proc/self/cgroup')) {
            $this->detectedVersion = CgroupVersion::V1;

            return $this->detectedVersion;
        }

        $this->detectedVersion = CgroupVersion::NONE;

        return $this->detectedVersion;
    }

    /**
     * Reset cached version (primarily for testing).
     */
    public function reset(): void
    {
        $this->detectedVersion = null;
    }
}
