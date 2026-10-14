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
}
