<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support\Parser\Cgroup\V2;

/**
 * Parses cgroup v2 CPU metrics.
 */
final class CgroupV2CpuParser
{
    /**
     * @var array<string, array{usage: float, timestamp: float}>
     */
    private array $usageCache = [];

    public function __construct(
        private readonly CgroupV2PathResolver $pathResolver
    ) {}

    /**
     * Parse cgroup v2 CPU quota (cpu.max).
     */
    public function parseQuota(float $hostCpuCores): ?float
    {
        $path = $this->pathResolver->resolvePath('cpu.max');
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
     * Parse cgroup v2 CPU usage (cpu.stat usage_usec).
     */
    public function parseUsage(): ?float
    {
        $path = $this->pathResolver->resolvePath('cpu.stat');
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

        return $this->computeUsageRate($path, $usageUsec, 1_000_000);
    }

    /**
     * Parse cgroup v2 CPU throttling (cpu.stat throttled_usec).
     */
    public function parseThrottled(): ?int
    {
        $path = $this->pathResolver->resolvePath('cpu.stat');
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
