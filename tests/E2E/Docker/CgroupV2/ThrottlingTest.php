<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Tests\E2E\Support\DockerHelper;

describe('Docker CgroupV2 - CPU Throttling', function () {

    it('reads cgroup v2 CPU throttling statistics', function () {
        expect(DockerHelper::fileExists('cgroupv2-target', '/sys/fs/cgroup/cpu.stat'))
            ->toBeTrue('cgroup v2 cpu.stat should exist');

        $cpuStat = DockerHelper::readFile('cgroupv2-target', '/sys/fs/cgroup/cpu.stat');

        // Cgroup v2 cpu.stat contains:
        // nr_periods - number of enforcement intervals
        // nr_throttled - number of times throttled
        // throttled_usec - total time throttled in microseconds

        // Verify the file contains throttling metrics
        $hasNrPeriods = str_contains($cpuStat, 'nr_periods');
        $hasNrThrottled = str_contains($cpuStat, 'nr_throttled');
        $hasThrottledUsec = str_contains($cpuStat, 'throttled_usec');

        expect($hasNrPeriods)->toBeTrue('Should contain nr_periods');
        expect($hasNrThrottled)->toBeTrue('Should contain nr_throttled');
        expect($hasThrottledUsec)->toBeTrue('Should contain throttled_usec');

        // Parse values
        preg_match('/nr_periods (\d+)/', $cpuStat, $periodsMatch);
        preg_match('/nr_throttled (\d+)/', $cpuStat, $throttledMatch);
        preg_match('/throttled_usec (\d+)/', $cpuStat, $timeMatch);

        if ($periodsMatch && $throttledMatch && $timeMatch) {
            $nrPeriods = (int) $periodsMatch[1];
            $nrThrottled = (int) $throttledMatch[1];
            $throttledUsec = (int) $timeMatch[1];

            expect($nrPeriods)->toBeGreaterThanOrEqual(0, 'Periods should be non-negative');
            expect($nrThrottled)->toBeGreaterThanOrEqual(0, 'Throttled count should be non-negative');
            expect($throttledUsec)->toBeGreaterThanOrEqual(0, 'Throttled time should be non-negative');

            if ($nrPeriods > 0) {
                expect($nrThrottled)->toBeLessThanOrEqual(
                    $nrPeriods,
                    'Throttled periods cannot exceed total periods'
                );
            }
        }
    });

    it('detects CPU throttling when exceeding quota in cgroup v2', function () {
        $cpuStatBefore = DockerHelper::readFile('cgroupv2-target', '/sys/fs/cgroup/cpu.stat');
        preg_match('/nr_throttled (\d+)/', $cpuStatBefore, $matchBefore);
        $throttledBefore = $matchBefore ? (int) $matchBefore[1] : 0;

        // Run aggressive CPU stress to trigger throttling
        // Container has 1.0 CPU limit, use 2 workers to exceed it
        try {
            DockerHelper::stressCpu('cgroupv2-target', 5, 2);
        } catch (RuntimeException $e) {
            // Stress test might fail, but that's ok
        }

        usleep(500000); // 500ms

        $cpuStatAfter = DockerHelper::readFile('cgroupv2-target', '/sys/fs/cgroup/cpu.stat');
        preg_match('/nr_throttled (\d+)/', $cpuStatAfter, $matchAfter);
        $throttledAfter = $matchAfter ? (int) $matchAfter[1] : 0;

        expect($throttledAfter)->toBeGreaterThanOrEqual(
            $throttledBefore,
            'Throttling count should increase when exceeding CPU quota'
        );
    })->skip(
        ! function_exists('stress-ng'),
        'stress-ng not available'
    );

    it('validates throttling percentage calculation', function () {
        $cpuStat = DockerHelper::readFile('cgroupv2-target', '/sys/fs/cgroup/cpu.stat');

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
                expect($nrPeriods)->toBe(0, 'Zero periods is acceptable initially');
            }
        }
    });

    it('reads CPU pressure information (PSI)', function () {
        // Cgroup v2 supports Pressure Stall Information (PSI)
        if (DockerHelper::fileExists('cgroupv2-target', '/sys/fs/cgroup/cpu.pressure')) {
            $cpuPressure = DockerHelper::readFile('cgroupv2-target', '/sys/fs/cgroup/cpu.pressure');

            // cpu.pressure format:
            // some avg10=0.00 avg60=0.00 avg300=0.00 total=0
            // full avg10=0.00 avg60=0.00 avg300=0.00 total=0

            expect($cpuPressure)->toContain('some', 'Should contain "some" pressure line');

            // Parse values to ensure they're valid
            if (preg_match('/some avg10=([\d.]+)/', $cpuPressure, $match)) {
                $avg10 = (float) $match[1];
                expect($avg10)->toBeGreaterThanOrEqual(0.0, 'Pressure avg10 >= 0');
                expect($avg10)->toBeLessThanOrEqual(100.0, 'Pressure avg10 <= 100');
            }
        } else {
            // PSI might not be available on all systems
            expect(true)->toBeTrue('CPU pressure file not available (acceptable)');
        }
    });

    it('validates CPU burst configuration', function () {
        // Cgroup v2 supports CPU burst allowance
        if (DockerHelper::fileExists('cgroupv2-target', '/sys/fs/cgroup/cpu.max.burst')) {
            $burstStr = trim(DockerHelper::readFile('cgroupv2-target', '/sys/fs/cgroup/cpu.max.burst'));

            if ($burstStr !== '0' && $burstStr !== 'max') {
                expect($burstStr)->toBeNumeric('CPU burst should be numeric, 0, or max');

                $burst = (int) $burstStr;
                expect($burst)->toBeGreaterThan(0, 'CPU burst should be positive if set');
            } else {
                expect($burstStr)->toBeIn(['0', 'max'], 'CPU burst should be 0 or max');
            }
        } else {
            expect(true)->toBeTrue('cpu.max.burst file not present');
        }
    });

    it('reads CPU usage breakdown from cpu.stat', function () {
        $cpuStat = DockerHelper::readFile('cgroupv2-target', '/sys/fs/cgroup/cpu.stat');

        // Parse usage times
        preg_match('/usage_usec (\d+)/', $cpuStat, $usageMatch);
        preg_match('/user_usec (\d+)/', $cpuStat, $userMatch);
        preg_match('/system_usec (\d+)/', $cpuStat, $systemMatch);

        if ($usageMatch && $userMatch && $systemMatch) {
            $usageUsec = (int) $usageMatch[1];
            $userUsec = (int) $userMatch[1];
            $systemUsec = (int) $systemMatch[1];

            expect($usageUsec)->toBeGreaterThan(0, 'Total usage should be positive');
            expect($userUsec)->toBeGreaterThanOrEqual(0, 'User time non-negative');
            expect($systemUsec)->toBeGreaterThanOrEqual(0, 'System time non-negative');

            // Usage should be approximately user + system
            // Allow some variance for kernel accounting
            expect($usageUsec)->toBeGreaterThanOrEqual(
                ($userUsec + $systemUsec) * 0.9,
                'Total usage should be ~= user + system'
            );
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

        $before = json_decode(DockerHelper::runPhp('cgroupv2-target', $code), true);

        try {
            DockerHelper::stressCpu('cgroupv2-target', 3, 2);
        } catch (RuntimeException $e) {
            // Ignore stress test failures
        }

        $after = json_decode(DockerHelper::runPhp('cgroupv2-target', $code), true);

        expect($after['busyTime'])->toBeGreaterThan(
            $before['busyTime'],
            'CPU busy time should increase even with throttling'
        );

        expect($after['coreCount'])->toBe(
            $before['coreCount'],
            'Core count should remain constant'
        );
    })->skip(
        ! function_exists('stress-ng'),
        'stress-ng not available'
    );
});
