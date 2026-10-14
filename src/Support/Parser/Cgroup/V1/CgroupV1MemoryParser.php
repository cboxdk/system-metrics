<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Support\Parser\Cgroup\V1;

/**
 * Parses cgroup v1 memory metrics.
 */
final class CgroupV1MemoryParser
{
    public function __construct(
        private readonly CgroupV1PathResolver $pathResolver
    ) {}

    /**
     * Parse cgroup v1 memory limit (memory.limit_in_bytes).
     */
    public function parseLimit(): ?int
    {
        $path = $this->pathResolver->resolvePath('memory', 'memory.limit_in_bytes');
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
     * Parse cgroup v1 memory usage (memory.usage_in_bytes).
     */
    public function parseUsage(): ?int
    {
        $path = $this->pathResolver->resolvePath('memory', 'memory.usage_in_bytes');
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
     * Parse cgroup v1 OOM kills (memory.oom_control oom_kill).
     */
    public function parseOomKills(): ?int
    {
        $path = $this->pathResolver->resolvePath('memory', 'memory.oom_control');
        if ($path === null) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return $this->oomKillsFromOomControl($contents);
    }

    /**
     * The `oom_kill` counter from memory.oom_control (Linux 4.13+), or null on
     * kernels that do not report it. `under_oom` is not a count: it is 1 only
     * while the cgroup is currently stalled at its limit with the OOM killer
     * disabled.
     *
     * @internal
     */
    public function oomKillsFromOomControl(string $contents): ?int
    {
        if (! preg_match('/^oom_kill\s+(\d+)\s*$/m', $contents, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * Parse the memory working set: usage minus inactive file cache (memory.stat).
     *
     * Null when usage is unknown or memory.stat cannot be read.
     */
    public function parseWorkingSet(?int $usageBytes): ?int
    {
        if ($usageBytes === null) {
            return null;
        }

        $path = $this->pathResolver->resolvePath('memory', 'memory.stat');
        if ($path === null) {
            return null;
        }

        $contents = @file_get_contents($path);

        return $this->workingSetFromMemoryStat($usageBytes, $contents === false ? null : $contents);
    }

    /**
     * The working set as cAdvisor and the kubelet define it: `memory.usage_in_bytes`
     * minus `total_inactive_file` from memory.stat, floored at zero. Inactive file pages
     * are page cache the kernel reclaims before it OOM-kills, so they are not
     * pressure on the limit.
     *
     * Reads the hierarchical `total_inactive_file`, which matches the hierarchical
     * `memory.usage_in_bytes`; the local `inactive_file` line is ignored. Null when the usage, the memory.stat contents or the key
     * is missing.
     *
     * @internal
     */
    public function workingSetFromMemoryStat(?int $usageBytes, ?string $memoryStat): ?int
    {
        if ($usageBytes === null || $memoryStat === null) {
            return null;
        }

        if (! preg_match('/^total_inactive_file\s+(\d+)\s*$/m', $memoryStat, $matches)) {
            return null;
        }

        return max(0, $usageBytes - (int) $matches[1]);
    }
}
