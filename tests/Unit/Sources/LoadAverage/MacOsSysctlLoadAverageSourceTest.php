<?php

declare(strict_types=1);

use Cbox\SystemMetrics\Contracts\ProcessRunnerInterface;
use Cbox\SystemMetrics\DTO\Metrics\LoadAverageSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;
use Cbox\SystemMetrics\Sources\LoadAverage\MacOsSysctlLoadAverageSource;

// Test doubles
class FakeProcessRunner implements ProcessRunnerInterface
{
    public function __construct(private string $returnOutput) {}

    public function execute(string $command): Result
    {
        return Result::success($this->returnOutput);
    }
}

class FakeProcessRunnerFailure implements ProcessRunnerInterface
{
    public function execute(string $command): Result
    {
        return Result::failure(new SystemMetricsException('Command failed'));
    }
}

describe('MacOsSysctlLoadAverageSource', function () {
    it('uses default dependencies when none provided', function () {
        $source = new MacOsSysctlLoadAverageSource;
        $result = $source->read();

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('can read load average successfully', function () {
        $processRunner = new FakeProcessRunner('{ 0.57 0.80 0.85 }');
        $source = new MacOsSysctlLoadAverageSource(
            processRunner: $processRunner,
        );

        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        $load = $result->getValue();
        expect($load)->toBeInstanceOf(LoadAverageSnapshot::class);
        expect($load->oneMinute)->toBe(0.57);
        expect($load->fiveMinutes)->toBe(0.80);
        expect($load->fifteenMinutes)->toBe(0.85);
    });

    it('propagates command execution errors', function () {
        $processRunner = new FakeProcessRunnerFailure;
        $source = new MacOsSysctlLoadAverageSource(
            processRunner: $processRunner,
        );

        $result = $source->read();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(SystemMetricsException::class);
    });

    it('handles empty sysctl output', function () {
        $processRunner = new FakeProcessRunner('');
        $source = new MacOsSysctlLoadAverageSource(
            processRunner: $processRunner,
        );

        $result = $source->read();

        expect($result->isFailure())->toBeTrue();
    });

    it('handles high load values', function () {
        $processRunner = new FakeProcessRunner('{ 128.45 64.30 32.75 }');
        $source = new MacOsSysctlLoadAverageSource(
            processRunner: $processRunner,
        );

        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        $load = $result->getValue();
        expect($load->oneMinute)->toBe(128.45);
        expect($load->fiveMinutes)->toBe(64.30);
        expect($load->fifteenMinutes)->toBe(32.75);
    });

    it('handles output without braces', function () {
        $processRunner = new FakeProcessRunner('0.57 0.80 0.85');
        $source = new MacOsSysctlLoadAverageSource(
            processRunner: $processRunner,
        );

        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        $load = $result->getValue();
        expect($load->oneMinute)->toBe(0.57);
    });
});
