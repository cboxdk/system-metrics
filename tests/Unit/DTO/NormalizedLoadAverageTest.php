<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\DTO\Metrics\NormalizedLoadAverage;

it('can be instantiated with all values', function () {
    $normalized = new NormalizedLoadAverage(
        oneMinute: 0.5,
        fiveMinutes: 0.375,
        fifteenMinutes: 0.25,
        coreCount: 8
    );

    expect($normalized->oneMinute)->toBe(0.5);
    expect($normalized->fiveMinutes)->toBe(0.375);
    expect($normalized->fifteenMinutes)->toBe(0.25);
    expect($normalized->coreCount)->toBe(8);
});

it('calculates one minute percentage correctly', function () {
    $normalized = new NormalizedLoadAverage(
        oneMinute: 0.5,
        fiveMinutes: 0.0,
        fifteenMinutes: 0.0,
        coreCount: 8
    );

    expect($normalized->oneMinutePercentage())->toBe(50.0);
});

it('calculates five minutes percentage correctly', function () {
    $normalized = new NormalizedLoadAverage(
        oneMinute: 0.0,
        fiveMinutes: 0.75,
        fifteenMinutes: 0.0,
        coreCount: 8
    );

    expect($normalized->fiveMinutesPercentage())->toBe(75.0);
});

it('calculates fifteen minutes percentage correctly', function () {
    $normalized = new NormalizedLoadAverage(
        oneMinute: 0.0,
        fiveMinutes: 0.0,
        fifteenMinutes: 0.25,
        coreCount: 8
    );

    expect($normalized->fifteenMinutesPercentage())->toBe(25.0);
});

it('handles 100% capacity correctly', function () {
    $normalized = new NormalizedLoadAverage(
        oneMinute: 1.0,
        fiveMinutes: 1.0,
        fifteenMinutes: 1.0,
        coreCount: 8
    );

    expect($normalized->oneMinutePercentage())->toBe(100.0);
    expect($normalized->fiveMinutesPercentage())->toBe(100.0);
    expect($normalized->fifteenMinutesPercentage())->toBe(100.0);
});

it('handles overloaded system (>100%)', function () {
    $normalized = new NormalizedLoadAverage(
        oneMinute: 2.0,
        fiveMinutes: 1.5,
        fifteenMinutes: 1.25,
        coreCount: 8
    );

    expect($normalized->oneMinutePercentage())->toBe(200.0);
    expect($normalized->fiveMinutesPercentage())->toBe(150.0);
    expect($normalized->fifteenMinutesPercentage())->toBe(125.0);
});

it('handles zero load', function () {
    $normalized = new NormalizedLoadAverage(
        oneMinute: 0.0,
        fiveMinutes: 0.0,
        fifteenMinutes: 0.0,
        coreCount: 8
    );

    expect($normalized->oneMinutePercentage())->toBe(0.0);
    expect($normalized->fiveMinutesPercentage())->toBe(0.0);
    expect($normalized->fifteenMinutesPercentage())->toBe(0.0);
});

it('handles single core system', function () {
    $normalized = new NormalizedLoadAverage(
        oneMinute: 0.8,
        fiveMinutes: 0.6,
        fifteenMinutes: 0.4,
        coreCount: 1
    );

    expect($normalized->oneMinutePercentage())->toBe(80.0);
    expect($normalized->fiveMinutesPercentage())->toBe(60.0);
    expect($normalized->fifteenMinutesPercentage())->toBe(40.0);
    expect($normalized->coreCount)->toBe(1);
});

it('handles many-core system', function () {
    $normalized = new NormalizedLoadAverage(
        oneMinute: 0.3,
        fiveMinutes: 0.25,
        fifteenMinutes: 0.2,
        coreCount: 128
    );

    expect($normalized->oneMinutePercentage())->toBe(30.0);
    expect($normalized->coreCount)->toBe(128);
});

it('is immutable', function () {
    $normalized = new NormalizedLoadAverage(
        oneMinute: 0.5,
        fiveMinutes: 0.375,
        fifteenMinutes: 0.25,
        coreCount: 8
    );

    $reflection = new ReflectionClass($normalized);
    expect($reflection->isReadOnly())->toBeTrue();
});
