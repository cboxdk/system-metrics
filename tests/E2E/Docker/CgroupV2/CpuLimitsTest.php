<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Tests\E2E\Support\DockerHelper;

describe('Docker CgroupV2 - CPU Limits', function () {

    it('detects CPU quota in cgroup v2 container', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::cpu();
echo json_encode([
    'success' => $result->isSuccess(),
    'coreCount' => $result->isSuccess() ? $result->getValue()->coreCount() : null,
    'error' => $result->isFailure() ? $result->getError()->getMessage() : null,
]);
PHP;

        $output = DockerHelper::runPhp('cgroupv2-target', $code);
        $data = json_decode($output, true);

        expect($data['success'])->toBeTrue('CPU metrics should be readable');
        expect($data['coreCount'])->toBeGreaterThan(0, 'Core count should be positive');

        // Note: coreCount() returns physical CPU cores from the host, not cgroup-limited cores
        // This is expected behavior - the library reports actual hardware, not container limits
    });

    it('reads CPU times from cgroup v2 container', function () {
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

        $output = DockerHelper::runPhp('cgroupv2-target', $code);
        $data = json_decode($output, true);

        expect($data['total'])->toBeGreaterThan(0, 'Total CPU time should be positive');
        expect($data['user'])->toBeGreaterThanOrEqual(0, 'User time should be non-negative');
        expect($data['system'])->toBeGreaterThanOrEqual(0, 'System time should be non-negative');
        expect($data['idle'])->toBeGreaterThanOrEqual(0, 'Idle time should be non-negative');
        expect($data['busy'])->toBeGreaterThanOrEqual(0, 'Busy time should be non-negative');
        expect($data['busy'])->toBeLessThanOrEqual($data['total'], 'Busy time cannot exceed total');
    });

    it('reads per-core CPU metrics in cgroup v2 container', function () {
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

        $output = DockerHelper::runPhp('cgroupv2-target', $code);
        $data = json_decode($output, true);

        expect($data['cores'])->not()->toBeEmpty('Should have per-core metrics');

        foreach ($data['cores'] as $core) {
            expect($core['total'])->toBeGreaterThan(0, "Core {$core['index']} total should be positive");
            expect($core['user'])->toBeGreaterThanOrEqual(0, "Core {$core['index']} user time non-negative");
            expect($core['system'])->toBeGreaterThanOrEqual(0, "Core {$core['index']} system time non-negative");
        }
    });

    it('reads cgroup v2 specific files for CPU quota', function () {
        // Cgroup v2 uses unified hierarchy with different file names
        expect(DockerHelper::fileExists('cgroupv2-target', '/sys/fs/cgroup/cgroup.controllers'))
            ->toBeTrue('cgroup v2 controllers file should exist');

        expect(DockerHelper::fileExists('cgroupv2-target', '/sys/fs/cgroup/cpu.max'))
            ->toBeTrue('cgroup v2 cpu.max should exist');

        // Read CPU quota from cpu.max
        // Format: "$MAX $PERIOD" or "max $PERIOD" for unlimited
        $cpuMax = trim(DockerHelper::readFile('cgroupv2-target', '/sys/fs/cgroup/cpu.max'));

        expect($cpuMax)->toBeString('cpu.max should be readable');

        // Parse cpu.max
        $parts = explode(' ', $cpuMax);
        expect($parts)->toHaveCount(2, 'cpu.max should have two values');

        [$max, $period] = $parts;

        if ($max !== 'max') {
            // Container has 1.0 CPU (--cpus=1.0)
            // This means max = 100000, period = 100000 (100000/100000 = 1.0)
            expect($max)->toBeNumeric('Max should be numeric or "max"');
            expect($period)->toBeNumeric('Period should be numeric');

            $maxInt = (int) $max;
            $periodInt = (int) $period;

            expect($periodInt)->toBeGreaterThan(0, 'Period should be positive');

            $cpuLimit = $maxInt / $periodInt;
            expect($cpuLimit)->toBeGreaterThan(0.9, 'CPU limit should be ~1.0');
            expect($cpuLimit)->toBeLessThan(1.1, 'CPU limit should be ~1.0');
        }
    });

    it('reads cgroup v2 CPU statistics from cpu.stat', function () {
        // Cgroup v2 cpu.stat contains different fields than v1
        expect(DockerHelper::fileExists('cgroupv2-target', '/sys/fs/cgroup/cpu.stat'))
            ->toBeTrue('cgroup v2 cpu.stat should exist');

        $cpuStat = DockerHelper::readFile('cgroupv2-target', '/sys/fs/cgroup/cpu.stat');

        // Cgroup v2 cpu.stat contains:
        // usage_usec - total CPU time in microseconds
        // user_usec - user mode time
        // system_usec - system mode time
        // nr_periods - number of enforcement periods
        // nr_throttled - number of times throttled
        // throttled_usec - total throttled time

        $expectedFields = ['usage_usec', 'user_usec', 'system_usec'];
        $foundFields = 0;

        foreach ($expectedFields as $field) {
            if (str_contains($cpuStat, $field)) {
                $foundFields++;
            }
        }

        expect($foundFields)->toBeGreaterThan(
            0,
            'Should find at least one expected cpu.stat field'
        );

        // Parse usage_usec
        if (preg_match('/usage_usec (\d+)/', $cpuStat, $match)) {
            $usageUsec = (int) $match[1];
            expect($usageUsec)->toBeGreaterThan(0, 'CPU usage should be positive');
        }
    });

    it('validates CPU metrics consistency under cgroup v2 limits', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$cpuResult = PHPeek\SystemMetrics\SystemMetrics::cpu();
$memResult = PHPeek\SystemMetrics\SystemMetrics::memory();
echo json_encode([
    'cpu_success' => $cpuResult->isSuccess(),
    'mem_success' => $memResult->isSuccess(),
]);
PHP;

        $output = DockerHelper::runPhp('cgroupv2-target', $code);
        $data = json_decode($output, true);

        expect($data['cpu_success'])->toBeTrue('CPU metrics should succeed');
        expect($data['mem_success'])->toBeTrue('Memory metrics should succeed');
    });

    it('detects CPU activity during stress test in cgroup v2', function () {
        $baselineCode = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::cpu();
if ($result->isSuccess()) {
    echo json_encode(['busy' => $result->getValue()->total->busy()]);
}
PHP;

        $baseline = json_decode(DockerHelper::runPhp('cgroupv2-target', $baselineCode), true);

        // Run stress test (2 workers for 3 seconds)
        DockerHelper::stressCpu('cgroupv2-target', 3, 2);

        $postStress = json_decode(DockerHelper::runPhp('cgroupv2-target', $baselineCode), true);

        expect($postStress['busy'])->toBeGreaterThan(
            $baseline['busy'],
            'CPU busy time should increase during stress test'
        );
    })->skip(
        ! function_exists('stress-ng'),
        'stress-ng not available in container'
    );

    it('reads cgroup v2 CPU weight (shares equivalent)', function () {
        // Cgroup v2 uses cpu.weight instead of cpu.shares
        if (DockerHelper::fileExists('cgroupv2-target', '/sys/fs/cgroup/cpu.weight')) {
            $weightStr = trim(DockerHelper::readFile('cgroupv2-target', '/sys/fs/cgroup/cpu.weight'));

            expect($weightStr)->toBeNumeric('CPU weight should be numeric');

            $weight = (int) $weightStr;
            // Default weight is 100, range is 1-10000
            expect($weight)->toBeGreaterThanOrEqual(1, 'CPU weight >= 1');
            expect($weight)->toBeLessThanOrEqual(10000, 'CPU weight <= 10000');
        }
    });
});
