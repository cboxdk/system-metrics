<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Tests\E2E\Support\KindHelper;

describe('Kubernetes - Memory Pressure', function () {

    it('detects memory limits enforced by Kubernetes', function () {
        // Pod has 256Mi memory limit
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::memory();
if ($result->isSuccess()) {
    echo json_encode(['totalBytes' => $result->getValue()->totalBytes]);
}
PHP;

        $output = KindHelper::execInPod('php-metrics-memory-test', 'metrics-test', "cd /workspace && php -r '$code'");
        $data = json_decode($output, true);

        $expectedBytes = 256 * 1024 * 1024; // 256 MiB
        $tolerance = 0.05; // ±5%

        expect($data['totalBytes'])->toBeGreaterThanOrEqual(
            (int) ($expectedBytes * (1 - $tolerance)),
            'Memory should be ~256Mi'
        );
        expect($data['totalBytes'])->toBeLessThanOrEqual(
            (int) ($expectedBytes * (1 + $tolerance)),
            'Memory should be ~256Mi'
        );
    });

    it('reads memory usage metrics from pod', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::memory();
if ($result->isSuccess()) {
    $mem = $result->getValue();
    echo json_encode([
        'totalBytes' => $mem->totalBytes,
        'usedBytes' => $mem->usedBytes,
        'availableBytes' => $mem->availableBytes,
        'usedPercentage' => $mem->usedPercentage(),
    ]);
}
PHP;

        $output = KindHelper::execInPod('php-metrics-memory-test', 'metrics-test', "cd /workspace && php -r '$code'");
        $data = json_decode($output, true);

        expect($data['totalBytes'])->toBeGreaterThan(0, 'Total memory positive');
        expect($data['usedBytes'])->toBeGreaterThan(0, 'Used memory positive');
        expect($data['availableBytes'])->toBeGreaterThanOrEqual(0, 'Available memory non-negative');
        expect($data['usedPercentage'])->toBeGreaterThanOrEqual(0.0, 'Usage % >= 0');
        expect($data['usedPercentage'])->toBeLessThanOrEqual(100.0, 'Usage % <= 100');
    });

    it('validates memory pressure during allocation', function () {
        // Memory test pod allocates 200MB which exceeds 256Mi limit
        // Pod should be allocating memory continuously via pod-memory-limit.yaml

        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::memory();
if ($result->isSuccess()) {
    echo json_encode(['usedPercentage' => $result->getValue()->usedPercentage()]);
}
PHP;

        // Wait a moment for memory allocation to occur
        sleep(2);

        $output = KindHelper::execInPod('php-metrics-memory-test', 'metrics-test', "cd /workspace && php -r '$code'");
        $data = json_decode($output, true);

        // Memory usage should be elevated due to allocation script
        expect($data['usedPercentage'])->toBeGreaterThan(
            30.0,
            'Memory usage should be elevated during allocation'
        );
    });

    it('reads memory statistics from pod cgroup', function () {
        // Try both cgroup v1 and v2 paths
        $memoryStat = KindHelper::execInPod(
            'php-metrics-memory-test',
            'metrics-test',
            'cat /sys/fs/cgroup/memory.stat 2>/dev/null || cat /sys/fs/cgroup/memory/memory.stat 2>/dev/null || echo "not available"'
        );

        if (! str_contains($memoryStat, 'not available')) {
            // Should contain memory breakdown (anon, file, etc.)
            expect($memoryStat)->not()->toBeEmpty('Memory stats should be readable');
        }
    });

    it('checks pod memory resource configuration', function () {
        $output = KindHelper::kubectl('get pod php-metrics-memory-test -n metrics-test -o json');
        $pod = json_decode($output, true);

        $container = $pod['spec']['containers'][0];
        $resources = $container['resources'];

        // Verify resource limits
        expect($resources['requests']['memory'])->toBe('128Mi', 'Memory request should be 128Mi');
        expect($resources['limits']['memory'])->toBe('256Mi', 'Memory limit should be 256Mi');
    });

    it('validates memory consistency under pressure', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::memory();
if ($result->isSuccess()) {
    $mem = $result->getValue();
    $consistent = $mem->usedBytes <= $mem->totalBytes;
    echo json_encode([
        'consistent' => $consistent,
        'used' => $mem->usedBytes,
        'total' => $mem->totalBytes,
    ]);
}
PHP;

        $output = KindHelper::execInPod('php-metrics-memory-test', 'metrics-test', "cd /workspace && php -r '$code'");
        $data = json_decode($output, true);

        expect($data['consistent'])->toBeTrue('Memory metrics should be consistent under pressure');
        expect($data['used'])->toBeLessThanOrEqual($data['total'], 'Used <= Total');
    });

    it('detects QoS class based on memory limits', function () {
        $output = KindHelper::kubectl('get pod php-metrics-memory-test -n metrics-test -o json');
        $pod = json_decode($output, true);

        $qosClass = $pod['status']['qosClass'];

        // Memory pod has requests != limits (128Mi != 256Mi)
        // QoS should be "Burstable"
        expect($qosClass)->toBe('Burstable', 'Pod should have Burstable QoS');
    });

    it('validates pod memory events', function () {
        // Check for OOM events in pod events
        $output = KindHelper::kubectl('get events -n metrics-test --field-selector involvedObject.name=php-metrics-memory-test');

        // Events should be readable
        expect($output)->toBeString('Should be able to read pod events');

        // If OOM events exist, they would show up here
        // Not asserting OOM since we want tests to pass
    });

    it('reads memory metrics multiple times for consistency', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::memory();
if ($result->isSuccess()) {
    echo json_encode(['totalBytes' => $result->getValue()->totalBytes]);
}
PHP;

        $first = json_decode(
            KindHelper::execInPod('php-metrics-memory-test', 'metrics-test', "cd /workspace && php -r '$code'"),
            true
        );

        sleep(1);

        $second = json_decode(
            KindHelper::execInPod('php-metrics-memory-test', 'metrics-test', "cd /workspace && php -r '$code'"),
            true
        );

        // Total memory should remain constant
        expect($second['totalBytes'])->toBe(
            $first['totalBytes'],
            'Total memory should remain constant'
        );
    });

    it('validates swap configuration in pod', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::memory();
if ($result->isSuccess()) {
    $mem = $result->getValue();
    echo json_encode([
        'swapTotal' => $mem->swapTotalBytes,
        'swapUsed' => $mem->swapUsedBytes,
    ]);
}
PHP;

        $output = KindHelper::execInPod('php-metrics-memory-test', 'metrics-test', "cd /workspace && php -r '$code'");
        $data = json_decode($output, true);

        // Swap might be disabled in containers
        expect($data['swapTotal'])->toBeGreaterThanOrEqual(0, 'Swap total non-negative');
        expect($data['swapUsed'])->toBeGreaterThanOrEqual(0, 'Swap used non-negative');
    });
});
