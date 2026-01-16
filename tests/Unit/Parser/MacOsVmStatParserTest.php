<?php

use Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use Cbox\SystemMetrics\Support\Parser\MacOsVmStatParser;

describe('MacOsVmStatParser', function () {
    it('can parse memory information from vm_stat', function () {
        $parser = new MacOsVmStatParser;
        $vmStatContent = <<<'VMSTAT'
Mach Virtual Memory Statistics: (page size of 16384 bytes)
Pages free:                               500000.
Pages active:                             300000.
Pages inactive:                           150000.
Pages speculative:                         50000.
Pages throttled:                               0.
Pages wired down:                         200000.
Pages purgeable count:                     10000.
VMSTAT;
        $hwMemsize = '17179869184'; // 16 GB in bytes
        $pageSize = 16384;

        $result = $parser->parse($vmStatContent, $hwMemsize, $pageSize);

        expect($result->isSuccess())->toBeTrue();

        $snapshot = $result->getValue();
        expect($snapshot)->toBeInstanceOf(MemorySnapshot::class);

        // Pages are multiplied by page size
        expect($snapshot->freeBytes)->toBe(500000 * 16384);
        expect($snapshot->totalBytes)->toBe(17179869184);
    });

    it('calculates used memory correctly', function () {
        $parser = new MacOsVmStatParser;
        $vmStatContent = <<<'VMSTAT'
Mach Virtual Memory Statistics: (page size of 4096 bytes)
Pages free:                               500000.
Pages active:                             300000.
Pages inactive:                           150000.
Pages speculative:                         50000.
Pages wired down:                         200000.
Pages occupied by compressor:              10000.
VMSTAT;
        $hwMemsize = '8589934592'; // 8 GB
        $pageSize = 4096;

        $result = $parser->parse($vmStatContent, $hwMemsize, $pageSize);

        expect($result->isSuccess())->toBeTrue();

        $snapshot = $result->getValue();
        // Used = active + wired + compressed
        $expectedUsed = (300000 + 200000 + 10000) * 4096;
        expect($snapshot->usedBytes)->toBe($expectedUsed);
    });

    it('calculates available memory correctly', function () {
        $parser = new MacOsVmStatParser;
        $vmStatContent = <<<'VMSTAT'
Mach Virtual Memory Statistics: (page size of 4096 bytes)
Pages free:                               500000.
Pages active:                             300000.
Pages inactive:                           150000.
Pages speculative:                         50000.
Pages wired down:                         200000.
VMSTAT;
        $hwMemsize = '8589934592';
        $pageSize = 4096;

        $result = $parser->parse($vmStatContent, $hwMemsize, $pageSize);

        expect($result->isSuccess())->toBeTrue();

        $snapshot = $result->getValue();
        // Available = free + inactive + speculative
        $expectedAvailable = (500000 + 150000 + 50000) * 4096;
        expect($snapshot->availableBytes)->toBe($expectedAvailable);
    });

    it('fails on empty vm_stat input', function () {
        $parser = new MacOsVmStatParser;

        $result = $parser->parse('', '8589934592', 4096);

        expect($result->isFailure())->toBeTrue();
    });

    it('fails on missing required page counts', function () {
        $parser = new MacOsVmStatParser;
        $vmStatContent = <<<'VMSTAT'
Mach Virtual Memory Statistics: (page size of 4096 bytes)
Pages free:                               500000.
VMSTAT;

        $result = $parser->parse($vmStatContent, '8589934592', 4096);

        expect($result->isFailure())->toBeTrue();
    });

    it('handles various page sizes', function () {
        $parser = new MacOsVmStatParser;
        $vmStatContent = <<<'VMSTAT'
Mach Virtual Memory Statistics: (page size of 16384 bytes)
Pages free:                               100000.
Pages active:                              50000.
Pages inactive:                            30000.
Pages wired down:                          20000.
VMSTAT;

        // Test with 16KB pages (Apple Silicon)
        $result = $parser->parse($vmStatContent, '8589934592', 16384);

        expect($result->isSuccess())->toBeTrue();

        $snapshot = $result->getValue();
        expect($snapshot->freeBytes)->toBe(100000 * 16384);
    });

    it('sets buffers to zero for macOS', function () {
        $parser = new MacOsVmStatParser;
        $vmStatContent = <<<'VMSTAT'
Mach Virtual Memory Statistics: (page size of 4096 bytes)
Pages free:                               100000.
Pages active:                              50000.
Pages inactive:                            30000.
Pages wired down:                          20000.
VMSTAT;

        $result = $parser->parse($vmStatContent, '8589934592', 4096);

        expect($result->isSuccess())->toBeTrue();

        $snapshot = $result->getValue();
        // macOS doesn't have Linux-style buffers
        expect($snapshot->buffersBytes)->toBe(0);
    });

    it('handles missing optional fields gracefully', function () {
        $parser = new MacOsVmStatParser;
        $vmStatContent = <<<'VMSTAT'
Mach Virtual Memory Statistics: (page size of 4096 bytes)
Pages free:                               100000.
Pages active:                              50000.
Pages inactive:                            30000.
Pages wired down:                          20000.
VMSTAT;

        $result = $parser->parse($vmStatContent, '8589934592', 4096);

        expect($result->isSuccess())->toBeTrue();

        $snapshot = $result->getValue();
        // Should succeed even without optional fields like speculative, purgeable, etc.
        expect($snapshot->totalBytes)->toBeGreaterThan(0);
    });
});
