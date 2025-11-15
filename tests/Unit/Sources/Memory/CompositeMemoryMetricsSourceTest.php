<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Contracts\MemoryMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\UnsupportedOperatingSystemException;
use PHPeek\SystemMetrics\Sources\Memory\CompositeMemoryMetricsSource;

describe('CompositeMemoryMetricsSource', function () {
    it('creates OS-specific source when none provided', function () {
        $source = new CompositeMemoryMetricsSource;

        $result = $source->read();

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('uses injected source when provided', function () {
        $mockSource = new class implements MemoryMetricsSource {
            public function read(): Result
            {
                return Result::success(
                    new MemorySnapshot(
                        totalBytes: 16000000000,
                        freeBytes: 4000000000,
                        availableBytes: 8000000000,
                        usedBytes: 8000000000,
                        buffersBytes: 1000000000,
                        cachedBytes: 2000000000,
                        swapTotalBytes: 2000000000,
                        swapFreeBytes: 1500000000,
                        swapUsedBytes: 500000000
                    )
                );
            }
        };

        $composite = new CompositeMemoryMetricsSource($mockSource);
        $result = $composite->read();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot)->toBeInstanceOf(MemorySnapshot::class);
        expect($snapshot->totalBytes)->toBe(16000000000);
    });

    it('delegates read to underlying source', function () {
        $mockSource = new class implements MemoryMetricsSource {
            public function read(): Result
            {
                return Result::success(
                    new MemorySnapshot(
                        totalBytes: 32000000000,
                        freeBytes: 8000000000,
                        availableBytes: 16000000000,
                        usedBytes: 16000000000,
                        buffersBytes: 2000000000,
                        cachedBytes: 4000000000,
                        swapTotalBytes: 4000000000,
                        swapFreeBytes: 3000000000,
                        swapUsedBytes: 1000000000
                    )
                );
            }
        };

        $composite = new CompositeMemoryMetricsSource($mockSource);
        $result = $composite->read();

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeInstanceOf(MemorySnapshot::class);
    });

    it('propagates errors from underlying source', function () {
        $mockSource = new class implements MemoryMetricsSource {
            public function read(): Result
            {
                return Result::failure(
                    UnsupportedOperatingSystemException::forOs('Windows')
                );
            }
        };

        $composite = new CompositeMemoryMetricsSource($mockSource);
        $result = $composite->read();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(UnsupportedOperatingSystemException::class);
    });
});
