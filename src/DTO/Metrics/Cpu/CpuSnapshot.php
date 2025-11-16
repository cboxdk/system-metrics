<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Metrics\Cpu;

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
}
