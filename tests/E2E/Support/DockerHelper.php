<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Tests\E2E\Support;

use RuntimeException;

/**
 * Helper class for Docker container operations in E2E tests.
 */
class DockerHelper
{
    /**
     * Execute command in running container.
     *
     * @throws RuntimeException If command execution fails
     */
    public static function exec(string $container, string $command): string
    {
        $output = [];
        $returnCode = 0;

        // Use docker compose exec to resolve service names
        $composeFile = __DIR__.'/../../../e2e/compose/docker-compose.yml';

        exec(
            sprintf(
                'docker compose -f %s exec -T %s sh -c %s 2>&1',
                escapeshellarg($composeFile),
                escapeshellarg($container),
                escapeshellarg($command)
            ),
            $output,
            $returnCode
        );

        if ($returnCode !== 0) {
            throw new RuntimeException(
                "Docker exec failed in {$container}: ".implode("\n", $output)
            );
        }

        return implode("\n", $output);
    }

    /**
     * Check if container is using cgroup v1 or v2.
     */
    public static function detectCgroupVersion(string $container): string
    {
        try {
            self::exec($container, 'test -f /sys/fs/cgroup/cgroup.controllers');

            return 'v2';
        } catch (RuntimeException $e) {
            return 'v1';
        }
    }

    /**
     * Get container ID from Docker Compose service name.
     */
    public static function getContainerId(string $serviceName): string
    {
        $composeFile = __DIR__.'/../../../e2e/compose/docker-compose.yml';
        $output = [];
        exec(
            sprintf(
                'docker compose -f %s ps -q %s',
                escapeshellarg($composeFile),
                escapeshellarg($serviceName)
            ),
            $output
        );

        if (empty($output)) {
            throw new RuntimeException("Container not found: {$serviceName}");
        }

        return trim($output[0]);
    }

    /**
     * Read file from container.
     */
    public static function readFile(string $container, string $path): string
    {
        return self::exec($container, sprintf('cat %s', escapeshellarg($path)));
    }

    /**
     * Check if file exists in container.
     */
    public static function fileExists(string $container, string $path): bool
    {
        try {
            self::exec($container, sprintf('test -f %s', escapeshellarg($path)));

            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    /**
     * Run stress test in container (CPU load).
     */
    public static function stressCpu(
        string $container,
        int $durationSeconds = 5,
        int $workers = 2
    ): void {
        $command = sprintf(
            'stress-ng --cpu %d --timeout %ds --metrics-brief',
            $workers,
            $durationSeconds
        );

        try {
            self::exec($container, $command);
        } catch (RuntimeException $e) {
            // stress-ng might not be available, ignore
        }
    }

    /**
     * Allocate memory in container.
     */
    public static function stressMemory(
        string $container,
        int $megabytes = 100,
        int $durationSeconds = 5
    ): void {
        $command = sprintf(
            'stress-ng --vm 1 --vm-bytes %dM --timeout %ds --metrics-brief',
            $megabytes,
            $durationSeconds
        );

        try {
            self::exec($container, $command);
        } catch (RuntimeException $e) {
            // stress-ng might not be available, ignore
        }
    }

    /**
     * Run PHP code in container and return output.
     */
    public static function runPhp(string $container, string $code): string
    {
        $command = sprintf(
            'cd /workspace && php -r %s',
            escapeshellarg($code)
        );

        return self::exec($container, $command);
    }

    /**
     * Run Pest test in container.
     */
    public static function runPestTest(
        string $container,
        string $testPath,
        array $groups = []
    ): string {
        $groupArgs = empty($groups) ? '' : '--group='.implode(',', $groups);

        $command = sprintf(
            'cd /workspace && vendor/bin/pest %s %s',
            escapeshellarg($testPath),
            $groupArgs
        );

        return self::exec($container, $command);
    }

    /**
     * Check if container is running.
     */
    public static function isRunning(string $container): bool
    {
        $composeFile = __DIR__.'/../../../e2e/compose/docker-compose.yml';
        $output = [];
        exec(
            sprintf(
                'docker compose -f %s ps -q %s',
                escapeshellarg($composeFile),
                escapeshellarg($container)
            ),
            $output
        );

        return ! empty($output);
    }

    /**
     * Get container stats from Docker API.
     */
    public static function getStats(string $container): array
    {
        // Get container ID first
        $containerId = self::getContainerId($container);

        $output = [];
        exec(
            sprintf(
                'docker stats %s --no-stream --format "{{json .}}"',
                escapeshellarg($containerId)
            ),
            $output
        );

        if (empty($output)) {
            throw new RuntimeException("Could not get stats for: {$container}");
        }

        return json_decode($output[0], true);
    }
}
