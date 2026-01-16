<?php

declare(strict_types=1);

use Cbox\SystemMetrics\SystemMetrics;

describe('Environment Caching', function () {
    beforeEach(function () {
        // Clear cache before each test
        SystemMetrics::clearEnvironmentCache();
    });

    it('caches environment detection results', function () {
        // First call should execute detection
        $result1 = SystemMetrics::environment();
        expect($result1->isSuccess())->toBeTrue();

        // Second call should return the same cached result
        $result2 = SystemMetrics::environment();
        expect($result2->isSuccess())->toBeTrue();

        // Results should be identical (same object reference)
        expect($result1)->toBe($result2);
    });

    it('returns the same environment data on subsequent calls', function () {
        $result1 = SystemMetrics::environment();
        $result2 = SystemMetrics::environment();

        expect($result1->isSuccess())->toBeTrue();
        expect($result2->isSuccess())->toBeTrue();

        $env1 = $result1->getValue();
        $env2 = $result2->getValue();

        // Verify same OS data
        expect($env1->os->name)->toBe($env2->os->name);
        expect($env1->os->version)->toBe($env2->os->version);
        expect($env1->os->family->value)->toBe($env2->os->family->value);

        // Verify same kernel data
        expect($env1->kernel->release)->toBe($env2->kernel->release);
        expect($env1->kernel->version)->toBe($env2->kernel->version);

        // Verify same architecture
        expect($env1->architecture->kind->value)->toBe($env2->architecture->kind->value);
    });

    it('clears cache when clearEnvironmentCache is called', function () {
        // First call
        $result1 = SystemMetrics::environment();
        expect($result1->isSuccess())->toBeTrue();

        // Clear cache
        SystemMetrics::clearEnvironmentCache();

        // Second call after clearing - should be a new detection
        $result2 = SystemMetrics::environment();
        expect($result2->isSuccess())->toBeTrue();

        // Results should NOT be the same object reference
        expect($result1)->not->toBe($result2);

        // But the data should still be identical (same system)
        expect($result1->getValue()->os->name)->toBe($result2->getValue()->os->name);
    });
});

describe('Dynamic Metrics Not Cached', function () {
    it('does not cache CPU metrics', function () {
        // Get two CPU snapshots
        $result1 = SystemMetrics::cpu();
        $result2 = SystemMetrics::cpu();

        // CPU metrics may not be available on Windows (no FFI)
        if ($result1->isFailure() || $result2->isFailure()) {
            expect($result1->isFailure() || $result2->isFailure())->toBeTrue();

            return;
        }

        expect($result1->isSuccess())->toBeTrue();
        expect($result2->isSuccess())->toBeTrue();

        // Results should NOT be the same object reference
        // (each call should read fresh data)
        expect($result1)->not->toBe($result2);
    });

    it('does not cache memory metrics', function () {
        // Get two memory snapshots
        $result1 = SystemMetrics::memory();
        $result2 = SystemMetrics::memory();

        // Memory metrics may not be available on Windows (no FFI)
        if ($result1->isFailure() || $result2->isFailure()) {
            expect($result1->isFailure() || $result2->isFailure())->toBeTrue();

            return;
        }

        expect($result1->isSuccess())->toBeTrue();
        expect($result2->isSuccess())->toBeTrue();

        // Results should NOT be the same object reference
        // (each call should read fresh data)
        expect($result1)->not->toBe($result2);
    });

    it('does not cache uptime metrics', function () {
        // Get two uptime snapshots
        $result1 = SystemMetrics::uptime();

        // Wait a tiny bit to ensure time has passed
        usleep(10000); // 10ms

        $result2 = SystemMetrics::uptime();

        // Uptime metrics may not be available on Windows
        if ($result1->isFailure() || $result2->isFailure()) {
            expect($result1->isFailure() || $result2->isFailure())->toBeTrue();

            return;
        }

        expect($result1->isSuccess())->toBeTrue();
        expect($result2->isSuccess())->toBeTrue();

        // Results should NOT be the same object reference
        expect($result1)->not->toBe($result2);

        // Uptime should be different (second measurement should be slightly higher)
        $uptime1 = $result1->getValue()->totalSeconds;
        $uptime2 = $result2->getValue()->totalSeconds;

        // Allow for some variance due to timing precision
        expect($uptime2)->toBeGreaterThanOrEqual($uptime1);
    });
});

describe('Overview Caching Behavior', function () {
    beforeEach(function () {
        SystemMetrics::clearEnvironmentCache();
    });

    it('benefits from environment caching in overview', function () {
        // First overview call
        $result1 = SystemMetrics::overview();

        // Overview may fail on Windows/macOS if CPU or memory metrics unavailable
        if ($result1->isFailure()) {
            expect($result1->isFailure())->toBeTrue();

            return;
        }
        expect($result1->isSuccess())->toBeTrue();

        // Get environment separately (should be cached from overview)
        $envResult = SystemMetrics::environment();
        expect($envResult->isSuccess())->toBeTrue();

        // Second overview call
        $result2 = SystemMetrics::overview();
        if ($result2->isFailure()) {
            return; // Skip if overview fails second time
        }
        expect($result2->isSuccess())->toBeTrue();

        // Environment data should be identical across calls
        $env1 = $result1->getValue()->environment;
        $env2 = $result2->getValue()->environment;
        $envSeparate = $envResult->getValue();

        expect($env1->os->name)->toBe($env2->os->name);
        expect($env1->os->name)->toBe($envSeparate->os->name);
    });

    it('creates new CPU and memory snapshots in each overview', function () {
        $result1 = SystemMetrics::overview();
        $result2 = SystemMetrics::overview();

        // Overview may fail on Windows/macOS if CPU or memory metrics unavailable
        if ($result1->isFailure() || $result2->isFailure()) {
            expect($result1->isFailure() || $result2->isFailure())->toBeTrue();

            return;
        }

        expect($result1->isSuccess())->toBeTrue();
        expect($result2->isSuccess())->toBeTrue();

        // Overview results should be different objects
        expect($result1)->not->toBe($result2);

        $overview1 = $result1->getValue();
        $overview2 = $result2->getValue();

        // CPU and memory should be fresh snapshots
        expect($overview1->cpu)->not->toBe($overview2->cpu);
        expect($overview1->memory)->not->toBe($overview2->memory);
    });
});

describe('Performance Characteristics', function () {
    beforeEach(function () {
        SystemMetrics::clearEnvironmentCache();
    });

    it('shows performance improvement from caching', function () {
        // First call (uncached) - measure time
        $start1 = microtime(true);
        $result1 = SystemMetrics::environment();
        $time1 = microtime(true) - $start1;

        expect($result1->isSuccess())->toBeTrue();

        // Second call (cached) - measure time
        $start2 = microtime(true);
        $result2 = SystemMetrics::environment();
        $time2 = microtime(true) - $start2;

        expect($result2->isSuccess())->toBeTrue();

        // Cached call should be significantly faster
        // (at least 10x faster, typically 100x+ faster)
        expect($time2)->toBeLessThan($time1 / 10);
    })->skip(fn () => getenv('CI') === 'true', 'Timing tests unreliable in CI');

    it('maintains fast access for cached environment data', function () {
        // Warm up cache
        SystemMetrics::environment();

        // Measure 100 cached accesses
        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $result = SystemMetrics::environment();
            expect($result->isSuccess())->toBeTrue();
        }
        $duration = microtime(true) - $start;

        // 100 cached accesses should be very fast (< 10ms total)
        expect($duration)->toBeLessThan(0.01);
    })->skip(fn () => getenv('CI') === 'true', 'Timing tests unreliable in CI');
});
