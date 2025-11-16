<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support\Parser;

use PHPeek\SystemMetrics\DTO\Metrics\Container\CgroupVersion;
use PHPeek\SystemMetrics\DTO\Metrics\Container\ContainerLimits;
use PHPeek\SystemMetrics\DTO\Result;

/**
 * Coordinates cgroup parsing across v1 and v2.
 *
 * This class acts as a facade, delegating to version-specific parsers.
 */
final class CgroupParser
{
    private readonly Cgroup\CgroupVersionDetector $versionDetector;

    private readonly Cgroup\V1\CgroupV1PathResolver $v1PathResolver;

    private readonly Cgroup\V1\CgroupV1CpuParser $v1CpuParser;

    private readonly Cgroup\V1\CgroupV1MemoryParser $v1MemoryParser;

    private readonly Cgroup\V2\CgroupV2PathResolver $v2PathResolver;

    private readonly Cgroup\V2\CgroupV2CpuParser $v2CpuParser;

    private readonly Cgroup\V2\CgroupV2MemoryParser $v2MemoryParser;

    public function __construct()
    {
        $this->versionDetector = new Cgroup\CgroupVersionDetector;

        // Initialize V1 parsers
        $this->v1PathResolver = new Cgroup\V1\CgroupV1PathResolver;
        $this->v1CpuParser = new Cgroup\V1\CgroupV1CpuParser($this->v1PathResolver);
        $this->v1MemoryParser = new Cgroup\V1\CgroupV1MemoryParser($this->v1PathResolver);

        // Initialize V2 parsers
        $this->v2PathResolver = new Cgroup\V2\CgroupV2PathResolver;
        $this->v2CpuParser = new Cgroup\V2\CgroupV2CpuParser($this->v2PathResolver);
        $this->v2MemoryParser = new Cgroup\V2\CgroupV2MemoryParser($this->v2PathResolver);
    }

    /**
     * Detect cgroup version.
     */
    public static function detectVersion(): CgroupVersion
    {
        $detector = new Cgroup\CgroupVersionDetector;

        return $detector->detect();
    }

    /**
     * Parse container limits and usage.
     *
     * @return Result<ContainerLimits>
     */
    public function parse(float $hostCpuCores): Result
    {
        $version = $this->versionDetector->detect();

        if ($version === CgroupVersion::NONE) {
            return Result::success(new ContainerLimits(
                cgroupVersion: CgroupVersion::NONE,
                cpuQuota: null,
                memoryLimitBytes: null,
                cpuUsageCores: null,
                memoryUsageBytes: null,
                cpuThrottledCount: null,
                oomKillCount: null,
            ));
        }

        if ($version === CgroupVersion::V2) {
            $cpuQuota = $this->v2CpuParser->parseQuota($hostCpuCores);
            $memoryLimit = $this->v2MemoryParser->parseLimit();
            $cpuUsage = $this->v2CpuParser->parseUsage();
            $memoryUsage = $this->v2MemoryParser->parseUsage();
            $cpuThrottled = $this->v2CpuParser->parseThrottled();
            $oomKills = $this->v2MemoryParser->parseOomKills();
        } else {
            $cpuQuota = $this->v1CpuParser->parseQuota($hostCpuCores);
            $memoryLimit = $this->v1MemoryParser->parseLimit();
            $cpuUsage = $this->v1CpuParser->parseUsage();
            $memoryUsage = $this->v1MemoryParser->parseUsage();
            $cpuThrottled = $this->v1CpuParser->parseThrottled();
            $oomKills = $this->v1MemoryParser->parseOomKills();
        }

        return Result::success(new ContainerLimits(
            cgroupVersion: $version,
            cpuQuota: $cpuQuota,
            memoryLimitBytes: $memoryLimit,
            cpuUsageCores: $cpuUsage,
            memoryUsageBytes: $memoryUsage,
            cpuThrottledCount: $cpuThrottled,
            oomKillCount: $oomKills,
        ));
    }

    /**
     * Reset all cached state (primarily for testing).
     */
    public static function reset(): void
    {
        // Reset is now handled by individual parser instances
        // For backward compatibility with tests, we create temporary instances
        $detector = new Cgroup\CgroupVersionDetector;
        $detector->reset();

        $v1PathResolver = new Cgroup\V1\CgroupV1PathResolver;
        $v1PathResolver->reset();

        $v1CpuParser = new Cgroup\V1\CgroupV1CpuParser($v1PathResolver);
        $v1CpuParser->reset();

        $v2PathResolver = new Cgroup\V2\CgroupV2PathResolver;
        $v2PathResolver->reset();

        $v2CpuParser = new Cgroup\V2\CgroupV2CpuParser($v2PathResolver);
        $v2CpuParser->reset();
    }
}
