<?php

use Cbox\SystemMetrics\Contracts\FileReaderInterface;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\FileNotFoundException;
use Cbox\SystemMetrics\Exceptions\ParseException;
use Cbox\SystemMetrics\Sources\Cpu\LinuxProcCpuMetricsSource;
use Cbox\SystemMetrics\Support\Parser\LinuxProcStatParser;

// Test double that implements FileReaderInterface
class FakeFileReader implements FileReaderInterface
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

class FakeFileReaderNotFound implements FileReaderInterface
{
    public function read(string $path): Result
    {
        return Result::failure(new FileNotFoundException($path));
    }

    public function exists(string $path): bool
    {
        return false;
    }
}

describe('LinuxProcCpuMetricsSource', function () {
    it('can read CPU metrics successfully', function () {
        $content = "cpu  74608 2520 38618 354369 4540 0 1420 0 0 0\n"
            ."cpu0 18652 630 9654 88592 1135 0 355 0 0 0\n"
            ."cpu1 18651 631 9655 88593 1136 0 356 0 0 0\n";

        $fileReader = new FakeFileReader($content);
        $source = new LinuxProcCpuMetricsSource($fileReader);
        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeInstanceOf(CpuSnapshot::class);

        $snapshot = $result->getValue();
        expect($snapshot->total->user)->toBe(74608);
        expect($snapshot->total->nice)->toBe(2520);
        expect($snapshot->total->system)->toBe(38618);
        expect($snapshot->total->idle)->toBe(354369);
        expect($snapshot->perCore)->toHaveCount(2);
    });

    it('propagates file not found errors', function () {
        $fileReader = new FakeFileReaderNotFound;
        $source = new LinuxProcCpuMetricsSource($fileReader);
        $result = $source->read();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(FileNotFoundException::class);
    });

    it('propagates parser errors', function () {
        $fileReader = new FakeFileReader('invalid content');
        $source = new LinuxProcCpuMetricsSource($fileReader);
        $result = $source->read();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(ParseException::class);
    });

    it('handles empty proc stat file', function () {
        $fileReader = new FakeFileReader('');
        $source = new LinuxProcCpuMetricsSource($fileReader);
        $result = $source->read();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(ParseException::class);
    });

    it('handles minimal CPU data', function () {
        $content = "cpu  100 0 50 200 0 0 0 0\n";
        $fileReader = new FakeFileReader($content);
        $source = new LinuxProcCpuMetricsSource($fileReader);
        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeInstanceOf(CpuSnapshot::class);
    });

    it('handles single core system', function () {
        $content = "cpu  100 50 75 200 25 10 15 5 0 0\n"
            ."cpu0 100 50 75 200 25 10 15 5 0 0\n";

        $fileReader = new FakeFileReader($content);
        $source = new LinuxProcCpuMetricsSource($fileReader);
        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot->perCore)->toHaveCount(1);
        expect($snapshot->perCore[0]->coreIndex)->toBe(0);
    });

    it('handles multi-core system', function () {
        $content = "cpu  400 200 300 800 100 40 60 20 0 0\n"
            ."cpu0 100 50 75 200 25 10 15 5 0 0\n"
            ."cpu1 100 50 75 200 25 10 15 5 0 0\n"
            ."cpu2 100 50 75 200 25 10 15 5 0 0\n"
            ."cpu3 100 50 75 200 25 10 15 5 0 0\n";

        $fileReader = new FakeFileReader($content);
        $source = new LinuxProcCpuMetricsSource($fileReader);
        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot->perCore)->toHaveCount(4);
        expect($snapshot->perCore[0]->coreIndex)->toBe(0);
        expect($snapshot->perCore[3]->coreIndex)->toBe(3);
    });

    it('uses default dependencies when none provided', function () {
        $source = new LinuxProcCpuMetricsSource;
        expect($source)->toBeInstanceOf(LinuxProcCpuMetricsSource::class);
    });

    it('can be constructed with custom parser', function () {
        $content = "cpu  100 0 50 200 0 0 0 0\n";
        $fileReader = new FakeFileReader($content);
        $parser = new LinuxProcStatParser;
        $source = new LinuxProcCpuMetricsSource($fileReader, $parser);
        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
    });
});
