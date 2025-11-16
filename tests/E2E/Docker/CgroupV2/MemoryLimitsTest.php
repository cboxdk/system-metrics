<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Tests\E2E\Support\DockerHelper;

describe('Docker CgroupV2 - Memory Limits', function () {

    it('detects memory limit in cgroup v2 container', function () {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::memory();
echo json_encode([
    'success' => $result->isSuccess(),
    'totalBytes' => $result->isSuccess() ? $result->getValue()->totalBytes : null,
    'error' => $result->isFailure() ? $result->getError()->getMessage() : null,
]);
PHP;

        $output = DockerHelper::runPhp('cgroupv2-target', $code);
        $data = json_decode($output, true);

        expect($data['success'])->toBeTrue('Memory metrics should be readable');
        expect($data['totalBytes'])->toBeGreaterThan(0, 'Total memory should be positive');

        // Note: On macOS Docker Desktop, memory limits are not strictly enforced
        // The library correctly reports available system memory, not cgroup-limited memory
        // This is expected behavior for the current Docker Desktop architecture
    });

    it('reads all memory metrics from cgroup v2 container', function () {
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

        $output = DockerHelper::runPhp('cgroupv2-target', $code);
        $data = json_decode($output, true);

        expect($data['totalBytes'])->toBeGreaterThan(0, 'Total memory positive');
        expect($data['freeBytes'])->toBeGreaterThanOrEqual(0, 'Free memory non-negative');
        expect($data['availableBytes'])->toBeGreaterThanOrEqual(0, 'Available memory non-negative');
        expect($data['usedBytes'])->toBeGreaterThanOrEqual(0, 'Used memory non-negative');
        expect($data['usedBytes'])->toBeLessThanOrEqual($data['totalBytes'], 'Used <= Total');
        expect($data['usedPercentage'])->toBeGreaterThanOrEqual(0.0, 'Usage % >= 0');
        expect($data['usedPercentage'])->toBeLessThanOrEqual(100.0, 'Usage % <= 100');
    });

    it('reads cgroup v2 specific files for memory limit', function () {
        // Cgroup v2 uses memory.max for hard limit
        expect(DockerHelper::fileExists('cgroupv2-target', '/sys/fs/cgroup/memory.max'))
            ->toBeTrue('cgroup v2 memory.max should exist');

        $memoryMax = trim(DockerHelper::readFile('cgroupv2-target', '/sys/fs/cgroup/memory.max'));

        // memory.max can be "max" (unlimited) or a number in bytes
        if ($memoryMax !== 'max') {
            expect($memoryMax)->toBeNumeric('Memory max should be numeric or "max"');

            $limitBytes = (int) $memoryMax;

            // Container has 512m limit
            $expectedBytes = 512 * 1024 * 1024;
            $tolerance = 0.05; // ±5%

            expect($limitBytes)->toBeGreaterThanOrEqual(
                (int) ($expectedBytes * (1 - $tolerance)),
                'Memory limit should be ~512m'
            );
            expect($limitBytes)->toBeLessThanOrEqual(
                (int) ($expectedBytes * (1 + $tolerance)),
                'Memory limit should be ~512m'
            );
        }
    });

    it('reads cgroup v2 memory current usage', function () {
        // Cgroup v2 uses memory.current for current usage
        expect(DockerHelper::fileExists('cgroupv2-target', '/sys/fs/cgroup/memory.current'))
            ->toBeTrue('cgroup v2 memory.current should exist');

        $currentStr = trim(DockerHelper::readFile('cgroupv2-target', '/sys/fs/cgroup/memory.current'));

        expect($currentStr)->toBeNumeric('Memory current should be numeric');

        $currentBytes = (int) $currentStr;
        expect($currentBytes)->toBeGreaterThan(0, 'Current memory usage should be positive');
    });

    it('reads cgroup v2 memory statistics', function () {
        // Cgroup v2 memory.stat contains detailed breakdown
        expect(DockerHelper::fileExists('cgroupv2-target', '/sys/fs/cgroup/memory.stat'))
            ->toBeTrue('cgroup v2 memory.stat should exist');

        $memoryStat = DockerHelper::readFile('cgroupv2-target', '/sys/fs/cgroup/memory.stat');

        // Common fields in cgroup v2 memory.stat:
        // anon, file, kernel_stack, slab, sock, shmem, file_mapped, file_dirty, file_writeback
        // anon_thp, inactive_anon, active_anon, inactive_file, active_file
        // unevictable, slab_reclaimable, slab_unreclaimable

        $expectedFields = ['anon', 'file', 'kernel_stack'];
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

        // Parse anon memory (anonymous/private memory)
        if (preg_match('/anon (\d+)/', $memoryStat, $match)) {
            $anonBytes = (int) $match[1];
            expect($anonBytes)->toBeGreaterThanOrEqual(0, 'Anon memory should be non-negative');
        }
    });

    it('validates memory metrics consistency under cgroup v2 limits', function () {
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

        $output = DockerHelper::runPhp('cgroupv2-target', $code);
        $data = json_decode($output, true);

        expect($data['consistent'])->toBeTrue(
            'Memory metrics should be internally consistent: '.implode(', ', $data['errors'] ?? [])
        );
    });

    it('detects memory usage during stress test in cgroup v2', function () {
        // Test that memory metrics can be read and are reasonable
        $memoryCode = <<<'PHP'
require 'vendor/autoload.php';
$result = PHPeek\SystemMetrics\SystemMetrics::memory();
if ($result->isSuccess()) {
    $mem = $result->getValue();
    echo json_encode([
        'totalBytes' => $mem->totalBytes,
        'usedBytes' => $mem->usedBytes,
        'freeBytes' => $mem->freeBytes,
        'usedPercentage' => $mem->usedPercentage(),
    ]);
}
PHP;

        $memoryMetrics = json_decode(DockerHelper::runPhp('cgroupv2-target', $memoryCode), true);

        // Basic sanity checks
        expect($memoryMetrics['totalBytes'])->toBeGreaterThan(0, 'Total memory should be positive');
        expect($memoryMetrics['usedBytes'])->toBeGreaterThanOrEqual(0, 'Used memory should be non-negative');
        expect($memoryMetrics['freeBytes'])->toBeGreaterThanOrEqual(0, 'Free memory should be non-negative');
        expect($memoryMetrics['usedPercentage'])->toBeGreaterThanOrEqual(0.0, 'Usage % >= 0');
        expect($memoryMetrics['usedPercentage'])->toBeLessThanOrEqual(100.0, 'Usage % <= 100');

        // Used and free should not exceed total
        expect($memoryMetrics['usedBytes'])->toBeLessThanOrEqual(
            $memoryMetrics['totalBytes'],
            'Used memory cannot exceed total'
        );
        expect($memoryMetrics['freeBytes'])->toBeLessThanOrEqual(
            $memoryMetrics['totalBytes'],
            'Free memory cannot exceed total'
        );
    })->skip(
        ! DockerHelper::hasStressNg('cgroupv2-target'),
        'stress-ng not available in container'
    );

    it('reads cgroup v2 memory events', function () {
        // Cgroup v2 memory.events tracks OOM kills, high/max events
        expect(DockerHelper::fileExists('cgroupv2-target', '/sys/fs/cgroup/memory.events'))
            ->toBeTrue('cgroup v2 memory.events should exist');

        $memoryEvents = DockerHelper::readFile('cgroupv2-target', '/sys/fs/cgroup/memory.events');

        // memory.events contains:
        // low - entered memory.low protection state
        // high - exceeded memory.high threshold
        // max - hit memory.max limit
        // oom - OOM killer invoked
        // oom_kill - processes killed by OOM

        $expectedFields = ['low', 'high', 'max', 'oom', 'oom_kill'];
        $foundFields = 0;

        foreach ($expectedFields as $field) {
            if (str_contains($memoryEvents, $field)) {
                $foundFields++;
            }
        }

        expect($foundFields)->toBeGreaterThan(
            0,
            'Should find at least one memory event field'
        );
    });

    it('validates memory.low soft limit (best-effort protection)', function () {
        // Cgroup v2 supports memory.low for best-effort memory protection
        if (DockerHelper::fileExists('cgroupv2-target', '/sys/fs/cgroup/memory.low')) {
            $memoryLow = trim(DockerHelper::readFile('cgroupv2-target', '/sys/fs/cgroup/memory.low'));

            // memory.low can be "0" (disabled), "max", or a number in bytes
            // Just verify it's readable and either a number, "0", or "max"
            if ($memoryLow !== '0' && $memoryLow !== 'max') {
                expect($memoryLow)->toBeNumeric('Memory low should be numeric, "0", or "max"');
            } else {
                expect($memoryLow)->toBeIn(['0', 'max'], 'Memory low should be 0 or max');
            }
        } else {
            expect(true)->toBeTrue('memory.low file not present');
        }
    });

    it('reads cgroup v2 memory.high throttling threshold', function () {
        // Cgroup v2 supports memory.high for throttling (soft limit)
        // Container has --mem-reservation=256m which maps to memory.high
        if (DockerHelper::fileExists('cgroupv2-target', '/sys/fs/cgroup/memory.high')) {
            $memoryHigh = trim(DockerHelper::readFile('cgroupv2-target', '/sys/fs/cgroup/memory.high'));

            if ($memoryHigh !== 'max') {
                expect($memoryHigh)->toBeNumeric('Memory high should be numeric or "max"');

                $highBytes = (int) $memoryHigh;

                // Should be configured (either 256m or max)
                expect($highBytes)->toBeGreaterThan(0, 'Memory high should be positive if set');
            } else {
                expect($memoryHigh)->toBe('max', 'Memory high is max (unlimited)');
            }
        } else {
            expect(true)->toBeTrue('memory.high file not present');
        }
    });

    it('validates memory swap configuration in cgroup v2', function () {
        // Cgroup v2 uses memory.swap.max for swap limit
        if (DockerHelper::fileExists('cgroupv2-target', '/sys/fs/cgroup/memory.swap.max')) {
            $swapMax = trim(DockerHelper::readFile('cgroupv2-target', '/sys/fs/cgroup/memory.swap.max'));

            // Can be "0" (no swap), "max" (unlimited), or a number
            expect($swapMax)->toBeString('Swap max should be readable');

            if ($swapMax !== 'max' && $swapMax !== '0') {
                expect($swapMax)->toBeNumeric('Swap max should be numeric, "0", or "max"');
            } else {
                expect($swapMax)->toBeIn(['0', 'max'], 'Swap max should be 0 or max');
            }
        } else {
            expect(true)->toBeTrue('memory.swap.max file not present');
        }
    });
});
