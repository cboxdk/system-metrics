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
