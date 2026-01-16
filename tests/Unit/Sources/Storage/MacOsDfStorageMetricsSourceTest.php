<?php

use Cbox\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\Storage\MacOsDfStorageMetricsSource;

describe('MacOsDfStorageMetricsSource', function () {
    it('can read storage metrics on macOS', function () {
        $source = new MacOsDfStorageMetricsSource;

        $result = $source->read();

        expect($result)->toBeInstanceOf(Result::class);

        // Only verify structure on actual macOS system
        if (PHP_OS_FAMILY === 'Darwin') {
            if ($result->isSuccess()) {
                $snapshot = $result->getValue();
                expect($snapshot)->toBeInstanceOf(StorageSnapshot::class);
                expect($snapshot->mountPoints)->toBeArray();
                expect($snapshot->diskIO)->toBeArray();
            }
        }
    });

    it('returns Result type', function () {
        $source = new MacOsDfStorageMetricsSource;

        $result = $source->read();

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('handles read errors gracefully', function () {
        $source = new MacOsDfStorageMetricsSource;

        $result = $source->read();

        // Should always return a Result, either success or failure
        expect($result)->toBeInstanceOf(Result::class);

        if ($result->isFailure()) {
            expect($result->getError())->toBeInstanceOf(Throwable::class);
        }
    });
});
