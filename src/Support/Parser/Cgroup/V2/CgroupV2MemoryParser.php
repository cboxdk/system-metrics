<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support\Parser\Cgroup\V2;

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
}
