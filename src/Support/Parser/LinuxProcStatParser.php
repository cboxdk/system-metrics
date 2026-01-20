<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Support\Parser;

use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuCoreTimes;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\ParseException;

/**
 * Parses Linux /proc/stat file for CPU metrics.
 */
final class LinuxProcStatParser
{
    /**
     * Parse /proc/stat content into CpuSnapshot.
     *
     * @return Result<CpuSnapshot>
     */
    public function parse(string $content): Result
    {
        $lines = explode("\n", $content);
        $total = null;
        $perCore = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Parse total CPU line (starts with "cpu ")
            if (str_starts_with($line, 'cpu ')) {
                $result = $this->parseCpuLine($line);
                if ($result->isFailure()) {
                    $error = $result->getError();
                    assert($error !== null);

                    /** @var Result<CpuSnapshot> */
                    return Result::failure($error);
                }
                $total = $result->getValue();

                continue;
            }

            // Parse per-core CPU lines (starts with "cpu0", "cpu1", etc.)
            if (preg_match('/^cpu(\d+)\s/', $line, $matches)) {
                $coreIndex = (int) $matches[1];
                $result = $this->parseCpuLine($line);
                if ($result->isFailure()) {
                    $error = $result->getError();
                    assert($error !== null);

                    /** @var Result<CpuSnapshot> */
                    return Result::failure($error);
                }
                $perCore[] = new CpuCoreTimes(
                    coreIndex: $coreIndex,
                    times: $result->getValue()
                );
            }
        }

        if ($total === null) {
            /** @var Result<CpuSnapshot> */
            return Result::failure(
                ParseException::forFile('/proc/stat', 'No total CPU line found')
            );
        }

        return Result::success(new CpuSnapshot(
            total: $total,
            perCore: $perCore,
        ));
    }

    /**
     * Parse a single CPU line into CpuTimes.
     *
     * @return Result<CpuTimes>
     */
    private function parseCpuLine(string $line): Result
    {
        // Format: cpu[N]  user nice system idle iowait irq softirq steal [guest guest_nice]
        // Use explode + array_filter for better performance than regex
        $parts = array_values(array_filter(
            explode(' ', $line),
            fn (string $p) => $p !== ''
        ));

        if (count($parts) < 9) {
            /** @var Result<CpuTimes> */
            return Result::failure(
                ParseException::forFile('/proc/stat', 'Invalid CPU line format')
            );
        }

        // Skip the first element (cpu/cpu0/cpu1/etc.)
        return Result::success(new CpuTimes(
            user: (int) $parts[1],
            nice: (int) $parts[2],
            system: (int) $parts[3],
            idle: (int) $parts[4],
            iowait: (int) $parts[5],
            irq: (int) $parts[6],
            softirq: (int) $parts[7],
            steal: (int) $parts[8],
        ));
    }
}
