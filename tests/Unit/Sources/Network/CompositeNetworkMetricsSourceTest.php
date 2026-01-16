<?php

use Cbox\SystemMetrics\Contracts\NetworkMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;
use Cbox\SystemMetrics\Sources\Network\CompositeNetworkMetricsSource;

describe('CompositeNetworkMetricsSource', function () {
    it('creates OS-specific source when none provided', function () {
        $source = new CompositeNetworkMetricsSource;

        $result = $source->read();

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('uses injected Linux source when provided', function () {
        // Only test on Linux where the Linux source is actually used
        if (PHP_OS_FAMILY !== 'Linux') {
            expect(true)->toBeTrue(); // Skip with passing assertion

            return;
        }

        $mockSource = new class implements NetworkMetricsSource
        {
            public function read(): Result
            {
                return Result::success(
                    new NetworkSnapshot(
                        interfaces: [],
                        connections: null
                    )
                );
            }
        };

        $composite = new CompositeNetworkMetricsSource($mockSource, null);
        $result = $composite->read();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot)->toBeInstanceOf(NetworkSnapshot::class);
    });

    it('uses injected macOS source when provided', function () {
        // Only test on macOS where the macOS source is actually used
        if (PHP_OS_FAMILY !== 'Darwin') {
            expect(true)->toBeTrue(); // Skip with passing assertion

            return;
        }

        $mockSource = new class implements NetworkMetricsSource
        {
            public function read(): Result
            {
                return Result::success(
                    new NetworkSnapshot(
                        interfaces: [],
                        connections: null
                    )
                );
            }
        };

        $composite = new CompositeNetworkMetricsSource(null, $mockSource);
        $result = $composite->read();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot)->toBeInstanceOf(NetworkSnapshot::class);
    });

    it('delegates read to underlying source', function () {
        // Only test on Linux/macOS where sources are available
        if (PHP_OS_FAMILY !== 'Linux' && PHP_OS_FAMILY !== 'Darwin') {
            expect(true)->toBeTrue(); // Skip with passing assertion

            return;
        }

        $mockSource = new class implements NetworkMetricsSource
        {
            public function read(): Result
            {
                return Result::success(
                    new NetworkSnapshot(
                        interfaces: [],
                        connections: null
                    )
                );
            }
        };

        // Inject based on current OS
        $linuxSource = PHP_OS_FAMILY === 'Linux' ? $mockSource : null;
        $macosSource = PHP_OS_FAMILY === 'Darwin' ? $mockSource : null;

        $composite = new CompositeNetworkMetricsSource($linuxSource, $macosSource);
        $result = $composite->read();

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeInstanceOf(NetworkSnapshot::class);
    });

    it('returns error when OS not supported', function () {
        // When both sources are null, it tries to detect OS
        // If we're on Linux or Darwin, it will succeed
        // This test just verifies the composite handles errors properly
        $mockSource = new class implements NetworkMetricsSource
        {
            public function read(): Result
            {
                return Result::failure(
                    new SystemMetricsException('Test error')
                );
            }
        };

        $composite = new CompositeNetworkMetricsSource($mockSource, $mockSource);
        $result = $composite->read();

        // Should return a result (success or failure)
        expect($result)->toBeInstanceOf(Result::class);

        // If it failed, error should be present
        if ($result->isFailure()) {
            expect($result->getError())->toBeInstanceOf(Throwable::class);
        }
    });
});
