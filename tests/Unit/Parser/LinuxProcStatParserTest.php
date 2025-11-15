<?php

use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuCoreTimes;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use PHPeek\SystemMetrics\Support\Parser\LinuxProcStatParser;

describe('LinuxProcStatParser', function () {
    it('can parse total CPU times from first line', function () {
        $parser = new LinuxProcStatParser;
        $procStatContent = "cpu  74608 2520 38618 354369 4540 0 1420 0 0 0\n"
            ."cpu0 18652 630 9654 88592 1135 0 355 0 0 0\n";

        $result = $parser->parse($procStatContent);

        expect($result->isSuccess())->toBeTrue();

        $snapshot = $result->getValue();
        expect($snapshot)->toBeInstanceOf(CpuSnapshot::class);
        expect($snapshot->total->user)->toBe(74608);
        expect($snapshot->total->nice)->toBe(2520);
        expect($snapshot->total->system)->toBe(38618);
        expect($snapshot->total->idle)->toBe(354369);
        expect($snapshot->total->iowait)->toBe(4540);
        expect($snapshot->total->irq)->toBe(0);
        expect($snapshot->total->softirq)->toBe(1420);
        expect($snapshot->total->steal)->toBe(0);
    });

    it('can parse per-core CPU times', function () {
        $parser = new LinuxProcStatParser;
        $procStatContent = "cpu  74608 2520 38618 354369 4540 0 1420 0 0 0\n"
            ."cpu0 18652 630 9654 88592 1135 0 355 0 0 0\n"
            ."cpu1 18651 631 9655 88593 1136 0 356 0 0 0\n"
            ."cpu2 18652 629 9654 88592 1134 0 354 0 0 0\n"
            ."cpu3 18653 630 9655 88592 1135 0 355 0 0 0\n";

        $result = $parser->parse($procStatContent);

        expect($result->isSuccess())->toBeTrue();

        $snapshot = $result->getValue();
        expect($snapshot->perCore)->toHaveCount(4);
        expect($snapshot->perCore[0])->toBeInstanceOf(CpuCoreTimes::class);
        expect($snapshot->perCore[0]->coreIndex)->toBe(0);
        expect($snapshot->perCore[0]->times->user)->toBe(18652);
        expect($snapshot->perCore[1]->coreIndex)->toBe(1);
        expect($snapshot->perCore[3]->coreIndex)->toBe(3);
    });

    it('can calculate total and busy time', function () {
        $parser = new LinuxProcStatParser;
        $procStatContent = "cpu  100 50 75 200 25 10 15 5 0 0\n";

        $result = $parser->parse($procStatContent);

        expect($result->isSuccess())->toBeTrue();

        $snapshot = $result->getValue();
        expect($snapshot->total->total())->toBe(480); // sum of all values
        expect($snapshot->total->busy())->toBe(255); // total - idle - iowait
    });

    it('handles minimal proc stat format', function () {
        $parser = new LinuxProcStatParser;
        $procStatContent = "cpu  100 0 50 200 0 0 0 0\n";

        $result = $parser->parse($procStatContent);

        expect($result->isSuccess())->toBeTrue();
    });

    it('fails on empty input', function () {
        $parser = new LinuxProcStatParser;

        $result = $parser->parse('');

        expect($result->isFailure())->toBeTrue();
    });

    it('fails on invalid format', function () {
        $parser = new LinuxProcStatParser;

        $result = $parser->parse('invalid data');

        expect($result->isFailure())->toBeTrue();
    });

    it('fails on missing cpu line', function () {
        $parser = new LinuxProcStatParser;
        $procStatContent = "cpu0 100 0 50 200 0 0 0 0\n";

        $result = $parser->parse($procStatContent);

        expect($result->isFailure())->toBeTrue();
    });
});
