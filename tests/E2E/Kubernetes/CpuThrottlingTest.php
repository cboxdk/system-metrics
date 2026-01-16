<?php

declare(strict_types=1);

use Cbox\SystemMetrics\Tests\E2E\Support\KindHelper;

describe('Kubernetes - CPU Throttling', function () {

    it('detects CPU limits enforced by Kubernetes', function () {
        // Pod has 500m (0.5 CPU) limit
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = Cbox\SystemMetrics\SystemMetrics::cpu();
if ($result->isSuccess()) {
    echo json_encode(['cores' => $result->getValue()->coreCount()]);
}
PHP;

        $output = KindHelper::execInPod('php-metrics-cpu-test', 'metrics-test', "cd /workspace && php -r '$code'");
        $data = json_decode($output, true);

        // Should detect 0.5 cores
        expect($data['cores'])->toBeGreaterThan(0.4, 'Should detect ~0.5 CPU limit');
        expect($data['cores'])->toBeLessThan(0.6, 'Should detect ~0.5 CPU limit');
    });

    it('reads CPU throttling statistics from pod cgroup', function () {
        // Read cpu.stat from pod
        $cpuStat = KindHelper::execInPod(
            'php-metrics-cpu-test',
            'metrics-test',
            'cat /sys/fs/cgroup/cpu.stat 2>/dev/null || cat /sys/fs/cgroup/cpu/cpu.stat 2>/dev/null || echo "not available"'
        );

        // Should contain throttling info (v1 or v2)
        if (! str_contains($cpuStat, 'not available')) {
            expect($cpuStat)->toContain('throttled', 'Should contain throttling stats');
        }
    });

    it('validates CPU metrics under sustained load', function () {
        // Take baseline
        $baselineCode = <<<'PHP'
require 'vendor/autoload.php';
$result = Cbox\SystemMetrics\SystemMetrics::cpu();
if ($result->isSuccess()) {
    echo json_encode(['busy' => $result->getValue()->total->busy()]);
}
PHP;

        $baseline = json_decode(
            KindHelper::execInPod('php-metrics-cpu-test', 'metrics-test', "cd /workspace && php -r '$baselineCode'"),
            true
        );

        // Generate some CPU load
        KindHelper::execInPod(
            'php-metrics-cpu-test',
            'metrics-test',
            'cd /workspace && php -r \'$sum = 0; for ($i = 0; $i < 1000000; $i++) { $sum += $i; }\''
        );

        // Take post-load measurement
        $postLoad = json_decode(
            KindHelper::execInPod('php-metrics-cpu-test', 'metrics-test', "cd /workspace && php -r '$baselineCode'"),
            true
        );

        expect($postLoad['busy'])->toBeGreaterThan(
            $baseline['busy'],
            'CPU busy time should increase with load'
        );
    });

    it('validates per-core metrics in pod', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = Cbox\SystemMetrics\SystemMetrics::cpu();
if ($result->isSuccess()) {
    $cpu = $result->getValue();
    echo json_encode([
        'core_count' => count($cpu->perCore),
        'first_core_total' => $cpu->perCore[0]->times->total(),
    ]);
}
PHP;

        $output = KindHelper::execInPod('php-metrics-cpu-test', 'metrics-test', "cd /workspace && php -r '$code'");
        $data = json_decode($output, true);

        expect($data['core_count'])->toBeGreaterThan(0, 'Should have per-core data');
        expect($data['first_core_total'])->toBeGreaterThan(0, 'Core times should be positive');
    });

    it('checks pod CPU resource configuration', function () {
        $output = KindHelper::kubectl('get pod php-metrics-cpu-test -n metrics-test -o json');
        $pod = json_decode($output, true);

        $container = $pod['spec']['containers'][0];
        $resources = $container['resources'];

        // Verify resource limits are set correctly
        expect($resources['requests']['cpu'])->toBe('100m', 'CPU request should be 100m');
        expect($resources['limits']['cpu'])->toBe('500m', 'CPU limit should be 500m');
    });

    it('validates CPU time advances consistently', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = Cbox\SystemMetrics\SystemMetrics::cpu();
if ($result->isSuccess()) {
    echo json_encode([
        'total' => $result->getValue()->total->total(),
        'user' => $result->getValue()->total->user,
        'system' => $result->getValue()->total->system,
    ]);
}
PHP;

        $first = json_decode(
            KindHelper::execInPod('php-metrics-cpu-test', 'metrics-test', "cd /workspace && php -r '$code'"),
            true
        );

        sleep(1); // Wait 1 second

        $second = json_decode(
            KindHelper::execInPod('php-metrics-cpu-test', 'metrics-test', "cd /workspace && php -r '$code'"),
            true
        );

        expect($second['total'])->toBeGreaterThanOrEqual(
            $first['total'],
            'Total CPU time should advance'
        );
    });
});
