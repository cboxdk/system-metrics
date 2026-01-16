<?php

use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuCoreTimes;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use Cbox\SystemMetrics\Support\Parser\MacOsSysctlParser;

describe('MacOsSysctlParser', function () {
    it('can parse total CPU times from kern.cp_time', function () {
        $parser = new MacOsSysctlParser;
        $cpTime = '74608 2520 38618 354369'; // macOS only has 4 values
        $cpTimes = "18652 630 9654 88592\n18651 631 9655 88593\n18652 629 9654 88592\n18653 630 9655 88592";

        $result = $parser->parseSnapshot($cpTime, $cpTimes);

        expect($result->isSuccess())->toBeTrue();

        $snapshot = $result->getValue();
        expect($snapshot)->toBeInstanceOf(CpuSnapshot::class);
        expect($snapshot->total->user)->toBe(74608);
        expect($snapshot->total->nice)->toBe(2520);
        expect($snapshot->total->system)->toBe(38618);
        expect($snapshot->total->idle)->toBe(354369);
        expect($snapshot->total->iowait)->toBe(0); // Not available on macOS
    });

    it('can parse per-core CPU times from kern.cp_times', function () {
        $parser = new MacOsSysctlParser;
        $cpTime = '74608 2520 38618 354369';
        $cpTimes = "18652 630 9654 88592\n18651 631 9655 88593\n18652 629 9654 88592\n18653 630 9655 88592";

        $result = $parser->parseSnapshot($cpTime, $cpTimes);

        expect($result->isSuccess())->toBeTrue();

        $snapshot = $result->getValue();
        expect($snapshot->perCore)->toHaveCount(4);
        expect($snapshot->perCore[0])->toBeInstanceOf(CpuCoreTimes::class);
        expect($snapshot->perCore[0]->coreIndex)->toBe(0);
        expect($snapshot->perCore[0]->times->user)->toBe(18652);
        expect($snapshot->perCore[0]->times->nice)->toBe(630);
        expect($snapshot->perCore[1]->coreIndex)->toBe(1);
        expect($snapshot->perCore[3]->coreIndex)->toBe(3);
    });

    it('sets unused fields to zero for macOS format', function () {
        $parser = new MacOsSysctlParser;
        $cpTime = '100 50 75 200';
        $cpTimes = '100 50 75 200';

        $result = $parser->parseSnapshot($cpTime, $cpTimes);

        expect($result->isSuccess())->toBeTrue();

        $snapshot = $result->getValue();
        expect($snapshot->total->irq)->toBe(0);
        expect($snapshot->total->softirq)->toBe(0);
        expect($snapshot->total->steal)->toBe(0);
    });

    it('fails on empty cp_time input', function () {
        $parser = new MacOsSysctlParser;

        $result = $parser->parseSnapshot('', '100 50 75 200');

        expect($result->isFailure())->toBeTrue();
    });

    it('fails on invalid cp_time format', function () {
        $parser = new MacOsSysctlParser;

        $result = $parser->parseSnapshot('invalid', '100 50 75 200 25');

        expect($result->isFailure())->toBeTrue();
    });

    it('fails on insufficient values in cp_time', function () {
        $parser = new MacOsSysctlParser;

        $result = $parser->parseSnapshot('100 50', '100 50 75 200 25');

        expect($result->isFailure())->toBeTrue();
    });

    it('fails on empty cp_times input', function () {
        $parser = new MacOsSysctlParser;

        $result = $parser->parseSnapshot('100 50 75 200', '');

        expect($result->isFailure())->toBeTrue();
    });

    it('fails on invalid per-core format', function () {
        $parser = new MacOsSysctlParser;

        $result = $parser->parseSnapshot('100 50 75 200 25', 'invalid core data');

        expect($result->isFailure())->toBeTrue();
    });
});
