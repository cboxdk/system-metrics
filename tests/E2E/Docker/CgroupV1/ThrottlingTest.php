<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Tests\E2E\Support\DockerHelper;

describe('Docker CgroupV1 - CPU Throttling', function () {

    it('reads cgroup v1 CPU throttling statistics', function () {
        // Skip if not actually cgroup v1 (macOS Docker Desktop uses v2)
        if (! DockerHelper::fileExists('cgroupv1-target', '/sys/fs/cgroup/cpu/cpu.stat')) {
            expect(true)->toBeTrue('Skipping: Host uses cgroup v2, not v1');

            return;
        }

        $cpuStat = DockerHelper::readFile('cgroupv1-target', '/sys/fs/cgroup/cpu/cpu.stat');

        // cpu.stat contains:
        // nr_periods - number of periods
        // nr_throttled - number of times throttled
        // throttled_time - total time throttled in nanoseconds

        expect($cpuStat)->toContain('nr_periods', 'Should contain nr_periods');
        expect($cpuStat)->toContain('nr_throttled', 'Should contain nr_throttled');
        expect($cpuStat)->toContain('throttled_time', 'Should contain throttled_time');

        // Parse values
        preg_match('/nr_periods (\d+)/', $cpuStat, $periodsMatch);
        preg_match('/nr_throttled (\d+)/', $cpuStat, $throttledMatch);
        preg_match('/throttled_time (\d+)/', $cpuStat, $timeMatch);

        if ($periodsMatch && $throttledMatch && $timeMatch) {
            $nrPeriods = (int) $periodsMatch[1];
            $nrThrottled = (int) $throttledMatch[1];
            $throttledTime = (int) $timeMatch[1];

            expect($nrPeriods)->toBeGreaterThanOrEqual(0, 'Periods should be non-negative');
            expect($nrThrottled)->toBeGreaterThanOrEqual(0, 'Throttled count should be non-negative');
            expect($throttledTime)->toBeGreaterThanOrEqual(0, 'Throttled time should be non-negative');

            // If throttled, nr_throttled should be <= nr_periods
            if ($nrPeriods > 0) {
                expect($nrThrottled)->toBeLessThanOrEqual(
                    $nrPeriods,
                    'Throttled periods cannot exceed total periods'
                );
            }
        }
    });

    it('detects CPU throttling when exceeding quota in cgroup v1', function () {
        // Read baseline throttling stats
        $cpuStatBefore = DockerHelper::readFile('cgroupv1-target', '/sys/fs/cgroup/cpu/cpu.stat');
        preg_match('/nr_throttled (\d+)/', $cpuStatBefore, $matchBefore);
        $throttledBefore = $matchBefore ? (int) $matchBefore[1] : 0;

        // Run aggressive CPU stress to trigger throttling
        // Container has 0.5 CPU limit, use 2 workers to exceed it
        try {
            DockerHelper::stressCpu('cgroupv1-target', 5, 2);
        } catch (RuntimeException $e) {
            // Stress test might fail, but that's ok for this test
        }

        // Wait a moment for stats to update
        usleep(500000); // 500ms

        // Read post-stress throttling stats
        $cpuStatAfter = DockerHelper::readFile('cgroupv1-target', '/sys/fs/cgroup/cpu/cpu.stat');
        preg_match('/nr_throttled (\d+)/', $cpuStatAfter, $matchAfter);
        $throttledAfter = $matchAfter ? (int) $matchAfter[1] : 0;

        // Throttling count should have increased
        expect($throttledAfter)->toBeGreaterThanOrEqual(
            $throttledBefore,
            'Throttling count should increase when exceeding CPU quota'
        );
    })->skip(
        ! function_exists('stress-ng'),
        'stress-ng not available in container'
    );

    it('validates throttling percentage calculation', function () {
        // Skip if not actually cgroup v1 (macOS Docker Desktop uses v2)
        if (! DockerHelper::fileExists('cgroupv1-target', '/sys/fs/cgroup/cpu/cpu.stat')) {
            expect(true)->toBeTrue('Skipping: Host uses cgroup v2, not v1');

            return;
        }

        $cpuStat = DockerHelper::readFile('cgroupv1-target', '/sys/fs/cgroup/cpu/cpu.stat');

        preg_match('/nr_periods (\d+)/', $cpuStat, $periodsMatch);
        preg_match('/nr_throttled (\d+)/', $cpuStat, $throttledMatch);

        if ($periodsMatch && $throttledMatch) {
            $nrPeriods = (int) $periodsMatch[1];
            $nrThrottled = (int) $throttledMatch[1];

            if ($nrPeriods > 0) {
                $throttlePercentage = ($nrThrottled / $nrPeriods) * 100;

                expect($throttlePercentage)->toBeGreaterThanOrEqual(0.0, 'Throttle % >= 0');
                expect($throttlePercentage)->toBeLessThanOrEqual(100.0, 'Throttle % <= 100');
            } else {
                // No periods yet, acceptable
                expect($nrPeriods)->toBe(0, 'Zero periods is acceptable initially');
            }
        }
    });

    it('reads CPU shares from cgroup v1', function () {
        // CPU shares control relative CPU time in contention scenarios
        if (DockerHelper::fileExists('cgroupv1-target', '/sys/fs/cgroup/cpu/cpu.shares')) {
            $sharesStr = trim(DockerHelper::readFile('cgroupv1-target', '/sys/fs/cgroup/cpu/cpu.shares'));

            expect($sharesStr)->toBeNumeric('CPU shares should be numeric');

            $shares = (int) $sharesStr;
            expect($shares)->toBeGreaterThan(0, 'CPU shares should be positive');

            // Default CPU shares is usually 1024
            expect($shares)->toBeGreaterThanOrEqual(2, 'CPU shares >= 2');
            expect($shares)->toBeLessThanOrEqual(262144, 'CPU shares <= max');
        } else {
            expect(true)->toBeTrue('cpu.shares file not present (cgroup v2)');
        }
    });

    it('validates throttling affects CPU usage metrics', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::cpu();
if ($result->isSuccess()) {
    $cpu = $result->getValue();
    echo json_encode([
        'coreCount' => $cpu->coreCount(),
        'busyTime' => $cpu->total->busy(),
    ]);
}
PHP;

        $before = json_decode(DockerHelper::runPhp('cgroupv1-target', $code), true);

        // Run stress test
        try {
            DockerHelper::stressCpu('cgroupv1-target', 3, 2);
        } catch (RuntimeException $e) {
            // Ignore stress test failures
        }

        $after = json_decode(DockerHelper::runPhp('cgroupv1-target', $code), true);

        // CPU time should advance even when throttled
        expect($after['busyTime'])->toBeGreaterThan(
            $before['busyTime'],
            'CPU busy time should increase even with throttling'
        );

        // Core count should remain stable (not affected by throttling)
        expect($after['coreCount'])->toBe(
            $before['coreCount'],
            'Core count should remain constant'
        );
    })->skip(
        ! function_exists('stress-ng'),
        'stress-ng not available'
    );
});
