<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support\Parser\Cgroup\V2;

/**
 * Resolves cgroup v2 (unified hierarchy) paths.
 */
final class CgroupV2PathResolver
{
    private ?string $cachedUnifiedPath = null;

    private bool $pathResolved = false;

    /**
     * Resolve cgroup v2 file path.
     */
    public function resolvePath(string $file): ?string
    {
        $relative = $this->getUnifiedPath() ?? '';
        $base = '/sys/fs/cgroup';
        $relative = rtrim($relative, '/');
        $path = rtrim($base, '/').($relative === '' ? '' : $relative).'/'.$file;

        return is_readable($path) ? $path : null;
    }

    /**
     * Get unified cgroup path from /proc/self/cgroup.
     */
    public function getUnifiedPath(): ?string
    {
        if ($this->pathResolved) {
            return $this->cachedUnifiedPath;
        }

        $contents = @file_get_contents('/proc/self/cgroup');
        if ($contents === false) {
            $this->pathResolved = true;

            return null;
        }

        foreach (explode("\n", trim($contents)) as $line) {
            if ($line === '') {
                continue;
            }

            $parts = explode(':', $line, 3);
            if (count($parts) === 3 && $parts[0] === '0' && $parts[1] === '') {
                $this->cachedUnifiedPath = $parts[2];
                $this->pathResolved = true;

                return $this->cachedUnifiedPath;
            }
        }

        $this->pathResolved = true;

        return null;
    }

    /**
     * Reset cached path (primarily for testing).
     */
    public function reset(): void
    {
        $this->cachedUnifiedPath = null;
        $this->pathResolved = false;
    }
}
