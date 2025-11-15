<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Contracts\LoadAverageSource;
use PHPeek\SystemMetrics\DTO\Metrics\LoadAverageSnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\UnsupportedOperatingSystemException;
use PHPeek\SystemMetrics\Sources\LoadAverage\CompositeLoadAverageSource;

describe('CompositeLoadAverageSource', function () {
    it('creates OS-specific source when none provided', function () {
        $source = new CompositeLoadAverageSource;

        $result = $source->read();

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('uses injected source when provided', function () {
        $mockSource = new class implements LoadAverageSource
        {
            public function read(): Result
            {
                return Result::success(
                    new LoadAverageSnapshot(
                        oneMinute: 1.23,
                        fiveMinutes: 4.56,
                        fifteenMinutes: 7.89,
                    )
                );
            }
        };

        $composite = new CompositeLoadAverageSource($mockSource);
        $result = $composite->read();

        expect($result->isSuccess())->toBeTrue();
        $load = $result->getValue();
        expect($load->oneMinute)->toBe(1.23);
        expect($load->fiveMinutes)->toBe(4.56);
        expect($load->fifteenMinutes)->toBe(7.89);
    });

    it('delegates read to underlying source', function () {
        $mockSource = new class implements LoadAverageSource
        {
            public function read(): Result
            {
                return Result::success(
                    new LoadAverageSnapshot(
                        oneMinute: 2.45,
                        fiveMinutes: 1.80,
                        fifteenMinutes: 1.20,
                    )
                );
            }
        };

        $composite = new CompositeLoadAverageSource($mockSource);
        $result = $composite->read();

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeInstanceOf(LoadAverageSnapshot::class);
    });

    it('propagates errors from underlying source', function () {
        $mockSource = new class implements LoadAverageSource
        {
            public function read(): Result
            {
                return Result::failure(
                    UnsupportedOperatingSystemException::forOs('Windows')
                );
            }
        };

        $composite = new CompositeLoadAverageSource($mockSource);
        $result = $composite->read();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(UnsupportedOperatingSystemException::class);
    });
});
