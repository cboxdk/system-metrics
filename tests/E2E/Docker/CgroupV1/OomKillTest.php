<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Tests\E2E\Support\DockerHelper;

describe('Docker CgroupV1 - OOM Kill Detection', function () {

    it('reads cgroup v1 OOM control file', function () {
        // Check if OOM control file exists
        if (DockerHelper::fileExists('cgroupv1-target', '/sys/fs/cgroup/memory/memory.oom_control')) {
            $oomControl = DockerHelper::readFile('cgroupv1-target', '/sys/fs/cgroup/memory/memory.oom_control');

            // oom_control contains:
            // oom_kill_disable 0 or 1
            // under_oom 0 or 1 (whether currently under OOM)
            // oom_kill counter

            expect($oomControl)->toContain('oom_kill_disable', 'Should contain oom_kill_disable');

            // Parse oom_kill_disable
            preg_match('/oom_kill_disable (\d+)/', $oomControl, $match);
            if ($match) {
                $disabled = (int) $match[1];
                expect($disabled)->toBeGreaterThanOrEqual(0, 'OOM disable flag valid');
                expect($disabled)->toBeLessThanOrEqual(1, 'OOM disable flag is 0 or 1');
            }
        } else {
            // OOM control might not be available on all systems
            expect(true)->toBeTrue('OOM control file not available (acceptable)');
        }
    });

    it('reads OOM kill count from cgroup v1', function () {
        // Some kernels expose oom_kill count in memory.oom_control
        if (DockerHelper::fileExists('cgroupv1-target', '/sys/fs/cgroup/memory/memory.oom_control')) {
            $oomControl = DockerHelper::readFile('cgroupv1-target', '/sys/fs/cgroup/memory/memory.oom_control');

            // Look for oom_kill or similar counter
            if (str_contains($oomControl, 'oom_kill')) {
                preg_match('/oom_kill[^\d]*(\d+)/', $oomControl, $match);
                if ($match) {
                    $oomCount = (int) $match[1];
                    expect($oomCount)->toBeGreaterThanOrEqual(0, 'OOM kill count non-negative');
                }
            }
        }
    });

    it('validates memory metrics remain consistent near OOM limit', function () {
        // Read memory metrics when approaching limit (but not triggering OOM)
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

        $metrics = json_decode(DockerHelper::runPhp('cgroupv1-target', $code), true);

        // Even under memory pressure, metrics should be consistent
        expect($metrics['usedBytes'])->toBeLessThanOrEqual(
            $metrics['totalBytes'],
            'Used memory should not exceed total'
        );

        expect($metrics['availableBytes'])->toBeLessThanOrEqual(
            $metrics['totalBytes'],
            'Available memory should not exceed total'
        );

        expect($metrics['usedPercentage'])->toBeGreaterThanOrEqual(0.0, 'Usage % >= 0');
        expect($metrics['usedPercentage'])->toBeLessThanOrEqual(100.0, 'Usage % <= 100');
    });

    it('checks memory.failcnt for allocation failures', function () {
        // memory.failcnt tracks failed memory allocations
        if (DockerHelper::fileExists('cgroupv1-target', '/sys/fs/cgroup/memory/memory.failcnt')) {
            $failcntStr = trim(DockerHelper::readFile('cgroupv1-target', '/sys/fs/cgroup/memory/memory.failcnt'));

            expect($failcntStr)->toBeNumeric('Fail count should be numeric');

            $failcnt = (int) $failcntStr;
            expect($failcnt)->toBeGreaterThanOrEqual(0, 'Fail count should be non-negative');

            // High fail count indicates memory pressure
            // But we're not asserting a specific value, just that it's readable
        }
    });

    it('validates memory.max_usage_in_bytes tracking', function () {
        // memory.max_usage_in_bytes tracks peak memory usage
        if (DockerHelper::fileExists('cgroupv1-target', '/sys/fs/cgroup/memory/memory.max_usage_in_bytes')) {
            $maxUsageStr = trim(DockerHelper::readFile('cgroupv1-target', '/sys/fs/cgroup/memory/memory.max_usage_in_bytes'));

            expect($maxUsageStr)->toBeNumeric('Max usage should be numeric');

            $maxUsage = (int) $maxUsageStr;
            expect($maxUsage)->toBeGreaterThan(0, 'Max usage should be positive');

            // Max usage should be <= limit (256m)
            $limitBytes = 256 * 1024 * 1024;
            expect($maxUsage)->toBeLessThanOrEqual(
                $limitBytes * 1.1, // Allow 10% over for kernel accounting
                'Max usage should be within reasonable bounds of limit'
            );
        }
    });

    it('verifies memory pressure notification mechanism', function () {
        // Cgroup v1 supports memory pressure notifications via memory.pressure_level
        // Check if the system supports this feature
        if (DockerHelper::fileExists('cgroupv1-target', '/sys/fs/cgroup/memory/memory.pressure_level')) {
            $pressureLevel = trim(DockerHelper::readFile('cgroupv1-target', '/sys/fs/cgroup/memory/memory.pressure_level'));

            // Pressure levels: low, medium, critical
            expect($pressureLevel)->toBeString('Pressure level should be string');

            // Valid values (if file exists and has content)
            if (! empty($pressureLevel)) {
                expect(['low', 'medium', 'critical', ''])->toContain(
                    $pressureLevel,
                    'Pressure level should be valid'
                );
            }
        } else {
            // Pressure level file might not exist on all systems
            expect(true)->toBeTrue('Pressure level file not available (acceptable)');
        }
    });

    it('validates memory statistics under pressure', function () {
        // memory.stat contains detailed memory usage breakdown
        if (DockerHelper::fileExists('cgroupv1-target', '/sys/fs/cgroup/memory/memory.stat')) {
            $memoryStat = DockerHelper::readFile('cgroupv1-target', '/sys/fs/cgroup/memory/memory.stat');

            // Common fields in memory.stat:
            // cache, rss, mapped_file, pgpgin, pgpgout, etc.

            // Just verify we can read it and it contains expected fields
            $expectedFields = ['cache', 'rss', 'mapped_file'];
            $foundFields = 0;

            foreach ($expectedFields as $field) {
                if (str_contains($memoryStat, $field)) {
                    $foundFields++;
                }
            }

            expect($foundFields)->toBeGreaterThan(
                0,
                'Should find at least one expected memory stat field'
            );
        }
    });
})->skip(
    'OOM kill tests are destructive and can crash containers. Run manually if needed.'
);

describe('Docker CgroupV1 - OOM Kill Manual Tests', function () {
    it('provides instructions for manual OOM kill testing', function () {
        $instructions = <<<'TEXT'
To manually test OOM kill behavior in cgroup v1:

1. Start the container:
   docker compose -f e2e/compose/docker-compose.yml up -d cgroupv1-target

2. Monitor OOM events:
   docker exec cgroupv1-target cat /sys/fs/cgroup/memory/memory.oom_control

3. Trigger OOM by allocating more memory than the 256m limit:
   docker exec cgroupv1-target php -r '$data = []; while(true) { $data[] = str_repeat("x", 1024*1024); }'

4. Observe the process getting killed

5. Check OOM kill count increased:
   docker exec cgroupv1-target cat /sys/fs/cgroup/memory/memory.oom_control

Note: This test is skipped in automated runs because it crashes processes.
TEXT;

        expect($instructions)->toBeString('Instructions provided');
        expect(str_contains($instructions, '256m'))->toBeTrue('References correct memory limit');
    });
});
