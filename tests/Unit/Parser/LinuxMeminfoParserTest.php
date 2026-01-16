<?php

use Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use Cbox\SystemMetrics\Support\Parser\LinuxMeminfoParser;

describe('LinuxMeminfoParser', function () {
    it('can parse memory information from meminfo', function () {
        $parser = new LinuxMeminfoParser;
        $meminfoContent = <<<'MEMINFO'
MemTotal:       16384000 kB
MemFree:         8192000 kB
MemAvailable:    10240000 kB
Buffers:          512000 kB
Cached:          2048000 kB
SwapTotal:       4096000 kB
SwapFree:        3072000 kB
MEMINFO;

        $result = $parser->parse($meminfoContent);

        expect($result->isSuccess())->toBeTrue();

        $snapshot = $result->getValue();
        expect($snapshot)->toBeInstanceOf(MemorySnapshot::class);
        expect($snapshot->totalBytes)->toBe(16384000 * 1024);
        expect($snapshot->freeBytes)->toBe(8192000 * 1024);
        expect($snapshot->availableBytes)->toBe(10240000 * 1024);
        expect($snapshot->buffersBytes)->toBe(512000 * 1024);
        expect($snapshot->cachedBytes)->toBe(2048000 * 1024);
        expect($snapshot->swapTotalBytes)->toBe(4096000 * 1024);
        expect($snapshot->swapFreeBytes)->toBe(3072000 * 1024);
    });

    it('calculates derived values correctly', function () {
        $parser = new LinuxMeminfoParser;
        $meminfoContent = <<<'MEMINFO'
MemTotal:       16384000 kB
MemFree:         8192000 kB
MemAvailable:    10240000 kB
Buffers:          512000 kB
Cached:          2048000 kB
SwapTotal:       4096000 kB
SwapFree:        3072000 kB
MEMINFO;

        $result = $parser->parse($meminfoContent);

        expect($result->isSuccess())->toBeTrue();

        $snapshot = $result->getValue();
        // usedBytes = total - available (more accurate than total - free - buffers - cached)
        $expectedUsed = (16384000 - 10240000) * 1024;
        expect($snapshot->usedBytes)->toBe($expectedUsed);

        // swapUsed = swapTotal - swapFree
        $expectedSwapUsed = (4096000 - 3072000) * 1024;
        expect($snapshot->swapUsedBytes)->toBe($expectedSwapUsed);

        // Test percentage calculations
        expect($snapshot->usedPercentage())->toBeFloat();
        expect($snapshot->availablePercentage())->toBeFloat();
    });

    it('handles missing optional fields gracefully', function () {
        $parser = new LinuxMeminfoParser;
        $meminfoContent = <<<'MEMINFO'
MemTotal:       16384000 kB
MemFree:         8192000 kB
MemAvailable:    10240000 kB
MEMINFO;

        $result = $parser->parse($meminfoContent);

        expect($result->isSuccess())->toBeTrue();

        $snapshot = $result->getValue();
        expect($snapshot->buffersBytes)->toBe(0);
        expect($snapshot->cachedBytes)->toBe(0);
        expect($snapshot->swapTotalBytes)->toBe(0);
        expect($snapshot->swapFreeBytes)->toBe(0);
    });

    it('fails on empty input', function () {
        $parser = new LinuxMeminfoParser;

        $result = $parser->parse('');

        expect($result->isFailure())->toBeTrue();
    });

    it('fails on missing required fields', function () {
        $parser = new LinuxMeminfoParser;
        $meminfoContent = "MemTotal:       16384000 kB\n";

        $result = $parser->parse($meminfoContent);

        expect($result->isFailure())->toBeTrue();
    });

    it('handles various meminfo formats', function () {
        $parser = new LinuxMeminfoParser;
        $meminfoContent = <<<'MEMINFO'
MemTotal:       16384000 kB
MemFree:         8192000 kB
MemAvailable:    10240000 kB
Buffers:          512000 kB
Cached:          2048000 kB
SwapCached:            0 kB
Active:          4096000 kB
Inactive:        2048000 kB
SwapTotal:       4096000 kB
SwapFree:        3072000 kB
Dirty:              1000 kB
Writeback:             0 kB
MEMINFO;

        $result = $parser->parse($meminfoContent);

        expect($result->isSuccess())->toBeTrue();
    });
});
