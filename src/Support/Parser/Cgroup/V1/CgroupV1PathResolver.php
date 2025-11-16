<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support\Parser\Cgroup\V1;

/**
 * Resolves cgroup v1 controller paths.
 */
final class CgroupV1PathResolver
{
    /**
     * @var array<string, array{mount: string, path: string}>|null
     */
    private ?array $cachedMappings = null;

    /**
     * Resolve cgroup v1 controller path.
     */
    public function resolvePath(string $controller, string $file): ?string
    {
        $mappings = $this->getMappings();

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
     * Parse /proc/self/cgroup for v1 controller mappings.
     *
     * @return array<string, array{mount: string, path: string}>
     */
    public function getMappings(): array
    {
        if ($this->cachedMappings !== null) {
            return $this->cachedMappings;
        }

        $contents = @file_get_contents('/proc/self/cgroup');
        if ($contents === false) {
            $this->cachedMappings = [];

            return $this->cachedMappings;
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

        $this->cachedMappings = $map;

        return $this->cachedMappings;
    }

    /**
     * Reset cached mappings (primarily for testing).
     */
    public function reset(): void
    {
        $this->cachedMappings = null;
    }
}
