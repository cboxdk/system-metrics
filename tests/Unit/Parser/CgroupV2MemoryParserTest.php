<?php

declare(strict_types=1);

use Cbox\SystemMetrics\Support\Parser\Cgroup\V2\CgroupV2MemoryParser;
use Cbox\SystemMetrics\Support\Parser\Cgroup\V2\CgroupV2PathResolver;

describe('CgroupV2MemoryParser working set', function () {
    // Real cgroup v2 memory.stat layout (abridged).
    $memoryStat = <<<'STAT'
anon 1048576000
file 2147483648
kernel 52428800
sock 0
shmem 0
file_mapped 104857600
file_dirty 0
file_writeback 0
inactive_anon 0
active_anon 1048576000
inactive_file 1610612736
active_file 536870912
unevictable 0
slab_reclaimable 20971520
slab_unreclaimable 10485760
workingset_refault_file 0

STAT;

    it('subtracts inactive_file from usage', function () use ($memoryStat) {
        $parser = new CgroupV2MemoryParser(new CgroupV2PathResolver);

        expect($parser->workingSetFromMemoryStat(3_221_225_472, $memoryStat))->toBe(1_610_612_736);
    });

    it('does not match a line that only ends in inactive_file', function () {
        $parser = new CgroupV2MemoryParser(new CgroupV2PathResolver);

        expect($parser->workingSetFromMemoryStat(3_221_225_472, "total_inactive_file 1610612736\n"))->toBeNull();
    });

    it('returns null when inactive_file is missing', function () {
        $parser = new CgroupV2MemoryParser(new CgroupV2PathResolver);

        expect($parser->workingSetFromMemoryStat(3_221_225_472, "anon 1048576000\nfile 2147483648\n"))->toBeNull();
    });

    it('floors the working set at zero', function () use ($memoryStat) {
        $parser = new CgroupV2MemoryParser(new CgroupV2PathResolver);

        expect($parser->workingSetFromMemoryStat(1_000_000_000, $memoryStat))->toBe(0);
    });

    it('returns null when memory.stat is unreadable', function () {
        $parser = new CgroupV2MemoryParser(new CgroupV2PathResolver);

        expect($parser->workingSetFromMemoryStat(3_221_225_472, null))->toBeNull();
    });

    it('returns null when usage is unknown', function () use ($memoryStat) {
        $parser = new CgroupV2MemoryParser(new CgroupV2PathResolver);

        expect($parser->workingSetFromMemoryStat(null, $memoryStat))->toBeNull()
            ->and($parser->parseWorkingSet(null))->toBeNull();
    });
});
