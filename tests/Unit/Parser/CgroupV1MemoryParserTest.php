<?php

declare(strict_types=1);

use Cbox\SystemMetrics\Support\Parser\Cgroup\V1\CgroupV1MemoryParser;
use Cbox\SystemMetrics\Support\Parser\Cgroup\V1\CgroupV1PathResolver;

describe('CgroupV1MemoryParser OOM kills', function () {
    it('reads the oom_kill counter', function () {
        $parser = new CgroupV1MemoryParser(new CgroupV1PathResolver);

        expect($parser->oomKillsFromOomControl("oom_kill_disable 0\nunder_oom 0\noom_kill 7\n"))->toBe(7);
    });

    it('does not report under_oom as a kill count', function () {
        $parser = new CgroupV1MemoryParser(new CgroupV1PathResolver);

        // A cgroup stalled at its limit with the OOM killer disabled, no kills.
        expect($parser->oomKillsFromOomControl("oom_kill_disable 1\nunder_oom 1\noom_kill 0\n"))->toBe(0);
    });

    it('returns null on kernels without the oom_kill counter', function () {
        $parser = new CgroupV1MemoryParser(new CgroupV1PathResolver);

        expect($parser->oomKillsFromOomControl("oom_kill_disable 0\nunder_oom 1\n"))->toBeNull();
    });
});

describe('CgroupV1MemoryParser working set', function () {
    // Real cgroup v1 memory.stat layout: local counters first, then the
    // hierarchical total_* counters that match memory.usage_in_bytes.
    $memoryStat = <<<'STAT'
cache 2147483648
rss 1048576000
mapped_file 104857600
inactive_anon 0
active_anon 1048576000
inactive_file 1073741824
active_file 536870912
unevictable 0
hierarchical_memory_limit 4294967296
total_cache 2147483648
total_rss 1048576000
total_inactive_anon 0
total_active_anon 1048576000
total_inactive_file 1610612736
total_active_file 536870912
total_unevictable 0

STAT;

    it('subtracts total_inactive_file from usage', function () use ($memoryStat) {
        $parser = new CgroupV1MemoryParser(new CgroupV1PathResolver);

        expect($parser->workingSetFromMemoryStat(3_221_225_472, $memoryStat))->toBe(1_610_612_736);
    });

    it('ignores the local inactive_file line', function () {
        $parser = new CgroupV1MemoryParser(new CgroupV1PathResolver);

        // Only the hierarchical counter matches the hierarchical usage; a
        // memory.stat without it is unreadable, not "use the local value".
        expect($parser->workingSetFromMemoryStat(3_221_225_472, "inactive_file 1073741824\nactive_file 536870912\n"))->toBeNull();
    });

    it('floors the working set at zero', function () use ($memoryStat) {
        $parser = new CgroupV1MemoryParser(new CgroupV1PathResolver);

        // usage_in_bytes is batched per CPU and can trail memory.stat briefly.
        expect($parser->workingSetFromMemoryStat(1_000_000_000, $memoryStat))->toBe(0);
    });

    it('returns null when memory.stat is unreadable', function () {
        $parser = new CgroupV1MemoryParser(new CgroupV1PathResolver);

        expect($parser->workingSetFromMemoryStat(3_221_225_472, null))->toBeNull();
    });

    it('returns null when usage is unknown', function () use ($memoryStat) {
        $parser = new CgroupV1MemoryParser(new CgroupV1PathResolver);

        expect($parser->workingSetFromMemoryStat(null, $memoryStat))->toBeNull()
            ->and($parser->parseWorkingSet(null))->toBeNull();
    });
});
