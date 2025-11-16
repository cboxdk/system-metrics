<?php

use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuCoreTimes;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;

describe('CpuSnapshot Finder Methods', function () {
    beforeEach(function () {
        $this->cores = [
            new CpuCoreTimes(
                coreIndex: 0,
                times: new CpuTimes(
                    user: 1000,
                    nice: 0,
                    system: 500,
                    idle: 8500,
                    iowait: 0,
                    irq: 0,
                    softirq: 0,
                    steal: 0,
                ), // 15% busy
            ),
            new CpuCoreTimes(
                coreIndex: 1,
                times: new CpuTimes(
                    user: 3000,
                    nice: 0,
                    system: 1000,
                    idle: 6000,
                    iowait: 0,
                    irq: 0,
                    softirq: 0,
                    steal: 0,
                ), // 40% busy
            ),
            new CpuCoreTimes(
                coreIndex: 2,
                times: new CpuTimes(
                    user: 500,
                    nice: 0,
                    system: 100,
                    idle: 9400,
                    iowait: 0,
                    irq: 0,
                    softirq: 0,
                    steal: 0,
                ), // 6% busy
            ),
            new CpuCoreTimes(
                coreIndex: 3,
                times: new CpuTimes(
                    user: 5000,
                    nice: 0,
                    system: 3000,
                    idle: 2000,
                    iowait: 0,
                    irq: 0,
                    softirq: 0,
                    steal: 0,
                ), // 80% busy
            ),
        ];

        $this->snapshot = new CpuSnapshot(
            total: new CpuTimes(
                user: 9500,
                nice: 0,
                system: 4600,
                idle: 25900,
                iowait: 0,
                irq: 0,
                softirq: 0,
                steal: 0,
            ),
            perCore: $this->cores,
        );
    });

    describe('findCore', function () {
        it('finds core by index', function () {
            $core = $this->snapshot->findCore(0);

            expect($core)->not->toBeNull();
            expect($core->coreIndex)->toBe(0);
        });

        it('finds core by different index', function () {
            $core = $this->snapshot->findCore(2);

            expect($core)->not->toBeNull();
            expect($core->coreIndex)->toBe(2);
        });

        it('returns null for non-existent core index', function () {
            $core = $this->snapshot->findCore(999);

            expect($core)->toBeNull();
        });
    });

    describe('findBusyCores', function () {
        it('finds cores above 50% busy threshold', function () {
            $cores = $this->snapshot->findBusyCores(50.0);

            expect($cores)->toHaveCount(1);
            expect($cores[0]->coreIndex)->toBe(3); // 80% busy
        });

        it('finds cores above 10% busy threshold', function () {
            $cores = $this->snapshot->findBusyCores(10.0);

            expect($cores)->toHaveCount(3);
            $indices = array_map(fn ($c) => $c->coreIndex, $cores);
            expect($indices)->toContain(0); // 15%
            expect($indices)->toContain(1); // 40%
            expect($indices)->toContain(3); // 80%
        });

        it('finds no cores above 90% busy threshold', function () {
            $cores = $this->snapshot->findBusyCores(90.0);

            expect($cores)->toBeEmpty();
        });

        it('returns re-indexed array', function () {
            $cores = $this->snapshot->findBusyCores(10.0);

            expect(array_keys($cores))->toBe([0, 1, 2]);
        });
    });

    describe('findIdleCores', function () {
        it('finds cores above 90% idle threshold', function () {
            $cores = $this->snapshot->findIdleCores(90.0);

            expect($cores)->toHaveCount(1);
            expect($cores[0]->coreIndex)->toBe(2); // 94% idle
        });

        it('finds cores above 80% idle threshold', function () {
            $cores = $this->snapshot->findIdleCores(80.0);

            expect($cores)->toHaveCount(2);
            $indices = array_map(fn ($c) => $c->coreIndex, $cores);
            expect($indices)->toContain(0); // 85% idle
            expect($indices)->toContain(2); // 94% idle
        });

        it('finds no cores above 95% idle threshold', function () {
            $cores = $this->snapshot->findIdleCores(95.0);

            expect($cores)->toBeEmpty();
        });

        it('returns re-indexed array', function () {
            $cores = $this->snapshot->findIdleCores(80.0);

            expect(array_keys($cores))->toBe([0, 1]);
        });
    });

    describe('busiestCore', function () {
        it('returns the core with highest busy percentage', function () {
            $core = $this->snapshot->busiestCore();

            expect($core)->not->toBeNull();
            expect($core->coreIndex)->toBe(3); // 80% busy
        });

        it('returns null for empty core array', function () {
            $snapshot = new CpuSnapshot(
                total: new CpuTimes(0, 0, 0, 0, 0, 0, 0, 0),
                perCore: [],
            );

            expect($snapshot->busiestCore())->toBeNull();
        });

        it('returns first core when all have zero time', function () {
            $cores = [
                new CpuCoreTimes(0, new CpuTimes(0, 0, 0, 0, 0, 0, 0, 0)),
                new CpuCoreTimes(1, new CpuTimes(0, 0, 0, 0, 0, 0, 0, 0)),
            ];

            $snapshot = new CpuSnapshot(
                total: new CpuTimes(0, 0, 0, 0, 0, 0, 0, 0),
                perCore: $cores,
            );

            $core = $snapshot->busiestCore();
            expect($core->coreIndex)->toBe(0);
        });
    });

    describe('idlestCore', function () {
        it('returns the core with lowest busy percentage', function () {
            $core = $this->snapshot->idlestCore();

            expect($core)->not->toBeNull();
            expect($core->coreIndex)->toBe(2); // 6% busy = 94% idle
        });

        it('returns null for empty core array', function () {
            $snapshot = new CpuSnapshot(
                total: new CpuTimes(0, 0, 0, 0, 0, 0, 0, 0),
                perCore: [],
            );

            expect($snapshot->idlestCore())->toBeNull();
        });

        it('returns first core when all have zero time', function () {
            $cores = [
                new CpuCoreTimes(0, new CpuTimes(0, 0, 0, 0, 0, 0, 0, 0)),
                new CpuCoreTimes(1, new CpuTimes(0, 0, 0, 0, 0, 0, 0, 0)),
            ];

            $snapshot = new CpuSnapshot(
                total: new CpuTimes(0, 0, 0, 0, 0, 0, 0, 0),
                perCore: $cores,
            );

            $core = $snapshot->idlestCore();
            expect($core->coreIndex)->toBe(0);
        });
    });

    describe('calculateBusyPercentage helper', function () {
        it('handles zero total time correctly in finder methods', function () {
            $cores = [
                new CpuCoreTimes(0, new CpuTimes(0, 0, 0, 0, 0, 0, 0, 0)),
            ];

            $snapshot = new CpuSnapshot(
                total: new CpuTimes(0, 0, 0, 0, 0, 0, 0, 0),
                perCore: $cores,
            );

            // Should not throw, should handle gracefully
            $busyCores = $snapshot->findBusyCores(10.0);
            expect($busyCores)->toBeEmpty();
        });
    });
});
