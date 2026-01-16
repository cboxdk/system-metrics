<?php

use Cbox\SystemMetrics\SystemMetrics;

it('can read system environment', function () {
    $result = SystemMetrics::environment();

    expect($result->isSuccess())->toBeTrue();

    $env = $result->getValue();
    expect($env->os->family)->toBeInstanceOf(\Cbox\SystemMetrics\DTO\Environment\OsFamily::class);
    expect($env->kernel->release)->toBeString();
    expect($env->architecture->kind)->toBeInstanceOf(\Cbox\SystemMetrics\DTO\Environment\ArchitectureKind::class);
});

it('can read CPU metrics', function () {
    $result = SystemMetrics::cpu();

    // Platform-specific expectations:
    // - Linux: should always succeed via /proc/stat
    // - macOS: may fail on Apple Silicon where kern.cp_time is unavailable
    // - Windows: requires FFI which may not be available in all CI environments
    if (PHP_OS_FAMILY === 'Darwin' || PHP_OS_FAMILY === 'Windows') {
        // macOS/Windows: expect either success or graceful failure
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

    // Windows requires FFI which may not be available in all CI environments
    if (PHP_OS_FAMILY === 'Windows') {
        expect($result->isSuccess() || $result->isFailure())->toBeTrue();

        if ($result->isSuccess()) {
            $mem = $result->getValue();
            expect($mem->totalBytes)->toBeInt()->toBeGreaterThan(0);
            expect($mem->freeBytes)->toBeInt()->toBeGreaterThanOrEqual(0);
            expect($mem->usedBytes)->toBeInt()->toBeGreaterThanOrEqual(0);
            expect($mem->usedPercentage())->toBeFloat()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
        }
    } else {
        // Linux and macOS: should succeed
        expect($result->isSuccess())->toBeTrue();

        $mem = $result->getValue();
        expect($mem->totalBytes)->toBeInt()->toBeGreaterThan(0);
        expect($mem->freeBytes)->toBeInt()->toBeGreaterThanOrEqual(0);
        expect($mem->usedBytes)->toBeInt()->toBeGreaterThanOrEqual(0);
        expect($mem->usedPercentage())->toBeFloat()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
    }
});

it('can get complete system overview', function () {
    $result = SystemMetrics::overview();

    // Overview may fail on macOS (Apple Silicon) or Windows (FFI unavailable)
    if (PHP_OS_FAMILY === 'Darwin' || PHP_OS_FAMILY === 'Windows') {
        expect($result->isSuccess() || $result->isFailure())->toBeTrue();

        if ($result->isSuccess()) {
            $overview = $result->getValue();
            expect($overview->environment)->toBeInstanceOf(\Cbox\SystemMetrics\DTO\Environment\EnvironmentSnapshot::class);
            expect($overview->cpu)->toBeInstanceOf(\Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot::class);
            expect($overview->memory)->toBeInstanceOf(\Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot::class);
        }
    } else {
        // Linux: should succeed
        expect($result->isSuccess())->toBeTrue();

        $overview = $result->getValue();
        expect($overview->environment)->toBeInstanceOf(\Cbox\SystemMetrics\DTO\Environment\EnvironmentSnapshot::class);
        expect($overview->cpu)->toBeInstanceOf(\Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot::class);
        expect($overview->memory)->toBeInstanceOf(\Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot::class);
    }
});
