<?php

use Cbox\SystemMetrics\DTO\Metrics\Container\CgroupVersion;
use Cbox\SystemMetrics\DTO\Metrics\Container\ContainerLimits;

describe('ContainerLimits', function () {
    it('can be instantiated with all values', function () {
        $limits = new ContainerLimits(
            cgroupVersion: CgroupVersion::V2,
            cpuQuota: 2.0,
            memoryLimitBytes: 4_294_967_296,
            cpuUsageCores: 1.5,
            memoryUsageBytes: 2_147_483_648,
            cpuThrottledCount: 0,
            oomKillCount: 0,
        );

        expect($limits->cgroupVersion)->toBe(CgroupVersion::V2);
        expect($limits->cpuQuota)->toBe(2.0);
        expect($limits->memoryLimitBytes)->toBe(4_294_967_296);
        expect($limits->cpuUsageCores)->toBe(1.5);
        expect($limits->memoryUsageBytes)->toBe(2_147_483_648);
        expect($limits->cpuThrottledCount)->toBe(0);
        expect($limits->oomKillCount)->toBe(0);
    });

    it('detects CPU limits correctly', function () {
        $withLimit = new ContainerLimits(
            cgroupVersion: CgroupVersion::V2,
            cpuQuota: 2.0,
            memoryLimitBytes: null,
            cpuUsageCores: null,
            memoryUsageBytes: null,
            cpuThrottledCount: null,
            oomKillCount: null,
        );

        $withoutLimit = new ContainerLimits(
            cgroupVersion: CgroupVersion::V2,
            cpuQuota: null,
            memoryLimitBytes: null,
            cpuUsageCores: null,
            memoryUsageBytes: null,
            cpuThrottledCount: null,
            oomKillCount: null,
        );

        expect($withLimit->hasCpuLimit())->toBeTrue();
        expect($withoutLimit->hasCpuLimit())->toBeFalse();
    });

    it('detects memory limits correctly', function () {
        $withLimit = new ContainerLimits(
            cgroupVersion: CgroupVersion::V2,
            cpuQuota: null,
            memoryLimitBytes: 4_294_967_296,
            cpuUsageCores: null,
            memoryUsageBytes: null,
            cpuThrottledCount: null,
            oomKillCount: null,
        );

        $withoutLimit = new ContainerLimits(
            cgroupVersion: CgroupVersion::V2,
            cpuQuota: null,
            memoryLimitBytes: null,
            cpuUsageCores: null,
            memoryUsageBytes: null,
            cpuThrottledCount: null,
            oomKillCount: null,
        );

        expect($withLimit->hasMemoryLimit())->toBeTrue();
        expect($withoutLimit->hasMemoryLimit())->toBeFalse();
    });

    it('calculates CPU utilization percentage correctly', function () {
        $limits = new ContainerLimits(
            cgroupVersion: CgroupVersion::V2,
            cpuQuota: 2.0,
            memoryLimitBytes: null,
            cpuUsageCores: 1.5,
            memoryUsageBytes: null,
            cpuThrottledCount: null,
            oomKillCount: null,
        );

        expect($limits->cpuUtilizationPercentage())->toBe(75.0);
    });

    it('calculates memory utilization percentage correctly', function () {
        $limits = new ContainerLimits(
            cgroupVersion: CgroupVersion::V2,
            cpuQuota: null,
            memoryLimitBytes: 4_294_967_296,
            cpuUsageCores: null,
            memoryUsageBytes: 2_147_483_648,
            cpuThrottledCount: null,
            oomKillCount: null,
        );

        expect($limits->memoryUtilizationPercentage())->toBe(50.0);
    });

    it('calculates available CPU cores correctly', function () {
        $limits = new ContainerLimits(
            cgroupVersion: CgroupVersion::V2,
            cpuQuota: 2.0,
            memoryLimitBytes: null,
            cpuUsageCores: 1.5,
            memoryUsageBytes: null,
            cpuThrottledCount: null,
            oomKillCount: null,
        );

        expect($limits->availableCpuCores())->toBe(0.5);
    });

    it('calculates available memory bytes correctly', function () {
        $limits = new ContainerLimits(
            cgroupVersion: CgroupVersion::V2,
            cpuQuota: null,
            memoryLimitBytes: 4_294_967_296,
            cpuUsageCores: null,
            memoryUsageBytes: 2_147_483_648,
            cpuThrottledCount: null,
            oomKillCount: null,
        );

        expect($limits->availableMemoryBytes())->toBe(2_147_483_648);
    });

    it('handles CPU over-utilization correctly', function () {
        $limits = new ContainerLimits(
            cgroupVersion: CgroupVersion::V2,
            cpuQuota: 1.0,
            memoryLimitBytes: null,
            cpuUsageCores: 1.5,
            memoryUsageBytes: null,
            cpuThrottledCount: null,
            oomKillCount: null,
        );

        expect($limits->cpuUtilizationPercentage())->toBe(100.0);
        expect($limits->availableCpuCores())->toBe(0.0);
    });

    it('handles memory over-utilization correctly', function () {
        $limits = new ContainerLimits(
            cgroupVersion: CgroupVersion::V2,
            cpuQuota: null,
            memoryLimitBytes: 2_147_483_648,
            cpuUsageCores: null,
            memoryUsageBytes: 4_294_967_296,
            cpuThrottledCount: null,
            oomKillCount: null,
        );

        expect($limits->memoryUtilizationPercentage())->toBe(100.0);
        expect($limits->availableMemoryBytes())->toBe(0);
    });

    it('detects CPU throttling correctly', function () {
        $throttled = new ContainerLimits(
            cgroupVersion: CgroupVersion::V2,
            cpuQuota: null,
            memoryLimitBytes: null,
            cpuUsageCores: null,
            memoryUsageBytes: null,
            cpuThrottledCount: 150,
            oomKillCount: null,
        );

        $notThrottled = new ContainerLimits(
            cgroupVersion: CgroupVersion::V2,
            cpuQuota: null,
            memoryLimitBytes: null,
            cpuUsageCores: null,
            memoryUsageBytes: null,
            cpuThrottledCount: 0,
            oomKillCount: null,
        );

        expect($throttled->isCpuThrottled())->toBeTrue();
        expect($notThrottled->isCpuThrottled())->toBeFalse();
    });

    it('detects OOM kills correctly', function () {
        $hasKills = new ContainerLimits(
            cgroupVersion: CgroupVersion::V2,
            cpuQuota: null,
            memoryLimitBytes: null,
            cpuUsageCores: null,
            memoryUsageBytes: null,
            cpuThrottledCount: null,
            oomKillCount: 2,
        );

        $noKills = new ContainerLimits(
            cgroupVersion: CgroupVersion::V2,
            cpuQuota: null,
            memoryLimitBytes: null,
            cpuUsageCores: null,
            memoryUsageBytes: null,
            cpuThrottledCount: null,
            oomKillCount: 0,
        );

        expect($hasKills->hasOomKills())->toBeTrue();
        expect($noKills->hasOomKills())->toBeFalse();
    });

    it('handles null values gracefully', function () {
        $limits = new ContainerLimits(
            cgroupVersion: CgroupVersion::NONE,
            cpuQuota: null,
            memoryLimitBytes: null,
            cpuUsageCores: null,
            memoryUsageBytes: null,
            cpuThrottledCount: null,
            oomKillCount: null,
        );

        expect($limits->hasCpuLimit())->toBeFalse();
        expect($limits->hasMemoryLimit())->toBeFalse();
        expect($limits->cpuUtilizationPercentage())->toBeNull();
        expect($limits->memoryUtilizationPercentage())->toBeNull();
        expect($limits->availableCpuCores())->toBeNull();
        expect($limits->availableMemoryBytes())->toBeNull();
        expect($limits->isCpuThrottled())->toBeFalse();
        expect($limits->hasOomKills())->toBeFalse();
    });
});
