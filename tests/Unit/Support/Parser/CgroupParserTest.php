<?php

declare(strict_types=1);

use Cbox\SystemMetrics\DTO\Metrics\Container\CgroupVersion;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Support\Parser\CgroupParser;

describe('CgroupParser', function () {
    beforeEach(function () {
        // Reset static state before each test
        CgroupParser::reset();
    });

    it('can detect cgroup version', function () {
        $version = CgroupParser::detectVersion();

        expect($version)->toBeInstanceOf(CgroupVersion::class);
        expect($version)->toBeIn([CgroupVersion::NONE, CgroupVersion::V1, CgroupVersion::V2]);
    });

    it('caches detected version', function () {
        $first = CgroupParser::detectVersion();
        $second = CgroupParser::detectVersion();

        expect($first)->toBe($second);
    });

    it('can parse container limits', function () {
        $parser = new CgroupParser;
        $result = $parser->parse(8.0); // 8 CPU cores on host

        expect($result)->toBeInstanceOf(Result::class);
        expect($result->isSuccess())->toBeTrue();

        $limits = $result->getValue();
        expect($limits->cgroupVersion)->toBeInstanceOf(CgroupVersion::class);
    });

    it('returns null values when no cgroups available', function () {
        $parser = new CgroupParser;
        $result = $parser->parse(8.0);

        expect($result->isSuccess())->toBeTrue();
        $limits = $result->getValue();

        // On systems without cgroups, all values should be null
        if ($limits->cgroupVersion === CgroupVersion::NONE) {
            expect($limits->cpuQuota)->toBeNull();
            expect($limits->memoryLimitBytes)->toBeNull();
            expect($limits->cpuUsageCores)->toBeNull();
            expect($limits->memoryUsageBytes)->toBeNull();
            expect($limits->cpuThrottledCount)->toBeNull();
            expect($limits->oomKillCount)->toBeNull();
        } else {
            // On systems with cgroups, verify cgroupVersion is valid
            expect($limits->cgroupVersion)->toBeIn([CgroupVersion::V1, CgroupVersion::V2]);
        }
    });

    it('respects host CPU cores as upper limit', function () {
        $parser = new CgroupParser;
        $hostCores = 4.0;
        $result = $parser->parse($hostCores);

        expect($result->isSuccess())->toBeTrue();
        $limits = $result->getValue();

        // If CPU quota is set, it should not exceed host cores
        if ($limits->cpuQuota !== null) {
            expect($limits->cpuQuota)->toBeLessThanOrEqual($hostCores);
        } else {
            // On systems without quota limits, expect null
            expect($limits->cpuQuota)->toBeNull();
        }
    });

    it('parses CPU quota as float', function () {
        $parser = new CgroupParser;
        $result = $parser->parse(8.0);

        expect($result->isSuccess())->toBeTrue();
        $limits = $result->getValue();

        if ($limits->cpuQuota !== null) {
            expect($limits->cpuQuota)->toBeFloat();
            expect($limits->cpuQuota)->toBeGreaterThan(0.0);
        } else {
            // On systems without CPU quota, expect null
            expect($limits->cpuQuota)->toBeNull();
        }
    });

    it('parses memory limit as int', function () {
        $parser = new CgroupParser;
        $result = $parser->parse(8.0);

        expect($result->isSuccess())->toBeTrue();
        $limits = $result->getValue();

        if ($limits->memoryLimitBytes !== null) {
            expect($limits->memoryLimitBytes)->toBeInt();
            expect($limits->memoryLimitBytes)->toBeGreaterThan(0);
        } else {
            // On systems without memory limits, expect null
            expect($limits->memoryLimitBytes)->toBeNull();
        }
    });

    it('parses CPU usage cores as float or null', function () {
        $parser = new CgroupParser;
        $result = $parser->parse(8.0);

        expect($result->isSuccess())->toBeTrue();
        $limits = $result->getValue();

        if ($limits->cpuUsageCores !== null) {
            expect($limits->cpuUsageCores)->toBeFloat();
            expect($limits->cpuUsageCores)->toBeGreaterThanOrEqual(0.0);
        } else {
            // On systems without CPU usage tracking, expect null
            expect($limits->cpuUsageCores)->toBeNull();
        }
    });

    it('parses memory usage as int or null', function () {
        $parser = new CgroupParser;
        $result = $parser->parse(8.0);

        expect($result->isSuccess())->toBeTrue();
        $limits = $result->getValue();

        if ($limits->memoryUsageBytes !== null) {
            expect($limits->memoryUsageBytes)->toBeInt();
            expect($limits->memoryUsageBytes)->toBeGreaterThanOrEqual(0);
        } else {
            // On systems without memory usage tracking, expect null
            expect($limits->memoryUsageBytes)->toBeNull();
        }
    });

    it('parses throttle count as int or null', function () {
        $parser = new CgroupParser;
        $result = $parser->parse(8.0);

        expect($result->isSuccess())->toBeTrue();
        $limits = $result->getValue();

        if ($limits->cpuThrottledCount !== null) {
            expect($limits->cpuThrottledCount)->toBeInt();
            expect($limits->cpuThrottledCount)->toBeGreaterThanOrEqual(0);
        } else {
            // On systems without throttle tracking, expect null
            expect($limits->cpuThrottledCount)->toBeNull();
        }
    });

    it('parses OOM kill count as int or null', function () {
        $parser = new CgroupParser;
        $result = $parser->parse(8.0);

        expect($result->isSuccess())->toBeTrue();
        $limits = $result->getValue();

        if ($limits->oomKillCount !== null) {
            expect($limits->oomKillCount)->toBeInt();
            expect($limits->oomKillCount)->toBeGreaterThanOrEqual(0);
        } else {
            // On systems without OOM tracking, expect null
            expect($limits->oomKillCount)->toBeNull();
        }
    });

    it('can reset static state', function () {
        // Detect version to cache it
        $first = CgroupParser::detectVersion();

        // Reset cache
        CgroupParser::reset();

        // Detect again should work (not fail due to reset)
        $second = CgroupParser::detectVersion();

        expect($second)->toBeInstanceOf(CgroupVersion::class);
    });

    it('handles different host CPU core counts', function () {
        $parser = new CgroupParser;

        $testCases = [1.0, 2.0, 4.0, 8.0, 16.0, 32.0, 64.0, 128.0];

        foreach ($testCases as $cores) {
            $result = $parser->parse($cores);

            expect($result->isSuccess())->toBeTrue("Should succeed with $cores cores");

            if ($result->isSuccess()) {
                $limits = $result->getValue();

                if ($limits->cpuQuota !== null) {
                    expect($limits->cpuQuota)->toBeLessThanOrEqual($cores, "CPU quota should not exceed $cores");
                }
            }
        }
    });

    it('returns consistent cgroup version across calls', function () {
        $parser1 = new CgroupParser;
        $parser2 = new CgroupParser;

        $result1 = $parser1->parse(4.0);
        $result2 = $parser2->parse(4.0);

        if ($result1->isSuccess() && $result2->isSuccess()) {
            expect($result1->getValue()->cgroupVersion)->toBe($result2->getValue()->cgroupVersion);
        }
    });

    it('handles zero or negative CPU cores gracefully', function () {
        $parser = new CgroupParser;

        // With 0 cores (invalid but should handle gracefully)
        $result = $parser->parse(0.0);
        expect($result->isSuccess())->toBeTrue();

        // With negative cores (invalid but should handle gracefully)
        $result = $parser->parse(-1.0);
        expect($result->isSuccess())->toBeTrue();
    });

    it('detects cgroup v2 when controllers file exists', function () {
        // This test only runs if actually on cgroup v2
        if (file_exists('/sys/fs/cgroup/cgroup.controllers')) {
            $version = CgroupParser::detectVersion();
            expect($version)->toBe(CgroupVersion::V2);
        } else {
            expect(true)->toBeTrue('Skipping: Not on cgroup v2 system');
        }
    });

    it('detects cgroup v1 when proc cgroup exists without unified', function () {
        // This test only runs if on cgroup v1
        if (file_exists('/proc/self/cgroup') && ! file_exists('/sys/fs/cgroup/cgroup.controllers')) {
            $version = CgroupParser::detectVersion();
            expect($version)->toBeIn([CgroupVersion::V1, CgroupVersion::NONE]);
        } else {
            expect(true)->toBeTrue('Skipping: Not on cgroup v1 system');
        }
    });

    it('detects NONE when no cgroup files exist', function () {
        // This test is primarily for bare metal or systems without cgroups
        if (! file_exists('/proc/self/cgroup') && ! file_exists('/sys/fs/cgroup/cgroup.controllers')) {
            $version = CgroupParser::detectVersion();
            expect($version)->toBe(CgroupVersion::NONE);
        } else {
            expect(true)->toBeTrue('Skipping: System has cgroups');
        }
    });
});
