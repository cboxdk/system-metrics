<?php

use Cbox\SystemMetrics\DTO\Metrics\Storage\DiskIOStats;
use Cbox\SystemMetrics\Support\Parser\MacOsIostatParser;

describe('MacOsIostatParser', function () {
    it('can parse macOS iostat -Id output', function () {
        $parser = new MacOsIostatParser;
        $iostatOutput = <<<'IOSTAT'
          disk0           disk1           disk2
    KB/t tps  MB/s     KB/t tps  MB/s     KB/t tps  MB/s
   16.47 123  1.98    32.94  45  1.45    64.00  10  0.62
IOSTAT;

        $result = $parser->parse($iostatOutput);

        expect($result->isSuccess())->toBeTrue();

        $diskStats = $result->getValue();
        expect($diskStats)->toHaveCount(3);

        // Note: macOS iostat provides rates, not cumulative counters
        // So we expect zeros for all counters
        $disk0 = $diskStats[0];
        expect($disk0)->toBeInstanceOf(DiskIOStats::class);
        expect($disk0->device)->toBe('disk0');
        expect($disk0->readsCompleted)->toBe(0);
        expect($disk0->readBytes)->toBe(0);
        expect($disk0->writesCompleted)->toBe(0);
        expect($disk0->writeBytes)->toBe(0);
        expect($disk0->ioTimeMs)->toBe(0);
        expect($disk0->weightedIOTimeMs)->toBe(0);
    });

    it('extracts device names correctly', function () {
        $parser = new MacOsIostatParser;
        $iostatOutput = <<<'IOSTAT'
          disk0           disk1           disk2
    KB/t tps  MB/s     KB/t tps  MB/s     KB/t tps  MB/s
   16.47 123  1.98    32.94  45  1.45    64.00  10  0.62
IOSTAT;

        $result = $parser->parse($iostatOutput);
        $diskStats = $result->getValue();

        expect($diskStats[0]->device)->toBe('disk0');
        expect($diskStats[1]->device)->toBe('disk1');
        expect($diskStats[2]->device)->toBe('disk2');
    });

    it('handles single disk output', function () {
        $parser = new MacOsIostatParser;
        $iostatOutput = <<<'IOSTAT'
          disk0
    KB/t tps  MB/s
   16.47 123  1.98
IOSTAT;

        $result = $parser->parse($iostatOutput);
        $diskStats = $result->getValue();

        expect($diskStats)->toHaveCount(1);
        expect($diskStats[0]->device)->toBe('disk0');
    });

    it('returns zeros for all counter fields', function () {
        $parser = new MacOsIostatParser;
        $iostatOutput = <<<'IOSTAT'
          disk0           disk1
    KB/t tps  MB/s     KB/t tps  MB/s
   16.47 123  1.98    32.94  45  1.45
IOSTAT;

        $result = $parser->parse($iostatOutput);
        $diskStats = $result->getValue();

        foreach ($diskStats as $disk) {
            expect($disk->readsCompleted)->toBe(0);
            expect($disk->readBytes)->toBe(0);
            expect($disk->writesCompleted)->toBe(0);
            expect($disk->writeBytes)->toBe(0);
            expect($disk->ioTimeMs)->toBe(0);
            expect($disk->weightedIOTimeMs)->toBe(0);
        }
    });

    it('handles empty iostat output', function () {
        $parser = new MacOsIostatParser;

        $result = $parser->parse('');

        // Parser fails on empty input
        expect($result->isFailure())->toBeTrue();
    });

    it('fails on invalid iostat format', function () {
        $parser = new MacOsIostatParser;
        $iostatOutput = "invalid output here\n";

        $result = $parser->parse($iostatOutput);

        expect($result->isFailure())->toBeTrue();
    });

    it('handles iostat output with extra whitespace', function () {
        $parser = new MacOsIostatParser;
        $iostatOutput = <<<'IOSTAT'
          disk0             disk1
    KB/t tps  MB/s     KB/t tps  MB/s
   16.47 123  1.98    32.94  45  1.45
IOSTAT;

        $result = $parser->parse($iostatOutput);
        $diskStats = $result->getValue();

        expect($diskStats)->toHaveCount(2);
        expect($diskStats[0]->device)->toBe('disk0');
        expect($diskStats[1]->device)->toBe('disk1');
    });
});
