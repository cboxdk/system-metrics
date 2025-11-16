<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support\Parser;

use PHPeek\SystemMetrics\DTO\Metrics\Container\CgroupVersion;
use PHPeek\SystemMetrics\DTO\Metrics\Container\ContainerLimits;
use PHPeek\SystemMetrics\DTO\Result;

/**
 * Parse cgroup resource limits and usage.
 */
final class CgroupParser
{
    private static ?CgroupVersion $detectedVersion = null;

    /**
     * @var array<string, array{usage: float, timestamp: float}>
     */
    private static array $cpuUsageCache = [];

    /**
     * Detect cgroup version.
     */
    public static function detectVersion(): CgroupVersion
    {
        if (self::$detectedVersion !== null) {
            return self::$detectedVersion;
        }

        // Check for cgroup v2 (unified hierarchy)
        if (file_exists('/sys/fs/cgroup/cgroup.controllers')) {
            self::$detectedVersion = CgroupVersion::V2;

            return self::$detectedVersion;
        }

        // Check for cgroup v1 (separate controllers)
        if (file_exists('/proc/self/cgroup')) {
            self::$detectedVersion = CgroupVersion::V1;

            return self::$detectedVersion;
        }

        self::$detectedVersion = CgroupVersion::NONE;

        return self::$detectedVersion;
    }

    /**
     * Parse container limits and usage.
     *
     * @return Result<ContainerLimits>
     */
    public function parse(float $hostCpuCores): Result
    {
        $version = self::detectVersion();

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

        $cpuQuota = $version === CgroupVersion::V2
            ? $this->parseCgroupV2CpuQuota($hostCpuCores)
            : $this->parseCgroupV1CpuQuota($hostCpuCores);

        $memoryLimit = $version === CgroupVersion::V2
            ? $this->parseCgroupV2MemoryLimit()
            : $this->parseCgroupV1MemoryLimit();

        $cpuUsage = $version === CgroupVersion::V2
            ? $this->parseCgroupV2CpuUsage()
            : $this->parseCgroupV1CpuUsage();

        $memoryUsage = $version === CgroupVersion::V2
            ? $this->parseCgroupV2MemoryUsage()
            : $this->parseCgroupV1MemoryUsage();

        $cpuThrottled = $version === CgroupVersion::V2
            ? $this->parseCgroupV2CpuThrottled()
            : $this->parseCgroupV1CpuThrottled();

        $oomKills = $version === CgroupVersion::V2
            ? $this->parseCgroupV2OomKills()
            : $this->parseCgroupV1OomKills();

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
     * Parse cgroup v2 CPU quota (cpu.max).
     */
    private function parseCgroupV2CpuQuota(float $hostCpuCores): ?float
    {
        $path = $this->resolveCgroupV2Path('cpu.max');
        if ($path === null) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $contents = trim($contents);
        if (! str_contains($contents, ' ')) {
            return null;
        }

        [$quotaRaw, $periodRaw] = explode(' ', $contents, 2);

        if ($quotaRaw === 'max') {
            return null; // No limit
        }

        $quota = (float) $quotaRaw;
        $period = (float) $periodRaw;

        if ($quota <= 0 || $period <= 0) {
            return null;
        }

        $limit = $quota / $period;

        return $limit > 0 ? min($limit, $hostCpuCores) : null;
    }

    /**
     * Parse cgroup v1 CPU quota (cpu.cfs_quota_us / cpu.cfs_period_us).
     */
    private function parseCgroupV1CpuQuota(float $hostCpuCores): ?float
    {
        $quotaPath = $this->resolveCgroupV1Path('cpu', 'cpu.cfs_quota_us')
            ?? $this->resolveCgroupV1Path('cpuacct', 'cpu.cfs_quota_us');

        $periodPath = $this->resolveCgroupV1Path('cpu', 'cpu.cfs_period_us')
            ?? $this->resolveCgroupV1Path('cpuacct', 'cpu.cfs_period_us');

        if ($quotaPath === null || $periodPath === null) {
            return null;
        }

        $quota = @file_get_contents($quotaPath);
        $period = @file_get_contents($periodPath);

        if ($quota === false || $period === false) {
            return null;
        }

        $quotaValue = (float) trim($quota);
        $periodValue = (float) trim($period);

        if ($quotaValue <= 0 || $periodValue <= 0) {
            return null;
        }

        $limit = $quotaValue / $periodValue;

        return $limit > 0 ? min($limit, $hostCpuCores) : null;
    }

    /**
     * Parse cgroup v2 memory limit (memory.max).
     */
    private function parseCgroupV2MemoryLimit(): ?int
    {
        $path = $this->resolveCgroupV2Path('memory.max');
        if ($path === null) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $value = trim($contents);

        if ($value === 'max' || ! is_numeric($value)) {
            return null; // No limit
        }

        $limit = (int) $value;

        return $limit > 0 ? $limit : null;
    }

    /**
     * Parse cgroup v1 memory limit (memory.limit_in_bytes).
     */
    private function parseCgroupV1MemoryLimit(): ?int
    {
        $path = $this->resolveCgroupV1Path('memory', 'memory.limit_in_bytes');
        if ($path === null) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $value = trim($contents);

        if (! is_numeric($value)) {
            return null;
        }

        $limit = (int) $value;

        // Ignore unrealistic limits (> 8 EiB) often reported as "no limit"
        if ($limit <= 0 || $limit >= 9_000_000_000_000_000_000) {
            return null;
        }

        return $limit;
    }

    /**
     * Parse cgroup v2 CPU usage (cpu.stat usage_usec).
     */
    private function parseCgroupV2CpuUsage(): ?float
    {
        $path = $this->resolveCgroupV2Path('cpu.stat');
        if ($path === null) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        if (! preg_match('/usage_usec\s+(\d+)/', $contents, $matches)) {
            return null;
        }

        $usageUsec = (float) $matches[1];

        return $this->computeCpuUsageRate($path, $usageUsec, 1_000_000);
    }

    /**
     * Parse cgroup v1 CPU usage (cpuacct.usage).
     */
    private function parseCgroupV1CpuUsage(): ?float
    {
        $path = $this->resolveCgroupV1Path('cpuacct', 'cpuacct.usage');
        if ($path === null) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $value = trim($contents);
        if (! is_numeric($value)) {
            return null;
        }

        $usageNanosec = (float) $value;

        return $this->computeCpuUsageRate($path, $usageNanosec, 1_000_000_000);
    }

    /**
     * Parse cgroup v2 memory usage (memory.current).
     */
    private function parseCgroupV2MemoryUsage(): ?int
    {
        $path = $this->resolveCgroupV2Path('memory.current');
        if ($path === null) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $value = trim($contents);
        if (! is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }

    /**
     * Parse cgroup v1 memory usage (memory.usage_in_bytes).
     */
    private function parseCgroupV1MemoryUsage(): ?int
    {
        $path = $this->resolveCgroupV1Path('memory', 'memory.usage_in_bytes');
        if ($path === null) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $value = trim($contents);
        if (! is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }

    /**
     * Parse cgroup v2 CPU throttling (cpu.stat throttled_usec).
     */
    private function parseCgroupV2CpuThrottled(): ?int
    {
        $path = $this->resolveCgroupV2Path('cpu.stat');
        if ($path === null) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        if (! preg_match('/nr_throttled\s+(\d+)/', $contents, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * Parse cgroup v1 CPU throttling (cpu.stat nr_throttled).
     */
    private function parseCgroupV1CpuThrottled(): ?int
    {
        $path = $this->resolveCgroupV1Path('cpu', 'cpu.stat');
        if ($path === null) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        if (! preg_match('/nr_throttled\s+(\d+)/', $contents, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * Parse cgroup v2 OOM kills (memory.events oom_kill).
     */
    private function parseCgroupV2OomKills(): ?int
    {
        $path = $this->resolveCgroupV2Path('memory.events');
        if ($path === null) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        if (! preg_match('/oom_kill\s+(\d+)/', $contents, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * Parse cgroup v1 OOM kills (memory.oom_control under_oom).
     */
    private function parseCgroupV1OomKills(): ?int
    {
        $path = $this->resolveCgroupV1Path('memory', 'memory.oom_control');
        if ($path === null) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        if (! preg_match('/under_oom\s+(\d+)/', $contents, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * Compute CPU usage rate (cores) using cached deltas.
     *
     * @param  float  $scale  Unit conversion (microseconds=1_000_000, nanoseconds=1_000_000_000)
     */
    private function computeCpuUsageRate(string $path, float $usageValue, float $scale): ?float
    {
        $now = microtime(true);
        $cache = self::$cpuUsageCache[$path] ?? null;

        self::$cpuUsageCache[$path] = [
            'usage' => $usageValue,
            'timestamp' => $now,
        ];

        if ($cache === null) {
            return null; // Need at least two samples
        }

        $deltaUsage = $usageValue - $cache['usage'];
        $deltaTime = $now - $cache['timestamp'];

        if ($deltaUsage < 0 || $deltaTime <= 0) {
            return null;
        }

        $cpuSeconds = $deltaUsage / $scale;

        return $cpuSeconds / $deltaTime;
    }

    /**
     * Resolve cgroup v2 file path.
     */
    private function resolveCgroupV2Path(string $file): ?string
    {
        $relative = $this->getUnifiedCgroupPath() ?? '';
        $base = '/sys/fs/cgroup';
        $relative = rtrim($relative, '/');
        $path = rtrim($base, '/').($relative === '' ? '' : $relative).'/'.$file;

        return is_readable($path) ? $path : null;
    }

    /**
     * Resolve cgroup v1 controller path.
     */
    private function resolveCgroupV1Path(string $controller, string $file): ?string
    {
        $mappings = $this->getCgroupV1Mappings();

        if (isset($mappings[$controller])) {
            $mount = $mappings[$controller]['mount'];
            $relative = rtrim($mappings[$controller]['path'], '/');
            $base = '/sys/fs/cgroup/'.($mount !== '' ? $mount : $controller);
            $path = rtrim($base, '/').($relative === '' ? '' : $relative).'/'.$file;

            if (is_readable($path)) {
                return $path;
            }
        }

        // Fallback to standard location
        $fallback = "/sys/fs/cgroup/{$controller}/{$file}";

        return is_readable($fallback) ? $fallback : null;
    }

    /**
     * Get unified cgroup path from /proc/self/cgroup.
     */
    private function getUnifiedCgroupPath(): ?string
    {
        $contents = @file_get_contents('/proc/self/cgroup');
        if ($contents === false) {
            return null;
        }

        foreach (explode("\n", trim($contents)) as $line) {
            if ($line === '') {
                continue;
            }

            $parts = explode(':', $line, 3);
            if (count($parts) === 3 && $parts[0] === '0' && $parts[1] === '') {
                return $parts[2];
            }
        }

        return null;
    }

    /**
     * Parse /proc/self/cgroup for v1 controller mappings.
     *
     * @return array<string, array{mount: string, path: string}>
     */
    private function getCgroupV1Mappings(): array
    {
        $contents = @file_get_contents('/proc/self/cgroup');
        if ($contents === false) {
            return [];
        }

        $map = [];

        foreach (explode("\n", trim($contents)) as $line) {
            if ($line === '') {
                continue;
            }

            $parts = explode(':', $line, 3);
            if (count($parts) !== 3) {
                continue;
            }

            [, $controllers, $path] = $parts;

            if ($controllers === '') {
                continue; // v2 unified
            }

            foreach (explode(',', $controllers) as $ctrl) {
                $map[$ctrl] = [
                    'mount' => $ctrl,
                    'path' => $path,
                ];
            }
        }

        return $map;
    }

    /**
     * Reset cached values (useful for testing).
     */
    public static function reset(): void
    {
        self::$detectedVersion = null;
        self::$cpuUsageCache = [];
    }
}
