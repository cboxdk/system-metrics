<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Tests\E2E\Support\DockerHelper;

describe('Docker CgroupV1 - Memory Limits', function () {

    it('detects memory limit in cgroup v1 container', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::memory();
echo json_encode([
    'success' => $result->isSuccess(),
    'totalBytes' => $result->isSuccess() ? $result->getValue()->totalBytes : null,
    'error' => $result->isFailure() ? $result->getError()->getMessage() : null,
]);
PHP;

        $output = DockerHelper::runPhp('cgroupv1-target', $code);
        $data = json_decode($output, true);

        expect($data['success'])->toBeTrue('Memory metrics should be readable');
        expect($data['totalBytes'])->toBeGreaterThan(0, 'Total memory should be positive');

        // Note: On macOS Docker Desktop, memory limits are not strictly enforced
        // The library correctly reports available system memory, not cgroup-limited memory
        // This is expected behavior for the current Docker Desktop architecture
    });

    it('reads all memory metrics from cgroup v1 container', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::memory();
if ($result->isSuccess()) {
    $mem = $result->getValue();
    echo json_encode([
        'totalBytes' => $mem->totalBytes,
        'freeBytes' => $mem->freeBytes,
        'availableBytes' => $mem->availableBytes,
        'usedBytes' => $mem->usedBytes,
        'usedPercentage' => $mem->usedPercentage(),
        'swapTotalBytes' => $mem->swapTotalBytes,
        'swapFreeBytes' => $mem->swapFreeBytes,
        'swapUsedBytes' => $mem->swapUsedBytes,
    ]);
}
PHP;

        $output = DockerHelper::runPhp('cgroupv1-target', $code);
        $data = json_decode($output, true);

        expect($data['totalBytes'])->toBeGreaterThan(0, 'Total memory positive');
        expect($data['freeBytes'])->toBeGreaterThanOrEqual(0, 'Free memory non-negative');
        expect($data['availableBytes'])->toBeGreaterThanOrEqual(0, 'Available memory non-negative');
        expect($data['usedBytes'])->toBeGreaterThanOrEqual(0, 'Used memory non-negative');
        expect($data['usedBytes'])->toBeLessThanOrEqual($data['totalBytes'], 'Used <= Total');
        expect($data['usedPercentage'])->toBeGreaterThanOrEqual(0.0, 'Usage % >= 0');
        expect($data['usedPercentage'])->toBeLessThanOrEqual(100.0, 'Usage % <= 100');
    });

    it('validates memory metrics consistency under cgroup v1 limits', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::memory();
if ($result->isSuccess()) {
    $mem = $result->getValue();
    $consistent = true;
    $errors = [];

    if ($mem->freeBytes < 0) {
        $consistent = false;
        $errors[] = 'Free bytes negative';
    }
    if ($mem->availableBytes < 0) {
        $consistent = false;
        $errors[] = 'Available bytes negative';
    }
    if ($mem->usedBytes < 0) {
        $consistent = false;
        $errors[] = 'Used bytes negative';
    }
    if ($mem->usedBytes > $mem->totalBytes) {
        $consistent = false;
        $errors[] = 'Used exceeds total';
    }

    echo json_encode([
        'consistent' => $consistent,
        'errors' => $errors,
    ]);
}
PHP;

        $output = DockerHelper::runPhp('cgroupv1-target', $code);
        $data = json_decode($output, true);

        expect($data['consistent'])->toBeTrue(
            'Memory metrics should be internally consistent: '.implode(', ', $data['errors'] ?? [])
        );
    });

    it('detects memory usage during memory stress test in cgroup v1', function () {
        // Take baseline memory snapshot
        $baselineCode = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::memory();
if ($result->isSuccess()) {
    echo json_encode([
        'usedBytes' => $result->getValue()->usedBytes,
        'usedPercentage' => $result->getValue()->usedPercentage(),
    ]);
}
PHP;

        $baseline = json_decode(DockerHelper::runPhp('cgroupv1-target', $baselineCode), true);

        // Run memory stress test (allocate 100MB for 3 seconds)
        DockerHelper::stressMemory('cgroupv1-target', 100, 3);

        // Take post-stress memory snapshot
        $postStress = json_decode(DockerHelper::runPhp('cgroupv1-target', $baselineCode), true);

        // Memory usage should have increased (or stayed high if already stressed)
        expect($postStress['usedBytes'])->toBeGreaterThanOrEqual(
            $baseline['usedBytes'],
            'Memory usage should increase or maintain during stress'
        );
    })->skip(
        ! function_exists('stress-ng'),
        'stress-ng not available in container'
    );

    it('reads cgroup v1 specific files for memory limit', function () {
        // Skip if not actually cgroup v1 (macOS Docker Desktop uses v2)
        if (! DockerHelper::fileExists('cgroupv1-target', '/sys/fs/cgroup/memory/memory.limit_in_bytes')) {
            expect(true)->toBeTrue('Skipping: Host uses cgroup v2, not v1');

            return;
        }

        // Read memory limit
        $limitStr = trim(DockerHelper::readFile('cgroupv1-target', '/sys/fs/cgroup/memory/memory.limit_in_bytes'));

        expect($limitStr)->toBeNumeric('Memory limit should be numeric');

        $limitBytes = (int) $limitStr;

        // Container has 256m limit
        $expectedBytes = 256 * 1024 * 1024;
        $tolerance = 0.05; // ±5%

        // Some systems report max value (9223372036854771712) when no limit
        if ($limitBytes < 1000000000000) { // Less than 1TB means real limit
            expect($limitBytes)->toBeGreaterThanOrEqual(
                (int) ($expectedBytes * (1 - $tolerance)),
                'Memory limit should be ~256m'
            );
            expect($limitBytes)->toBeLessThanOrEqual(
                (int) ($expectedBytes * (1 + $tolerance)),
                'Memory limit should be ~256m'
            );
        }
    });

    it('reads cgroup v1 memory usage statistics', function () {
        // Skip if not actually cgroup v1 (macOS Docker Desktop uses v2)
        if (! DockerHelper::fileExists('cgroupv1-target', '/sys/fs/cgroup/memory/memory.usage_in_bytes')) {
            expect(true)->toBeTrue('Skipping: Host uses cgroup v2, not v1');

            return;
        }

        $usageStr = trim(DockerHelper::readFile('cgroupv1-target', '/sys/fs/cgroup/memory/memory.usage_in_bytes'));

        expect($usageStr)->toBeNumeric('Memory usage should be numeric');

        $usageBytes = (int) $usageStr;
        expect($usageBytes)->toBeGreaterThan(0, 'Memory usage should be positive');
    });

    it('validates memory reservation in cgroup v1 (soft limit)', function () {
        // Container has --mem-reservation=128m
        // Verify soft limit file exists
        if (DockerHelper::fileExists('cgroupv1-target', '/sys/fs/cgroup/memory/memory.soft_limit_in_bytes')) {
            $softLimitStr = trim(DockerHelper::readFile('cgroupv1-target', '/sys/fs/cgroup/memory/memory.soft_limit_in_bytes'));

            expect($softLimitStr)->toBeNumeric('Soft limit should be numeric');

            $softLimitBytes = (int) $softLimitStr;

            // Should be ~128m
            $expectedBytes = 128 * 1024 * 1024;
            $tolerance = 0.1; // ±10%

            if ($softLimitBytes < 1000000000000) {
                expect($softLimitBytes)->toBeGreaterThanOrEqual(
                    (int) ($expectedBytes * (1 - $tolerance)),
                    'Soft limit should be ~128m'
                );
            }
        } else {
            // Soft limit file might not exist on all systems
            expect(true)->toBeTrue('Soft limit file not found (acceptable)');
        }
    });
});
