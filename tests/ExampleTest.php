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

    expect($result->isSuccess())->toBeTrue();

    $cpu = $result->getValue();
    // Note: On modern macOS, CPU time counters may not be available
    // In this case, the values will be 0 but the structure is still valid
    expect($cpu->total->user)->toBeInt()->toBeGreaterThanOrEqual(0);
    expect($cpu->total->system)->toBeInt()->toBeGreaterThanOrEqual(0);
    expect($cpu->coreCount())->toBeInt()->toBeGreaterThan(0);
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

    expect($result->isSuccess())->toBeTrue();

    $overview = $result->getValue();
    expect($overview->environment)->toBeInstanceOf(\PHPeek\SystemMetrics\DTO\Environment\EnvironmentSnapshot::class);
    expect($overview->cpu)->toBeInstanceOf(\PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot::class);
    expect($overview->memory)->toBeInstanceOf(\PHPeek\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot::class);
});
