<?php

use Cbox\SystemMetrics\DTO\Metrics\LimitSource;
use Cbox\SystemMetrics\DTO\Metrics\SystemLimits;

describe('SystemLimits', function () {
    it('can be instantiated with all values', function () {
        $limits = new SystemLimits(
            source: LimitSource::HOST,
            cpuCores: 8,
            memoryBytes: 16_000_000_000,
            currentCpuCores: 4,
            currentMemoryBytes: 8_000_000_000.0,
            swapBytes: 4_000_000_000,
            currentSwapBytes: 2_000_000_000.0,
        );

        expect($limits->source)->toBe(LimitSource::HOST);
        expect($limits->cpuCores)->toBe(8);
        expect($limits->memoryBytes)->toBe(16_000_000_000);
        expect($limits->currentCpuCores)->toBe(4);
        expect($limits->currentMemoryBytes)->toBe(8_000_000_000.0);
        expect($limits->swapBytes)->toBe(4_000_000_000);
        expect($limits->currentSwapBytes)->toBe(2_000_000_000.0);
    });

    it('can be instantiated without swap', function () {
        $limits = new SystemLimits(
            source: LimitSource::CGROUP_V2,
            cpuCores: 4,
            memoryBytes: 8_000_000_000,
            currentCpuCores: 2,
            currentMemoryBytes: 4_000_000_000.0,
        );

        expect($limits->swapBytes)->toBeNull();
        expect($limits->currentSwapBytes)->toBeNull();
    });

    it('calculates available CPU cores correctly', function () {
        $limits = new SystemLimits(
            source: LimitSource::HOST,
            cpuCores: 8,
            memoryBytes: 16_000_000_000,
            currentCpuCores: 3,
            currentMemoryBytes: 8_000_000_000.0,
        );

        expect($limits->availableCpuCores())->toBe(5);
    });

    it('calculates available memory bytes correctly', function () {
        $limits = new SystemLimits(
            source: LimitSource::HOST,
            cpuCores: 8,
            memoryBytes: 16_000_000_000,
            currentCpuCores: 4,
            currentMemoryBytes: 8_000_000_000.0,
        );

        expect($limits->availableMemoryBytes())->toBe(8_000_000_000);
    });

    it('returns zero available when at capacity', function () {
        $limits = new SystemLimits(
            source: LimitSource::CGROUP_V1,
            cpuCores: 4,
            memoryBytes: 8_000_000_000,
            currentCpuCores: 4,
            currentMemoryBytes: 8_000_000_000.0,
        );

        expect($limits->availableCpuCores())->toBe(0);
        expect($limits->availableMemoryBytes())->toBe(0);
    });

    it('returns zero available when over capacity', function () {
        $limits = new SystemLimits(
            source: LimitSource::HOST,
            cpuCores: 4,
            memoryBytes: 8_000_000_000,
            currentCpuCores: 5, // Over limit
            currentMemoryBytes: 9_000_000_000.0, // Over limit
        );

        expect($limits->availableCpuCores())->toBe(0);
        expect($limits->availableMemoryBytes())->toBe(0);
    });

    it('calculates CPU utilization percentage correctly', function () {
        $limits = new SystemLimits(
            source: LimitSource::HOST,
            cpuCores: 8,
            memoryBytes: 16_000_000_000,
            currentCpuCores: 4,
            currentMemoryBytes: 8_000_000_000.0,
        );

        expect($limits->cpuUtilization())->toBe(50.0);
    });

    it('calculates memory utilization percentage correctly', function () {
        $limits = new SystemLimits(
            source: LimitSource::CGROUP_V2,
            cpuCores: 4,
            memoryBytes: 8_000_000_000,
            currentCpuCores: 2,
            currentMemoryBytes: 6_000_000_000.0,
        );

        expect($limits->memoryUtilization())->toBe(75.0);
    });

    it('handles zero CPU cores gracefully', function () {
        $limits = new SystemLimits(
            source: LimitSource::HOST,
            cpuCores: 0,
            memoryBytes: 16_000_000_000,
            currentCpuCores: 0,
            currentMemoryBytes: 8_000_000_000.0,
        );

        expect($limits->cpuUtilization())->toBe(0.0);
    });

    it('handles zero memory gracefully', function () {
        $limits = new SystemLimits(
            source: LimitSource::HOST,
            cpuCores: 8,
            memoryBytes: 0,
            currentCpuCores: 4,
            currentMemoryBytes: 0.0,
        );

        expect($limits->memoryUtilization())->toBe(0.0);
    });

    it('calculates swap utilization when available', function () {
        $limits = new SystemLimits(
            source: LimitSource::HOST,
            cpuCores: 8,
            memoryBytes: 16_000_000_000,
            currentCpuCores: 4,
            currentMemoryBytes: 8_000_000_000.0,
            swapBytes: 4_000_000_000,
            currentSwapBytes: 1_000_000_000.0,
        );

        expect($limits->swapUtilization())->toBe(25.0);
    });

    it('returns null for swap utilization when not available', function () {
        $limits = new SystemLimits(
            source: LimitSource::CGROUP_V2,
            cpuCores: 4,
            memoryBytes: 8_000_000_000,
            currentCpuCores: 2,
            currentMemoryBytes: 4_000_000_000.0,
        );

        expect($limits->swapUtilization())->toBeNull();
    });

    it('detects if can scale CPU by additional cores', function () {
        $limits = new SystemLimits(
            source: LimitSource::HOST,
            cpuCores: 8,
            memoryBytes: 16_000_000_000,
            currentCpuCores: 4,
            currentMemoryBytes: 8_000_000_000.0,
        );

        expect($limits->canScaleCpu(2))->toBeTrue(); // 4 + 2 = 6 <= 8
        expect($limits->canScaleCpu(4))->toBeTrue(); // 4 + 4 = 8 <= 8
        expect($limits->canScaleCpu(5))->toBeFalse(); // 4 + 5 = 9 > 8
    });

    it('detects if can scale memory by additional bytes', function () {
        $limits = new SystemLimits(
            source: LimitSource::CGROUP_V1,
            cpuCores: 4,
            memoryBytes: 8_000_000_000,
            currentCpuCores: 2,
            currentMemoryBytes: 6_000_000_000.0,
        );

        expect($limits->canScaleMemory(1_000_000_000))->toBeTrue(); // 6GB + 1GB <= 8GB
        expect($limits->canScaleMemory(2_000_000_000))->toBeTrue(); // 6GB + 2GB <= 8GB
        expect($limits->canScaleMemory(3_000_000_000))->toBeFalse(); // 6GB + 3GB > 8GB
    });

    it('calculates CPU headroom correctly', function () {
        $limits = new SystemLimits(
            source: LimitSource::HOST,
            cpuCores: 8,
            memoryBytes: 16_000_000_000,
            currentCpuCores: 2, // 25% used
            currentMemoryBytes: 8_000_000_000.0,
        );

        expect($limits->cpuHeadroom())->toBe(75.0);
    });

    it('calculates memory headroom correctly', function () {
        $limits = new SystemLimits(
            source: LimitSource::CGROUP_V2,
            cpuCores: 4,
            memoryBytes: 8_000_000_000,
            currentCpuCores: 2,
            currentMemoryBytes: 2_000_000_000.0, // 25% used
        );

        expect($limits->memoryHeadroom())->toBe(75.0);
    });

    it('returns zero headroom when at capacity', function () {
        $limits = new SystemLimits(
            source: LimitSource::HOST,
            cpuCores: 4,
            memoryBytes: 8_000_000_000,
            currentCpuCores: 4,
            currentMemoryBytes: 8_000_000_000.0,
        );

        expect($limits->cpuHeadroom())->toBe(0.0);
        expect($limits->memoryHeadroom())->toBe(0.0);
    });

    it('detects containerized environment from cgroup v1', function () {
        $limits = new SystemLimits(
            source: LimitSource::CGROUP_V1,
            cpuCores: 4,
            memoryBytes: 8_000_000_000,
            currentCpuCores: 2,
            currentMemoryBytes: 4_000_000_000.0,
        );

        expect($limits->isContainerized())->toBeTrue();
    });

    it('detects containerized environment from cgroup v2', function () {
        $limits = new SystemLimits(
            source: LimitSource::CGROUP_V2,
            cpuCores: 2,
            memoryBytes: 4_000_000_000,
            currentCpuCores: 1,
            currentMemoryBytes: 2_000_000_000.0,
        );

        expect($limits->isContainerized())->toBeTrue();
    });

    it('detects non-containerized environment from host', function () {
        $limits = new SystemLimits(
            source: LimitSource::HOST,
            cpuCores: 8,
            memoryBytes: 16_000_000_000,
            currentCpuCores: 4,
            currentMemoryBytes: 8_000_000_000.0,
        );

        expect($limits->isContainerized())->toBeFalse();
    });

    it('detects memory pressure above default threshold', function () {
        $limits = new SystemLimits(
            source: LimitSource::CGROUP_V1,
            cpuCores: 4,
            memoryBytes: 8_000_000_000,
            currentCpuCores: 2,
            currentMemoryBytes: 7_000_000_000.0, // 87.5%
        );

        expect($limits->isMemoryPressure())->toBeTrue();
    });

    it('detects memory pressure with custom threshold', function () {
        $limits = new SystemLimits(
            source: LimitSource::HOST,
            cpuCores: 8,
            memoryBytes: 16_000_000_000,
            currentCpuCores: 4,
            currentMemoryBytes: 12_000_000_000.0, // 75%
        );

        expect($limits->isMemoryPressure(70.0))->toBeTrue();
        expect($limits->isMemoryPressure(80.0))->toBeFalse();
    });

    it('detects CPU pressure above default threshold', function () {
        $limits = new SystemLimits(
            source: LimitSource::CGROUP_V2,
            cpuCores: 4,
            memoryBytes: 8_000_000_000,
            currentCpuCores: 3, // 75%
            currentMemoryBytes: 4_000_000_000.0,
        );

        expect($limits->isCpuPressure(70.0))->toBeTrue();
        expect($limits->isCpuPressure())->toBeFalse(); // Default 80%
    });

    it('handles over-provisioned scenarios', function () {
        $limits = new SystemLimits(
            source: LimitSource::HOST,
            cpuCores: 4,
            memoryBytes: 8_000_000_000,
            currentCpuCores: 6, // 150% - over limit
            currentMemoryBytes: 10_000_000_000.0, // 125% - over limit
        );

        expect($limits->cpuUtilization())->toBe(150.0);
        expect($limits->memoryUtilization())->toBe(125.0);
        expect($limits->cpuHeadroom())->toBe(0.0); // max(0, 100 - 150) = 0
        expect($limits->memoryHeadroom())->toBe(0.0);
    });

    it('is immutable', function () {
        $limits = new SystemLimits(
            source: LimitSource::HOST,
            cpuCores: 8,
            memoryBytes: 16_000_000_000,
            currentCpuCores: 4,
            currentMemoryBytes: 8_000_000_000.0,
        );

        $reflection = new ReflectionClass($limits);
        expect($reflection->isReadOnly())->toBeTrue();
    });
});
