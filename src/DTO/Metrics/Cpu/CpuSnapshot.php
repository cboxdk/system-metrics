<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Metrics\Cpu;

use DateTimeImmutable;

/**
 * Complete snapshot of CPU metrics.
 */
final readonly class CpuSnapshot
{
    /**
     * @param  CpuCoreTimes[]  $perCore
     */
    public function __construct(
        public CpuTimes $total,
        public array $perCore,
        public DateTimeImmutable $timestamp = new DateTimeImmutable,
    ) {}

    /**
     * Get the number of CPU cores.
     */
    public function coreCount(): int
    {
        return count($this->perCore);
    }

    /**
     * Find CPU core by index.
     */
    public function findCore(int $coreIndex): ?CpuCoreTimes
    {
        foreach ($this->perCore as $core) {
            if ($core->coreIndex === $coreIndex) {
                return $core;
            }
        }

        return null;
    }

    /**
     * Find all cores where busy percentage is above threshold.
     *
     * @return CpuCoreTimes[]
     */
    public function findBusyCores(float $threshold): array
    {
        return array_values(array_filter(
            $this->perCore,
            function (CpuCoreTimes $core) use ($threshold): bool {
                $total = $core->times->total();
                if ($total === 0) {
                    return false;
                }

                return ($core->times->busy() / $total * 100) >= $threshold;
            }
        ));
    }

    /**
     * Find all cores where idle percentage is above threshold.
     *
     * @return CpuCoreTimes[]
     */
    public function findIdleCores(float $threshold): array
    {
        return array_values(array_filter(
            $this->perCore,
            function (CpuCoreTimes $core) use ($threshold): bool {
                $total = $core->times->total();
                if ($total === 0) {
                    return false;
                }

                return ($core->times->idle / $total * 100) >= $threshold;
            }
        ));
    }

    /**
     * Get the core with highest busy percentage.
     */
    public function busiestCore(): ?CpuCoreTimes
    {
        if (empty($this->perCore)) {
            return null;
        }

        $busiest = $this->perCore[0];
        $busiestPercentage = $this->calculateBusyPercentage($busiest);

        foreach ($this->perCore as $core) {
            $percentage = $this->calculateBusyPercentage($core);
            if ($percentage > $busiestPercentage) {
                $busiest = $core;
                $busiestPercentage = $percentage;
            }
        }

        return $busiest;
    }

    /**
     * Get the core with lowest busy percentage.
     */
    public function idlestCore(): ?CpuCoreTimes
    {
        if (empty($this->perCore)) {
            return null;
        }

        $idlest = $this->perCore[0];
        $idlestPercentage = $this->calculateBusyPercentage($idlest);

        foreach ($this->perCore as $core) {
            $percentage = $this->calculateBusyPercentage($core);
            if ($percentage < $idlestPercentage) {
                $idlest = $core;
                $idlestPercentage = $percentage;
            }
        }

        return $idlest;
    }

    /**
     * Calculate busy percentage for a core.
     */
    private function calculateBusyPercentage(CpuCoreTimes $core): float
    {
        $total = $core->times->total();
        if ($total === 0) {
            return 0.0;
        }

        return ($core->times->busy() / $total) * 100;
    }

    /**
     * Calculate CPU usage delta between two snapshots.
     *
     * ⚠️  IMPORTANT: You MUST wait between taking snapshots for accurate results.
     *
     * CPU counters are cumulative since boot (like an odometer). To calculate speed (usage %),
     * you need to measure distance traveled over time:
     * 1. Take snapshot #1
     * 2. Wait (recommended: 1+ second)
     * 3. Take snapshot #2
     * 4. Calculate delta
     *
     * Shorter intervals (< 0.1s) will produce inaccurate results.
     * Longer intervals (> 1s) provide more accurate averages.
     *
     * @example Basic usage with 1 second interval
     * ```php
     * $snap1 = SystemMetrics::cpu()->getValue();
     * sleep(1);  // CRITICAL: Must wait between snapshots!
     * $snap2 = SystemMetrics::cpu()->getValue();
     *
     * $delta = CpuSnapshot::calculateDelta($snap1, $snap2);
     * echo "CPU Usage: " . round($delta->usagePercentage(), 1) . "%\n";
     * ```
     *
     * @example Per-core analysis
     * ```php
     * $snap1 = SystemMetrics::cpu()->getValue();
     * sleep(2);  // Longer interval for more accurate results
     * $snap2 = SystemMetrics::cpu()->getValue();
     *
     * $delta = CpuSnapshot::calculateDelta($snap1, $snap2);
     * echo "Overall: " . round($delta->usagePercentage(), 1) . "%\n";
     * foreach ($delta->perCoreDelta as $core) {
     *     echo "Core {$core->coreIndex}: " . round($core->usagePercentage(), 1) . "%\n";
     * }
     * ```
     *
     * @param  CpuSnapshot  $before  Earlier snapshot (MUST be taken first)
     * @param  CpuSnapshot  $after  Later snapshot (MUST be taken after waiting)
     * @return CpuDelta Delta with usage percentage calculations
     *
     * @see \PHPeek\SystemMetrics\DTO\Metrics\Process\ProcessDelta::cpuUsagePercentage() for similar pattern
     */
    public static function calculateDelta(
        CpuSnapshot $before,
        CpuSnapshot $after
    ): CpuDelta {
        // Calculate total delta
        $totalDelta = new CpuTimes(
            user: max(0, $after->total->user - $before->total->user),
            nice: max(0, $after->total->nice - $before->total->nice),
            system: max(0, $after->total->system - $before->total->system),
            idle: max(0, $after->total->idle - $before->total->idle),
            iowait: max(0, $after->total->iowait - $before->total->iowait),
            irq: max(0, $after->total->irq - $before->total->irq),
            softirq: max(0, $after->total->softirq - $before->total->softirq),
            steal: max(0, $after->total->steal - $before->total->steal),
        );

        // Calculate per-core deltas
        $perCoreDelta = [];
        foreach ($before->perCore as $beforeCore) {
            $afterCore = $after->findCore($beforeCore->coreIndex);
            if ($afterCore === null) {
                continue; // Core disappeared between snapshots (rare but possible)
            }

            $coreDelta = new CpuTimes(
                user: max(0, $afterCore->times->user - $beforeCore->times->user),
                nice: max(0, $afterCore->times->nice - $beforeCore->times->nice),
                system: max(0, $afterCore->times->system - $beforeCore->times->system),
                idle: max(0, $afterCore->times->idle - $beforeCore->times->idle),
                iowait: max(0, $afterCore->times->iowait - $beforeCore->times->iowait),
                irq: max(0, $afterCore->times->irq - $beforeCore->times->irq),
                softirq: max(0, $afterCore->times->softirq - $beforeCore->times->softirq),
                steal: max(0, $afterCore->times->steal - $beforeCore->times->steal),
            );

            $perCoreDelta[] = new CpuCoreDelta(
                coreIndex: $beforeCore->coreIndex,
                delta: $coreDelta
            );
        }

        // Calculate duration
        $durationSeconds = $after->timestamp->getTimestamp() - $before->timestamp->getTimestamp();
        $durationMicroseconds = $after->timestamp->format('u') - $before->timestamp->format('u');
        $duration = $durationSeconds + ($durationMicroseconds / 1_000_000);

        return new CpuDelta(
            totalDelta: $totalDelta,
            perCoreDelta: $perCoreDelta,
            durationSeconds: max(0.0, $duration),
            startTime: $before->timestamp,
            endTime: $after->timestamp
        );
    }
}
