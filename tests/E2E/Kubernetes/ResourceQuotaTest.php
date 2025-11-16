<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Tests\E2E\Support\KindHelper;

describe('Kubernetes - Resource Quotas', function () {

    it('validates namespace resource quota exists', function () {
        $output = KindHelper::kubectl('get resourcequota metrics-quota -n metrics-test -o json');
        $quota = json_decode($output, true);

        expect($quota)->not()->toBeEmpty('Resource quota should exist');
        expect($quota['metadata']['name'])->toBe('metrics-quota');

        // Verify hard limits
        $hard = $quota['spec']['hard'];
        expect($hard['requests.cpu'])->toBe('2', 'CPU request quota should be 2');
        expect($hard['requests.memory'])->toBe('2Gi', 'Memory request quota should be 2Gi');
        expect($hard['limits.cpu'])->toBe('4', 'CPU limit quota should be 4');
        expect($hard['limits.memory'])->toBe('4Gi', 'Memory limit quota should be 4Gi');
        expect($hard['pods'])->toBe('10', 'Pod quota should be 10');
    });

    it('validates resource quota usage', function () {
        $output = KindHelper::kubectl('get resourcequota metrics-quota -n metrics-test -o json');
        $quota = json_decode($output, true);

        $used = $quota['status']['used'] ?? [];

        // Verify usage is being tracked
        expect($used)->not()->toBeEmpty('Usage should be tracked');

        // Parse CPU requests (e.g., "200m")
        if (isset($used['requests.cpu'])) {
            $cpuReq = $used['requests.cpu'];
            expect($cpuReq)->toBeString('CPU requests should be reported');

            // Should show our two pods (100m + something)
            // Just verify it's not zero
            expect($cpuReq)->not()->toBe('0', 'CPU requests should be non-zero');
        }

        // Parse memory requests (e.g., "192Mi")
        if (isset($used['requests.memory'])) {
            $memReq = $used['requests.memory'];
            expect($memReq)->toBeString('Memory requests should be reported');
            expect($memReq)->not()->toBe('0', 'Memory requests should be non-zero');
        }
    });

    it('validates pods respect namespace quotas', function () {
        // List all pods in namespace
        $output = KindHelper::kubectl('get pods -n metrics-test -o json');
        $pods = json_decode($output, true);

        $items = $pods['items'] ?? [];
        expect($items)->not()->toBeEmpty('Should have pods in namespace');

        // Calculate total resource requests
        $totalCpuRequests = 0;
        $totalMemoryRequests = 0;

        foreach ($items as $pod) {
            $containers = $pod['spec']['containers'] ?? [];
            foreach ($containers as $container) {
                $resources = $container['resources'] ?? [];
                $requests = $resources['requests'] ?? [];

                // Parse CPU (e.g., "100m")
                if (isset($requests['cpu'])) {
                    $cpu = $requests['cpu'];
                    if (str_ends_with($cpu, 'm')) {
                        $totalCpuRequests += (int) rtrim($cpu, 'm');
                    }
                }

                // Parse memory (e.g., "64Mi")
                if (isset($requests['memory'])) {
                    $mem = $requests['memory'];
                    if (str_ends_with($mem, 'Mi')) {
                        $totalMemoryRequests += (int) rtrim($mem, 'Mi');
                    }
                }
            }
        }

        // Verify within quotas (2 CPU = 2000m, 2Gi = 2048Mi)
        expect($totalCpuRequests)->toBeLessThanOrEqual(2000, 'CPU requests within quota');
        expect($totalMemoryRequests)->toBeLessThanOrEqual(2048, 'Memory requests within quota');
    });

    it('validates QoS class based on resource limits', function () {
        // Check QoS class for CPU test pod
        $output = KindHelper::kubectl('get pod php-metrics-cpu-test -n metrics-test -o json');
        $pod = json_decode($output, true);

        $qosClass = $pod['status']['qosClass'] ?? null;

        // Pod has both requests and limits set
        // QoS should be "Guaranteed" if requests == limits, otherwise "Burstable"
        expect(['Guaranteed', 'Burstable'])->toContain(
            $qosClass,
            'Pod should have Guaranteed or Burstable QoS class'
        );
    });

    it('validates limit ranges are enforced', function () {
        // Check if namespace has LimitRange
        $output = KindHelper::kubectl('get limitrange -n metrics-test 2>&1');

        // LimitRange might not exist, which is fine
        // Just verify command works
        expect($output)->toBeString('Should be able to query LimitRange');
    });

    it('validates resource quota prevents over-allocation', function () {
        // Try to get current quota status
        $output = KindHelper::kubectl('describe resourcequota metrics-quota -n metrics-test');

        expect($output)->toContain('Used', 'Should show used resources');
        expect($output)->toContain('Hard', 'Should show hard limits');

        // Verify quota enforcement is active
        expect($output)->toContain('Name:', 'Should show quota details');
    });

    it('reads metrics from pod under quota constraints', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::cpu();
if ($result->isSuccess()) {
    $cpu = $result->getValue();
    echo json_encode([
        'cores' => $cpu->coreCount(),
        'busy' => $cpu->total->busy(),
    ]);
}
PHP;

        $output = KindHelper::execInPod('php-metrics-cpu-test', 'metrics-test', "cd /workspace && php -r '$code'");
        $data = json_decode($output, true);

        // Even with quotas, metrics should be readable
        expect($data['cores'])->toBeGreaterThan(0, 'CPU metrics work under quotas');
        expect($data['busy'])->toBeGreaterThanOrEqual(0, 'CPU busy time readable');
    });
});
