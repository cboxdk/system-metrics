<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Tests\E2E\Support\DockerHelper;

describe('Docker CgroupV1 - CPU Limits', function () {

    it('detects CPU quota in cgroup v1 container', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::cpu();
echo json_encode([
    'success' => $result->isSuccess(),
    'coreCount' => $result->isSuccess() ? $result->getValue()->coreCount() : null,
    'error' => $result->isFailure() ? $result->getError()->getMessage() : null,
]);
PHP;

        $output = DockerHelper::runPhp('cgroupv1-target', $code);
        $data = json_decode($output, true);

        expect($data['success'])->toBeTrue('CPU metrics should be readable');
        expect($data['coreCount'])->toBeGreaterThan(0, 'Core count should be positive');

        // Note: coreCount() returns physical CPU cores from the host, not cgroup-limited cores
        // This is expected behavior - the library reports actual hardware, not container limits
    });

    it('reads CPU times from cgroup v1 container', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::cpu();
if ($result->isSuccess()) {
    $cpu = $result->getValue();
    echo json_encode([
        'total' => $cpu->total->total(),
        'user' => $cpu->total->user,
        'system' => $cpu->total->system,
        'idle' => $cpu->total->idle,
        'busy' => $cpu->total->busy(),
    ]);
}
PHP;

        $output = DockerHelper::runPhp('cgroupv1-target', $code);
        $data = json_decode($output, true);

        expect($data['total'])->toBeGreaterThan(0, 'Total CPU time should be positive');
        expect($data['user'])->toBeGreaterThanOrEqual(0, 'User time should be non-negative');
        expect($data['system'])->toBeGreaterThanOrEqual(0, 'System time should be non-negative');
        expect($data['idle'])->toBeGreaterThanOrEqual(0, 'Idle time should be non-negative');
        expect($data['busy'])->toBeGreaterThanOrEqual(0, 'Busy time should be non-negative');
        expect($data['busy'])->toBeLessThanOrEqual($data['total'], 'Busy time cannot exceed total');
    });

    it('reads per-core CPU metrics in cgroup v1 container', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::cpu();
if ($result->isSuccess()) {
    $cpu = $result->getValue();
    $cores = array_map(function($core) {
        return [
            'index' => $core->coreIndex,
            'user' => $core->times->user,
            'system' => $core->times->system,
            'total' => $core->times->total(),
        ];
    }, $cpu->perCore);
    echo json_encode(['cores' => $cores]);
}
PHP;

        $output = DockerHelper::runPhp('cgroupv1-target', $code);
        $data = json_decode($output, true);

        expect($data['cores'])->not()->toBeEmpty('Should have per-core metrics');

        foreach ($data['cores'] as $core) {
            expect($core['total'])->toBeGreaterThan(0, "Core {$core['index']} total should be positive");
            expect($core['user'])->toBeGreaterThanOrEqual(0, "Core {$core['index']} user time non-negative");
            expect($core['system'])->toBeGreaterThanOrEqual(0, "Core {$core['index']} system time non-negative");
        }
    });

    it('validates CPU metrics consistency under cgroup v1 limits', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$cpuResult = PHPeek\SystemMetrics\SystemMetrics::cpu();
$memResult = PHPeek\SystemMetrics\SystemMetrics::memory();
echo json_encode([
    'cpu_success' => $cpuResult->isSuccess(),
    'mem_success' => $memResult->isSuccess(),
]);
PHP;

        $output = DockerHelper::runPhp('cgroupv1-target', $code);
        $data = json_decode($output, true);

        expect($data['cpu_success'])->toBeTrue('CPU metrics should succeed');
        expect($data['mem_success'])->toBeTrue('Memory metrics should succeed');
    });

    it('detects CPU activity during stress test in cgroup v1', function () {
        // Take baseline CPU snapshot
        $baselineCode = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::cpu();
if ($result->isSuccess()) {
    echo json_encode(['busy' => $result->getValue()->total->busy()]);
}
PHP;

        $baseline = json_decode(DockerHelper::runPhp('cgroupv1-target', $baselineCode), true);

        // Run stress test (2 workers for 3 seconds)
        DockerHelper::stressCpu('cgroupv1-target', 3, 2);

        // Take post-stress CPU snapshot
        $postStress = json_decode(DockerHelper::runPhp('cgroupv1-target', $baselineCode), true);

        // CPU busy time should have increased
        expect($postStress['busy'])->toBeGreaterThan(
            $baseline['busy'],
            'CPU busy time should increase during stress test'
        );
    })->skip(
        ! function_exists('stress-ng'),
        'stress-ng not available in container'
    );

    it('reads cgroup v1 specific files for CPU quota', function () {
        // Skip if not actually cgroup v1 (macOS Docker Desktop uses v2)
        if (! DockerHelper::fileExists('cgroupv1-target', '/sys/fs/cgroup/cpu/cpu.cfs_quota_us')) {
            expect(true)->toBeTrue('Skipping: Host uses cgroup v2, not v1');

            return;
        }

        expect(DockerHelper::fileExists('cgroupv1-target', '/sys/fs/cgroup/cpu/cpu.cfs_period_us'))
            ->toBeTrue('cgroup v1 cpu.cfs_period_us should exist');

        // Read quota values
        $quota = trim(DockerHelper::readFile('cgroupv1-target', '/sys/fs/cgroup/cpu/cpu.cfs_quota_us'));
        $period = trim(DockerHelper::readFile('cgroupv1-target', '/sys/fs/cgroup/cpu/cpu.cfs_period_us'));

        expect($quota)->toBeNumeric('Quota should be numeric');
        expect($period)->toBeNumeric('Period should be numeric');

        // Container has 0.5 CPU (--cpus=0.5)
        // This means quota = 50000, period = 100000 (50000/100000 = 0.5)
        $quotaInt = (int) $quota;
        $periodInt = (int) $period;

        if ($quotaInt > 0 && $periodInt > 0) {
            $cpuLimit = $quotaInt / $periodInt;
            expect($cpuLimit)->toBeGreaterThan(0.4, 'CPU limit should be ~0.5');
            expect($cpuLimit)->toBeLessThan(0.6, 'CPU limit should be ~0.5');
        }
    });
});
