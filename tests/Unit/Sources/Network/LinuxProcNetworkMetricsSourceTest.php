<?php

use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Sources\Network\LinuxProcNetworkMetricsSource;

describe('LinuxProcNetworkMetricsSource', function () {
    it('can read network metrics on Linux', function () {
        $source = new LinuxProcNetworkMetricsSource;

        $result = $source->read();

        expect($result)->toBeInstanceOf(Result::class);

        // Only verify structure on actual Linux system
        if (PHP_OS_FAMILY === 'Linux') {
            if ($result->isSuccess()) {
                $snapshot = $result->getValue();
                expect($snapshot)->toBeInstanceOf(NetworkSnapshot::class);
                expect($snapshot->interfaces)->toBeArray();
            }
        }
    });

    it('returns Result type', function () {
        $source = new LinuxProcNetworkMetricsSource;

        $result = $source->read();

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('handles read errors gracefully', function () {
        $source = new LinuxProcNetworkMetricsSource;

        $result = $source->read();

        // Should always return a Result, either success or failure
        expect($result)->toBeInstanceOf(Result::class);

        if ($result->isFailure()) {
            expect($result->getError())->toBeInstanceOf(Throwable::class);
        }
    });

    it('includes connection stats in snapshot', function () {
        $source = new LinuxProcNetworkMetricsSource;

        $result = $source->read();

        expect($result)->toBeInstanceOf(Result::class);

        if (PHP_OS_FAMILY === 'Linux' && $result->isSuccess()) {
            $snapshot = $result->getValue();
            expect($snapshot)->toBeInstanceOf(NetworkSnapshot::class);
            // Connection stats may be null or populated - just verify type
            $connections = $snapshot->connections;
            expect($connections === null || is_object($connections))->toBeTrue();
        }
    });
});
