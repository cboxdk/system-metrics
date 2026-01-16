<?php

declare(strict_types=1);

use Cbox\SystemMetrics\Contracts\FileReaderInterface;
use Cbox\SystemMetrics\DTO\Metrics\LoadAverageSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\FileNotFoundException;
use Cbox\SystemMetrics\Exceptions\ParseException;
use Cbox\SystemMetrics\Sources\LoadAverage\LinuxProcLoadAverageSource;
use Cbox\SystemMetrics\Support\Parser\LinuxProcLoadavgParser;

// Test doubles
class FakeLoadAvgFileReader implements FileReaderInterface
{
    public function __construct(private string $returnContent) {}

    public function read(string $path): Result
    {
        return Result::success($this->returnContent);
    }

    public function exists(string $path): bool
    {
        return true;
    }
}

class FakeLoadAvgFileReaderNotFound implements FileReaderInterface
{
    public function read(string $path): Result
    {
        return Result::failure(new FileNotFoundException('/proc/loadavg'));
    }

    public function exists(string $path): bool
    {
        return false;
    }
}

describe('LinuxProcLoadAverageSource', function () {
    it('uses default dependencies when none provided', function () {
        $source = new LinuxProcLoadAverageSource;
        $result = $source->read();

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('can read load average successfully', function () {
        $fileReader = new FakeLoadAvgFileReader('2.45 1.80 1.20 2/750 1234');
        $source = new LinuxProcLoadAverageSource(
            fileReader: $fileReader,
        );

        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        $load = $result->getValue();
        expect($load)->toBeInstanceOf(LoadAverageSnapshot::class);
        expect($load->oneMinute)->toBe(2.45);
        expect($load->fiveMinutes)->toBe(1.80);
        expect($load->fifteenMinutes)->toBe(1.20);
    });

    it('propagates file not found errors', function () {
        $fileReader = new FakeLoadAvgFileReaderNotFound;
        $source = new LinuxProcLoadAverageSource(
            fileReader: $fileReader,
        );

        $result = $source->read();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(FileNotFoundException::class);
    });

    it('handles empty loadavg file', function () {
        $fileReader = new FakeLoadAvgFileReader('');
        $source = new LinuxProcLoadAverageSource(
            fileReader: $fileReader,
        );

        $result = $source->read();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(ParseException::class);
    });

    it('handles high load values', function () {
        $fileReader = new FakeLoadAvgFileReader('128.45 64.30 32.75 50/750 1234');
        $source = new LinuxProcLoadAverageSource(
            fileReader: $fileReader,
        );

        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        $load = $result->getValue();
        expect($load->oneMinute)->toBe(128.45);
        expect($load->fiveMinutes)->toBe(64.30);
        expect($load->fifteenMinutes)->toBe(32.75);
    });

    it('can be constructed with custom parser', function () {
        $parser = new LinuxProcLoadavgParser;
        $source = new LinuxProcLoadAverageSource(
            parser: $parser,
        );

        expect($source)->toBeInstanceOf(LinuxProcLoadAverageSource::class);
    });
});
