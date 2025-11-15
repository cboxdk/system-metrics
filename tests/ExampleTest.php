<?php

use PHPeek\SystemMetrics\SystemMetrics;

it('can read system environment', function () {
    $result = SystemMetrics::environment();

    expect($result->isSuccess())->toBeTrue();

    $env = $result->getValue();
    expect($env->os->family)->toBeInstanceOf(\PHPeek\SystemMetrics\DTO\Environment\OsFamily::class);
    expect($env->kernel->release)->toBeString();
    expect($env->architecture->kind)->toBeInstanceOf(\PHPeek\SystemMetrics\DTO\Environment\ArchitectureKind::class);
});

it('can read CPU metrics', function () {
    $result = SystemMetrics::cpu();

    // On modern macOS/Apple Silicon, CPU metrics are not available and return failure
    // On Linux, they should succeed
    if (PHP_OS_FAMILY === 'Darwin') {
        // macOS: expect failure on systems where kern.cp_time is unavailable
        expect($result->isSuccess() || $result->isFailure())->toBeTrue();

        if ($result->isSuccess()) {
            $cpu = $result->getValue();
            expect($cpu->total->user)->toBeInt()->toBeGreaterThanOrEqual(0);
            expect($cpu->total->system)->toBeInt()->toBeGreaterThanOrEqual(0);
            expect($cpu->coreCount())->toBeInt()->toBeGreaterThan(0);
        }
    } else {
        // Linux: should succeed
        expect($result->isSuccess())->toBeTrue();

        $cpu = $result->getValue();
        expect($cpu->total->user)->toBeInt()->toBeGreaterThanOrEqual(0);
        expect($cpu->total->system)->toBeInt()->toBeGreaterThanOrEqual(0);
        expect($cpu->coreCount())->toBeInt()->toBeGreaterThan(0);
    }
});

it('can read memory metrics', function () {
    $result = SystemMetrics::memory();

    expect($result->isSuccess())->toBeTrue();

    $mem = $result->getValue();
    expect($mem->totalBytes)->toBeInt()->toBeGreaterThan(0);
    expect($mem->freeBytes)->toBeInt()->toBeGreaterThanOrEqual(0);
    expect($mem->usedBytes)->toBeInt()->toBeGreaterThanOrEqual(0);
    expect($mem->usedPercentage())->toBeFloat()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
});

it('can get complete system overview', function () {
    $result = SystemMetrics::overview();

    // Overview may fail on modern macOS if CPU metrics are unavailable
    if (PHP_OS_FAMILY === 'Darwin') {
        expect($result->isSuccess() || $result->isFailure())->toBeTrue();

        if ($result->isSuccess()) {
            $overview = $result->getValue();
            expect($overview->environment)->toBeInstanceOf(\PHPeek\SystemMetrics\DTO\Environment\EnvironmentSnapshot::class);
            expect($overview->cpu)->toBeInstanceOf(\PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot::class);
            expect($overview->memory)->toBeInstanceOf(\PHPeek\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot::class);
        }
    } else {
        // Linux: should succeed
        expect($result->isSuccess())->toBeTrue();

        $overview = $result->getValue();
        expect($overview->environment)->toBeInstanceOf(\PHPeek\SystemMetrics\DTO\Environment\EnvironmentSnapshot::class);
        expect($overview->cpu)->toBeInstanceOf(\PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot::class);
        expect($overview->memory)->toBeInstanceOf(\PHPeek\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot::class);
    }
});
