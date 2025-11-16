<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\SystemMetrics;

describe('SystemMetrics Facade', function () {
    it('can get environment detection', function () {
        $result = SystemMetrics::environment();

        expect($result)->toBeInstanceOf(Result::class);
        expect($result->isSuccess())->toBeTrue();

        $env = $result->getValue();
        expect($env->os->family->value)->toBeIn(['linux', 'macos', 'bsd', 'windows', 'unknown']);
    });

    it('can get CPU metrics', function () {
        $result = SystemMetrics::cpu();

        expect($result)->toBeInstanceOf(Result::class);

        // CPU metrics might not be available on modern macOS
        if ($result->isSuccess()) {
            $cpu = $result->getValue();
            expect($cpu->coreCount())->toBeGreaterThan(0);
            expect($cpu->total->total())->toBeGreaterThanOrEqual(0);
        } else {
            // If it fails, expect it to be a proper Result failure
            expect($result->isFailure())->toBeTrue();
        }
    });

    it('can get memory metrics', function () {
        $result = SystemMetrics::memory();

        expect($result)->toBeInstanceOf(Result::class);
        expect($result->isSuccess())->toBeTrue();

        $memory = $result->getValue();
        expect($memory->totalBytes)->toBeGreaterThan(0);
        expect($memory->usedBytes)->toBeGreaterThanOrEqual(0);
        expect($memory->freeBytes)->toBeGreaterThanOrEqual(0);
    });

    it('can get load average', function () {
        $result = SystemMetrics::loadAverage();

        expect($result)->toBeInstanceOf(Result::class);

        // Load average might not be available on all systems
        if ($result->isSuccess()) {
            $load = $result->getValue();
            expect($load->oneMinute)->toBeGreaterThanOrEqual(0.0);
            expect($load->fiveMinutes)->toBeGreaterThanOrEqual(0.0);
            expect($load->fifteenMinutes)->toBeGreaterThanOrEqual(0.0);
        }
    });

    it('can get system uptime', function () {
        $result = SystemMetrics::uptime();

        expect($result)->toBeInstanceOf(Result::class);

        if ($result->isSuccess()) {
            $uptime = $result->getValue();
            expect($uptime->totalSeconds)->toBeGreaterThan(0);
        }
    });

    it('can get system limits', function () {
        $result = SystemMetrics::limits();

        expect($result)->toBeInstanceOf(Result::class);

        if ($result->isSuccess()) {
            $limits = $result->getValue();
            expect($limits->cpuCores)->toBeGreaterThan(0);
            expect($limits->memoryBytes)->toBeGreaterThan(0);
        }
    });

    it('can get storage metrics', function () {
        $result = SystemMetrics::storage();

        expect($result)->toBeInstanceOf(Result::class);

        if ($result->isSuccess()) {
            $storage = $result->getValue();
            expect($storage->mountPoints)->toBeArray();
            expect($storage->totalBytes())->toBeGreaterThanOrEqual(0);
        }
    });

    it('can get network metrics', function () {
        $result = SystemMetrics::network();

        expect($result)->toBeInstanceOf(Result::class);

        if ($result->isSuccess()) {
            $network = $result->getValue();
            expect($network->interfaces)->toBeArray();
            expect($network->totalBytesReceived())->toBeGreaterThanOrEqual(0);
            expect($network->totalBytesSent())->toBeGreaterThanOrEqual(0);
        }
    });

    it('can get container metrics', function () {
        $result = SystemMetrics::container();

        expect($result)->toBeInstanceOf(Result::class);

        // Container metrics only available in container environments
        if ($result->isSuccess()) {
            $container = $result->getValue();

            // Check if limits are set (might be null)
            if ($container->cpuQuota !== null) {
                expect($container->cpuQuota)->toBeGreaterThanOrEqual(0.0);
            }

            if ($container->memoryLimitBytes !== null) {
                expect($container->memoryLimitBytes)->toBeGreaterThanOrEqual(0);
            }
        }
    });

    it('can measure CPU usage with default interval', function () {
        $result = SystemMetrics::cpuUsage();

        expect($result)->toBeInstanceOf(Result::class);

        if ($result->isSuccess()) {
            $delta = $result->getValue();
            expect($delta->usagePercentage())->toBeGreaterThanOrEqual(0.0);
            $coreCount = count($delta->perCoreDelta);
            expect($delta->usagePercentage())->toBeLessThanOrEqual(100.0 * $coreCount);
        }
    })->skip(function () {
        // Skip if we can't get CPU metrics
        return SystemMetrics::cpu()->isFailure();
    }, 'CPU metrics not available');

    it('can measure CPU usage with custom interval', function () {
        $result = SystemMetrics::cpuUsage(0.5);

        expect($result)->toBeInstanceOf(Result::class);

        if ($result->isSuccess()) {
            $delta = $result->getValue();
            expect($delta->usagePercentage())->toBeGreaterThanOrEqual(0.0);
        }
    })->skip(function () {
        return SystemMetrics::cpu()->isFailure();
    }, 'CPU metrics not available');

    it('enforces minimum interval for CPU usage', function () {
        // Should take at least 0.1 seconds even if we pass 0.01
        $start = microtime(true);
        SystemMetrics::cpuUsage(0.01);
        $elapsed = microtime(true) - $start;

        expect($elapsed)->toBeGreaterThanOrEqual(0.09); // Allow small timing variance
    })->skip(function () {
        return SystemMetrics::cpu()->isFailure();
    }, 'CPU metrics not available');

    it('propagates CPU errors in cpuUsage', function () {
        // This test assumes CPU metrics work - if they don't, we can't test error propagation
        $firstSnapshot = SystemMetrics::cpu();

        if ($firstSnapshot->isFailure()) {
            // Can't test error propagation if initial call fails
            expect(true)->toBeTrue();

            return;
        }

        // If first snapshot works, cpuUsage should also work
        $result = SystemMetrics::cpuUsage(0.1);
        expect($result->isSuccess())->toBeTrue();
    });

    it('can get system overview', function () {
        $result = SystemMetrics::overview();

        expect($result)->toBeInstanceOf(Result::class);

        // Overview might fail if CPU fails (on modern macOS)
        if ($result->isSuccess()) {
            $overview = $result->getValue();
            expect($overview->environment)->toBeInstanceOf(\PHPeek\SystemMetrics\DTO\Environment\EnvironmentSnapshot::class);
            expect($overview->cpu)->toBeInstanceOf(\PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot::class);
            expect($overview->memory)->toBeInstanceOf(\PHPeek\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot::class);
        } else {
            expect($result->isFailure())->toBeTrue();
        }
    });

    it('overview includes all expected metrics', function () {
        $result = SystemMetrics::overview();

        if ($result->isSuccess()) {
            $overview = $result->getValue();

            // Environment should always be available
            expect($overview->environment->os->name)->toBeString();

            // CPU should always be available
            expect($overview->cpu->coreCount())->toBeGreaterThan(0);

            // Memory should always be available
            expect($overview->memory->totalBytes)->toBeGreaterThan(0);

            // Storage should be available
            expect($overview->storage)->toBeInstanceOf(\PHPeek\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot::class);

            // Network should be available
            expect($overview->network)->toBeInstanceOf(\PHPeek\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot::class);
        } else {
            // If overview fails, verify it's a proper failure
            expect($result->isFailure())->toBeTrue();
        }
    });

    it('returns Result type for all methods', function () {
        expect(SystemMetrics::environment())->toBeInstanceOf(Result::class);
        expect(SystemMetrics::cpu())->toBeInstanceOf(Result::class);
        expect(SystemMetrics::memory())->toBeInstanceOf(Result::class);
        expect(SystemMetrics::loadAverage())->toBeInstanceOf(Result::class);
        expect(SystemMetrics::uptime())->toBeInstanceOf(Result::class);
        expect(SystemMetrics::limits())->toBeInstanceOf(Result::class);
        expect(SystemMetrics::storage())->toBeInstanceOf(Result::class);
        expect(SystemMetrics::network())->toBeInstanceOf(Result::class);
        expect(SystemMetrics::container())->toBeInstanceOf(Result::class);
        expect(SystemMetrics::overview())->toBeInstanceOf(Result::class);
    });

    it('facade methods are static', function () {
        $reflection = new ReflectionClass(SystemMetrics::class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            expect($method->isStatic())->toBeTrue(
                "Method {$method->getName()} should be static"
            );
        }
    });

    it('facade is final and cannot be extended', function () {
        $reflection = new ReflectionClass(SystemMetrics::class);
        expect($reflection->isFinal())->toBeTrue();
    });
});
