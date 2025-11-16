<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support\Parser\Cgroup\V1;

/**
 * Parses cgroup v1 CPU metrics.
 */
final class CgroupV1CpuParser
{
    /**
     * @var array<string, array{usage: float, timestamp: float}>
     */
    private array $usageCache = [];

    public function __construct(
        private readonly CgroupV1PathResolver $pathResolver
    ) {}

    /**
     * Parse cgroup v1 CPU quota (cpu.cfs_quota_us / cpu.cfs_period_us).
     */
    public function parseQuota(float $hostCpuCores): ?float
    {
        $quotaPath = $this->pathResolver->resolvePath('cpu', 'cpu.cfs_quota_us')
            ?? $this->pathResolver->resolvePath('cpuacct', 'cpu.cfs_quota_us');

        $periodPath = $this->pathResolver->resolvePath('cpu', 'cpu.cfs_period_us')
            ?? $this->pathResolver->resolvePath('cpuacct', 'cpu.cfs_period_us');

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
     * Parse cgroup v1 CPU usage (cpuacct.usage).
     */
    public function parseUsage(): ?float
    {
        $path = $this->pathResolver->resolvePath('cpuacct', 'cpuacct.usage');
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

        return $this->computeUsageRate($path, $usageNanosec, 1_000_000_000);
    }

    /**
     * Parse cgroup v1 CPU throttling (cpu.stat nr_throttled).
     */
    public function parseThrottled(): ?int
    {
        $path = $this->pathResolver->resolvePath('cpu', 'cpu.stat');
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
     * Compute CPU usage rate (cores) using cached deltas.
     *
     * @param  float  $scale  Unit conversion (microseconds=1_000_000, nanoseconds=1_000_000_000)
     */
    private function computeUsageRate(string $path, float $usageValue, float $scale): ?float
    {
        $now = microtime(true);
        $cache = $this->usageCache[$path] ?? null;

        $this->usageCache[$path] = [
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
     * Reset cached usage (primarily for testing).
     */
    public function reset(): void
    {
        $this->usageCache = [];
    }
}
