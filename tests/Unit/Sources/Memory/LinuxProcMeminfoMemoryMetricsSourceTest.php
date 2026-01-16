<?php

use Cbox\SystemMetrics\Contracts\FileReaderInterface;
use Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\FileNotFoundException;
use Cbox\SystemMetrics\Exceptions\ParseException;
use Cbox\SystemMetrics\Sources\Memory\LinuxProcMeminfoMemoryMetricsSource;
use Cbox\SystemMetrics\Support\Parser\LinuxMeminfoParser;

// Test doubles
class FakeMemoryFileReader implements FileReaderInterface
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

class FakeMemoryFileReaderNotFound implements FileReaderInterface
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

describe('LinuxProcMeminfoMemoryMetricsSource', function () {
    it('can read memory metrics successfully', function () {
        $content = "MemTotal:       16384000 kB\n"
            ."MemFree:         8192000 kB\n"
            ."MemAvailable:   10240000 kB\n"
            ."Buffers:          512000 kB\n"
            ."Cached:          2048000 kB\n"
            ."SwapTotal:       4096000 kB\n"
            ."SwapFree:        2048000 kB\n";

        $fileReader = new FakeMemoryFileReader($content);
        $source = new LinuxProcMeminfoMemoryMetricsSource($fileReader);
        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeInstanceOf(MemorySnapshot::class);

        $snapshot = $result->getValue();
        expect($snapshot->totalBytes)->toBe(16777216000); // 16384000 * 1024
        expect($snapshot->freeBytes)->toBe(8388608000);   // 8192000 * 1024
        expect($snapshot->availableBytes)->toBe(10485760000); // 10240000 * 1024
        expect($snapshot->swapTotalBytes)->toBe(4194304000);    // 4096000 * 1024
        expect($snapshot->swapFreeBytes)->toBe(2097152000);     // 2048000 * 1024
    });

    it('propagates file not found errors', function () {
        $fileReader = new FakeMemoryFileReaderNotFound;
        $source = new LinuxProcMeminfoMemoryMetricsSource($fileReader);
        $result = $source->read();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(FileNotFoundException::class);
    });

    it('propagates parser errors', function () {
        $fileReader = new FakeMemoryFileReader('invalid content');
        $source = new LinuxProcMeminfoMemoryMetricsSource($fileReader);
        $result = $source->read();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(ParseException::class);
    });

    it('handles empty meminfo file', function () {
        $fileReader = new FakeMemoryFileReader('');
        $source = new LinuxProcMeminfoMemoryMetricsSource($fileReader);
        $result = $source->read();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(ParseException::class);
    });

    it('handles minimal memory data', function () {
        $content = "MemTotal:       1024000 kB\n"
            ."MemFree:         512000 kB\n"
            ."MemAvailable:    768000 kB\n";

        $fileReader = new FakeMemoryFileReader($content);
        $source = new LinuxProcMeminfoMemoryMetricsSource($fileReader);
        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeInstanceOf(MemorySnapshot::class);
    });

    it('handles systems with swap', function () {
        $content = "MemTotal:       8192000 kB\n"
            ."MemFree:        4096000 kB\n"
            ."MemAvailable:   5120000 kB\n"
            ."Buffers:         256000 kB\n"
            ."Cached:         1024000 kB\n"
            ."SwapTotal:      8192000 kB\n"
            ."SwapFree:       4096000 kB\n";

        $fileReader = new FakeMemoryFileReader($content);
        $source = new LinuxProcMeminfoMemoryMetricsSource($fileReader);
        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot->swapTotalBytes)->toBe(8388608000);
        expect($snapshot->swapFreeBytes)->toBe(4194304000);
        expect($snapshot->swapUsedBytes)->toBe(4194304000);
    });

    it('handles systems without swap', function () {
        $content = "MemTotal:       8192000 kB\n"
            ."MemFree:        4096000 kB\n"
            ."MemAvailable:   5120000 kB\n"
            ."Buffers:         256000 kB\n"
            ."Cached:         1024000 kB\n";

        $fileReader = new FakeMemoryFileReader($content);
        $source = new LinuxProcMeminfoMemoryMetricsSource($fileReader);
        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot->swapTotalBytes)->toBe(0);
        expect($snapshot->swapFreeBytes)->toBe(0);
    });

    it('uses default dependencies when none provided', function () {
        $source = new LinuxProcMeminfoMemoryMetricsSource;
        expect($source)->toBeInstanceOf(LinuxProcMeminfoMemoryMetricsSource::class);
    });

    it('can be constructed with custom parser', function () {
        $content = "MemTotal:       1024000 kB\n"
            ."MemFree:         512000 kB\n"
            ."MemAvailable:    768000 kB\n";

        $fileReader = new FakeMemoryFileReader($content);
        $parser = new LinuxMeminfoParser;
        $source = new LinuxProcMeminfoMemoryMetricsSource($fileReader, $parser);
        $result = $source->read();

        expect($result->isSuccess())->toBeTrue();
    });
});
