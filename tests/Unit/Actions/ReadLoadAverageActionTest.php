<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Actions\ReadLoadAverageAction;
use PHPeek\SystemMetrics\Contracts\LoadAverageSource;
use PHPeek\SystemMetrics\DTO\Metrics\LoadAverageSnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\UnsupportedOperatingSystemException;

describe('ReadLoadAverageAction', function () {
    it('uses default source when none provided', function () {
        $action = new ReadLoadAverageAction;
        $result = $action->execute();

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('can execute with custom source', function () {
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

        $action = new ReadLoadAverageAction($mockSource);
        $result = $action->execute();

        expect($result->isSuccess())->toBeTrue();
        $load = $result->getValue();
        expect($load)->toBeInstanceOf(LoadAverageSnapshot::class);
        expect($load->oneMinute)->toBe(2.45);
        expect($load->fiveMinutes)->toBe(1.80);
        expect($load->fifteenMinutes)->toBe(1.20);
    });

    it('propagates source errors', function () {
        $mockSource = new class implements LoadAverageSource
        {
            public function read(): Result
            {
                return Result::failure(
                    UnsupportedOperatingSystemException::forOs('Windows')
                );
            }
        };

        $action = new ReadLoadAverageAction($mockSource);
        $result = $action->execute();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(UnsupportedOperatingSystemException::class);
    });

    it('handles zero load values', function () {
        $mockSource = new class implements LoadAverageSource
        {
            public function read(): Result
            {
                return Result::success(
                    new LoadAverageSnapshot(
                        oneMinute: 0.0,
                        fiveMinutes: 0.0,
                        fifteenMinutes: 0.0,
                    )
                );
            }
        };

        $action = new ReadLoadAverageAction($mockSource);
        $result = $action->execute();

        expect($result->isSuccess())->toBeTrue();
        $load = $result->getValue();
        expect($load->oneMinute)->toBe(0.0);
        expect($load->fiveMinutes)->toBe(0.0);
        expect($load->fifteenMinutes)->toBe(0.0);
    });

    it('handles high load values', function () {
        $mockSource = new class implements LoadAverageSource
        {
            public function read(): Result
            {
                return Result::success(
                    new LoadAverageSnapshot(
                        oneMinute: 128.45,
                        fiveMinutes: 64.30,
                        fifteenMinutes: 32.75,
                    )
                );
            }
        };

        $action = new ReadLoadAverageAction($mockSource);
        $result = $action->execute();

        expect($result->isSuccess())->toBeTrue();
        $load = $result->getValue();
        expect($load->oneMinute)->toBe(128.45);
        expect($load->fiveMinutes)->toBe(64.30);
        expect($load->fifteenMinutes)->toBe(32.75);
    });
});
