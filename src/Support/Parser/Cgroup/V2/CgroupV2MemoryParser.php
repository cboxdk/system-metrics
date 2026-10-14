<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Support\Parser\Cgroup\V2;

/**
 * Parses cgroup v2 memory metrics.
 */
final class CgroupV2MemoryParser
{
    public function __construct(
        private readonly CgroupV2PathResolver $pathResolver
    ) {}

    /**
     * Parse cgroup v2 memory limit (memory.max).
     */
    public function parseLimit(): ?int
    {
        $path = $this->pathResolver->resolvePath('memory.max');
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
     * Parse cgroup v2 memory usage (memory.current).
     */
    public function parseUsage(): ?int
    {
        $path = $this->pathResolver->resolvePath('memory.current');
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
     * Parse cgroup v2 OOM kills (memory.events oom_kill).
     */
    public function parseOomKills(): ?int
    {
        $path = $this->pathResolver->resolvePath('memory.events');
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
     * Parse the memory working set: usage minus inactive file cache (memory.stat).
     *
     * Null when usage is unknown or memory.stat cannot be read.
     */
    public function parseWorkingSet(?int $usageBytes): ?int
    {
        if ($usageBytes === null) {
            return null;
        }

        $path = $this->pathResolver->resolvePath('memory.stat');
        if ($path === null) {
            return null;
        }

        $contents = @file_get_contents($path);

        return $this->workingSetFromMemoryStat($usageBytes, $contents === false ? null : $contents);
    }

    /**
     * The working set as cAdvisor and the kubelet define it: `memory.current`
     * minus `inactive_file` from memory.stat, floored at zero. Inactive file pages
     * are page cache the kernel reclaims before it OOM-kills, so they are not
     * pressure on the limit.
     *
     * Reads the `inactive_file` line. Null when the usage, the memory.stat contents or the key
     * is missing.
     *
     * @internal
     */
    public function workingSetFromMemoryStat(?int $usageBytes, ?string $memoryStat): ?int
    {
        if ($usageBytes === null || $memoryStat === null) {
            return null;
        }

        if (! preg_match('/^inactive_file\s+(\d+)\s*$/m', $memoryStat, $matches)) {
            return null;
        }

        return max(0, $usageBytes - (int) $matches[1]);
    }
}
