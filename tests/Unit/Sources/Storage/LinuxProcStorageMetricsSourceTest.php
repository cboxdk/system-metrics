<?php

use Cbox\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\Storage\LinuxProcStorageMetricsSource;

describe('LinuxProcStorageMetricsSource', function () {
    it('can read storage metrics on Linux', function () {
        $source = new LinuxProcStorageMetricsSource;

        $result = $source->read();

        expect($result)->toBeInstanceOf(Result::class);

        // Only verify structure on actual Linux system
        if (PHP_OS_FAMILY === 'Linux') {
            if ($result->isSuccess()) {
                $snapshot = $result->getValue();
                expect($snapshot)->toBeInstanceOf(StorageSnapshot::class);
                expect($snapshot->mountPoints)->toBeArray();
                expect($snapshot->diskIO)->toBeArray();
            }
        }
    });

    it('returns Result type', function () {
        $source = new LinuxProcStorageMetricsSource;

        $result = $source->read();

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('handles read errors gracefully', function () {
        $source = new LinuxProcStorageMetricsSource;

        $result = $source->read();

        // Should always return a Result, either success or failure
        expect($result)->toBeInstanceOf(Result::class);

        if ($result->isFailure()) {
            expect($result->getError())->toBeInstanceOf(Throwable::class);
        }
    });
});
