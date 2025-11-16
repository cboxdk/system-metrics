<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Tests\E2E\Support;

use RuntimeException;

/**
 * Helper class for Kind (Kubernetes in Docker) operations in E2E tests.
 */
class KindHelper
{
    private static string $clusterName = 'system-metrics-test';

    /**
     * Ensure Kind cluster exists and is ready.
     */
    public static function ensureCluster(): void
    {
        if (! self::clusterExists()) {
            self::createCluster();
        }
    }

    /**
     * Check if cluster exists.
     */
    public static function clusterExists(): bool
    {
        exec(
            sprintf(
                'kind get clusters | grep -q %s',
                escapeshellarg(self::$clusterName)
            ),
            $output,
            $returnCode
        );

        return $returnCode === 0;
    }

    /**
     * Create Kind cluster.
     */
    public static function createCluster(): void
    {
        $configPath = __DIR__.'/../../../e2e/kind/cluster-config.yaml';

        if (! file_exists($configPath)) {
            throw new RuntimeException("Kind config not found: {$configPath}");
        }

        $command = sprintf(
            'kind create cluster --name %s --config %s',
            escapeshellarg(self::$clusterName),
            escapeshellarg($configPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException(
                'Failed to create Kind cluster: '.implode("\n", $output)
            );
        }

        // Wait for cluster to be ready
        sleep(10);
        self::waitForNodes();
    }

    /**
     * Wait for all nodes to be ready.
     */
    public static function waitForNodes(int $timeoutSeconds = 120): void
    {
        $command = sprintf(
            'kubectl wait --for=condition=Ready nodes --all --timeout=%ds --context kind-%s',
            $timeoutSeconds,
            self::$clusterName
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException(
                'Nodes did not become ready: '.implode("\n", $output)
            );
        }
    }

    /**
     * Deploy test pods to cluster.
     */
    public static function deployTestPods(): void
    {
        $manifestsDir = __DIR__.'/../../../e2e/kind';

        // Create namespace
        self::kubectl('create namespace metrics-test --dry-run=client -o yaml | kubectl apply -f -');

        // Apply resource quota
        self::kubectl("apply -f {$manifestsDir}/resource-quota.yaml");

        // Load PHP image into Kind
        self::loadImage('php:8.3-cli');

        // Deploy test pods
        self::kubectl("apply -f {$manifestsDir}/pod-cpu-limit.yaml");
        self::kubectl("apply -f {$manifestsDir}/pod-memory-limit.yaml");

        // Wait for pods to be ready
        self::waitForPod('php-metrics-cpu-test', 'metrics-test');
        self::waitForPod('php-metrics-memory-test', 'metrics-test');
    }

    /**
     * Load Docker image into Kind cluster.
     */
    public static function loadImage(string $image): void
    {
        // Pull image first
        exec(sprintf('docker pull %s', escapeshellarg($image)));

        // Load into Kind
        $command = sprintf(
            'kind load docker-image %s --name %s',
            escapeshellarg($image),
            escapeshellarg(self::$clusterName)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException(
                "Failed to load image {$image}: ".implode("\n", $output)
            );
        }
    }

    /**
     * Wait for pod to be ready.
     */
    public static function waitForPod(
        string $podName,
        string $namespace,
        int $timeoutSeconds = 120
    ): void {
        $command = sprintf(
            'kubectl wait --for=condition=Ready pod/%s -n %s --timeout=%ds',
            escapeshellarg($podName),
            escapeshellarg($namespace),
            $timeoutSeconds
        );

        exec(self::addContext($command), $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException(
                "Pod {$podName} did not become ready: ".implode("\n", $output)
            );
        }
    }

    /**
     * Execute command in pod.
     */
    public static function execInPod(
        string $podName,
        string $namespace,
        string $command
    ): string {
        $output = [];
        $returnCode = 0;

        $fullCommand = sprintf(
            'kubectl exec -n %s %s -- sh -c %s',
            escapeshellarg($namespace),
            escapeshellarg($podName),
            escapeshellarg($command)
        );

        exec(self::addContext($fullCommand), $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException(
                "kubectl exec failed in {$podName}: ".implode("\n", $output)
            );
        }

        return implode("\n", $output);
    }

    /**
     * Get pod logs.
     */
    public static function getPodLogs(string $podName, string $namespace): string
    {
        $command = sprintf(
            'kubectl logs -n %s %s',
            escapeshellarg($namespace),
            escapeshellarg($podName)
        );

        $output = [];
        exec(self::addContext($command), $output);

        return implode("\n", $output);
    }

    /**
     * Check if pod exists.
     */
    public static function podExists(string $podName, string $namespace): bool
    {
        $command = sprintf(
            'kubectl get pod -n %s %s',
            escapeshellarg($namespace),
            escapeshellarg($podName)
        );

        exec(self::addContext($command), $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Delete pod.
     */
    public static function deletePod(string $podName, string $namespace): void
    {
        $command = sprintf(
            'kubectl delete pod -n %s %s --ignore-not-found=true',
            escapeshellarg($namespace),
            escapeshellarg($podName)
        );

        self::kubectl($command);
    }

    /**
     * Run kubectl command.
     */
    public static function kubectl(string $args): string
    {
        $command = self::addContext("kubectl {$args}");

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException(
                'kubectl failed: '.implode("\n", $output)
            );
        }

        return implode("\n", $output);
    }

    /**
     * Add Kind context to kubectl command.
     */
    private static function addContext(string $command): string
    {
        return sprintf(
            '%s --context kind-%s',
            $command,
            self::$clusterName
        );
    }

    /**
     * Cleanup Kind cluster and resources.
     */
    public static function cleanup(): void
    {
        // Delete namespace (cascades to all resources)
        try {
            self::kubectl('delete namespace metrics-test --ignore-not-found=true');
        } catch (RuntimeException $e) {
            // Ignore cleanup errors
        }

        // Delete cluster
        exec(
            sprintf(
                'kind delete cluster --name %s',
                escapeshellarg(self::$clusterName)
            )
        );
    }

    /**
     * Get cluster name.
     */
    public static function getClusterName(): string
    {
        return self::$clusterName;
    }
}
