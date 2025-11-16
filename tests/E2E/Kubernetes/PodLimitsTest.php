<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Tests\E2E\Support\KindHelper;

describe('Kubernetes - Pod Resource Limits', function () {

    it('detects CPU limits in Kubernetes pod', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::cpu();
echo json_encode([
    'success' => $result->isSuccess(),
    'coreCount' => $result->isSuccess() ? $result->getValue()->coreCount() : null,
    'error' => $result->isFailure() ? $result->getError()->getMessage() : null,
]);
PHP;

        $output = KindHelper::execInPod('php-metrics-cpu-test', 'metrics-test', "cd /workspace && php -r '$code'");
        $data = json_decode($output, true);

        expect($data['success'])->toBeTrue('CPU metrics should be readable in pod');
        expect($data['coreCount'])->toBeGreaterThan(0, 'Core count should be positive');

        // Pod has 500m CPU limit (0.5 cores)
        $expectedCores = 0.5;
        $tolerance = 0.1; // ±10%

        expect($data['coreCount'])->toBeGreaterThanOrEqual(
            $expectedCores * (1 - $tolerance),
            'CPU cores should be ~0.5'
        );
        expect($data['coreCount'])->toBeLessThanOrEqual(
            $expectedCores * (1 + $tolerance),
            'CPU cores should be ~0.5'
        );
    });

    it('detects memory limits in Kubernetes pod', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::memory();
echo json_encode([
    'success' => $result->isSuccess(),
    'totalBytes' => $result->isSuccess() ? $result->getValue()->totalBytes : null,
    'error' => $result->isFailure() ? $result->getError()->getMessage() : null,
]);
PHP;

        $output = KindHelper::execInPod('php-metrics-memory-test', 'metrics-test', "cd /workspace && php -r '$code'");
        $data = json_decode($output, true);

        expect($data['success'])->toBeTrue('Memory metrics should be readable in pod');
        expect($data['totalBytes'])->toBeGreaterThan(0, 'Total memory should be positive');

        // Pod has 256Mi memory limit
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

    it('validates environment detection in Kubernetes pod', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::environment();
if ($result->isSuccess()) {
    $env = $result->getValue();
    echo json_encode([
        'insideContainer' => $env->containerization->insideContainer,
        'containerType' => $env->containerization->type->value,
        'osFamily' => $env->os->family->value,
    ]);
}
PHP;

        $output = KindHelper::execInPod('php-metrics-cpu-test', 'metrics-test', "cd /workspace && php -r '$code'");
        $data = json_decode($output, true);

        expect($data['insideContainer'])->toBeTrue('Should detect container environment');
        expect($data['containerType'])->toBe('docker', 'Kind uses Docker containers');
        expect($data['osFamily'])->toBe('linux', 'Pod should run on Linux');
    });

    it('reads complete system overview from pod', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::overview();
if ($result->isSuccess()) {
    $overview = $result->getValue();
    echo json_encode([
        'cpu_cores' => $overview->cpu->coreCount(),
        'memory_total' => $overview->memory->totalBytes,
        'os_name' => $overview->environment->os->name,
        'container' => $overview->environment->containerization->insideContainer,
    ]);
}
PHP;

        $output = KindHelper::execInPod('php-metrics-cpu-test', 'metrics-test', "cd /workspace && php -r '$code'");
        $data = json_decode($output, true);

        expect($data['cpu_cores'])->toBeGreaterThan(0, 'CPU cores detected');
        expect($data['memory_total'])->toBeGreaterThan(0, 'Memory detected');
        expect($data['container'])->toBeTrue('Container detected');
    });

    it('validates pod has correct cgroup version', function () {
        // Check which cgroup version the pod is using
        $output = KindHelper::execInPod(
            'php-metrics-cpu-test',
            'metrics-test',
            'test -f /sys/fs/cgroup/cgroup.controllers && echo "v2" || echo "v1"'
        );

        $cgroupVersion = trim($output);

        expect(['v1', 'v2'])->toContain($cgroupVersion, 'Should detect valid cgroup version');
    });

    it('reads pod logs for metric outputs', function () {
        $logs = KindHelper::getPodLogs('php-metrics-cpu-test', 'metrics-test');

        // Logs should contain the SystemMetrics output from pod startup
        expect($logs)->toContain('object(PHPeek\SystemMetrics\DTO\Metrics', 'Logs should show metrics output');
    });

    it('validates pod CPU and memory metrics consistency', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$cpuResult = PHPeek\SystemMetrics\SystemMetrics::cpu();
$memResult = PHPeek\SystemMetrics\SystemMetrics::memory();

if ($cpuResult->isSuccess() && $memResult->isSuccess()) {
    $cpu = $cpuResult->getValue();
    $mem = $memResult->getValue();

    echo json_encode([
        'cpu_total' => $cpu->total->total(),
        'cpu_busy' => $cpu->total->busy(),
        'mem_used' => $mem->usedBytes,
        'mem_total' => $mem->totalBytes,
        'consistent' => $mem->usedBytes <= $mem->totalBytes && $cpu->total->busy() <= $cpu->total->total(),
    ]);
}
PHP;

        $output = KindHelper::execInPod('php-metrics-cpu-test', 'metrics-test', "cd /workspace && php -r '$code'");
        $data = json_decode($output, true);

        expect($data['consistent'])->toBeTrue('Metrics should be internally consistent');
        expect($data['cpu_busy'])->toBeLessThanOrEqual($data['cpu_total'], 'Busy <= Total CPU');
        expect($data['mem_used'])->toBeLessThanOrEqual($data['mem_total'], 'Used <= Total memory');
    });
});
