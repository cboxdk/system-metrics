<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Container;

use PHPeek\SystemMetrics\Contracts\ContainerMetricsSource;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Support\Parser\CgroupParser;

/**
 * Read container metrics from Linux cgroups (v1 and v2).
 */
final class LinuxCgroupMetricsSource implements ContainerMetricsSource
{
    public function __construct(
        private readonly CgroupParser $parser = new CgroupParser,
    ) {}

    public function read(): Result
    {
        // Get host CPU cores to calculate effective limits
        $hostCpuCores = $this->getHostCpuCores();

        return $this->parser->parse($hostCpuCores);
    }

    /**
     * Get number of host CPU cores.
     */
    private function getHostCpuCores(): float
    {
        // Try /proc/cpuinfo
        $cpuinfo = @file_get_contents('/proc/cpuinfo');
        if ($cpuinfo !== false) {
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            if (! empty($matches[0])) {
                return (float) count($matches[0]);
            }
        }

        // Fallback to 1 core
        return 1.0;
    }
}
