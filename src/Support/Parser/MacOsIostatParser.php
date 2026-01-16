<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Support\Parser;

use Cbox\SystemMetrics\DTO\Metrics\Storage\DiskIOStats;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\ParseException;

/**
 * Parse macOS iostat -Id output for disk I/O statistics.
 */
final class MacOsIostatParser
{
    /**
     * Parse iostat -Id output.
     *
     * Expected format:
     *               disk0               disk2
     *     KB/t  tps  MB/s     KB/t  tps  MB/s
     *    21.88 85.03  1.82    24.67 4.12  0.10
     *
     * Note: macOS iostat provides rates, not cumulative counters like Linux.
     * We'll return zeros for now or estimate from rates if possible.
     *
     * @return Result<DiskIOStats[]>
     */
    public function parse(string $output): Result
    {
        $lines = explode("\n", trim($output));

        if (count($lines) < 3) {
            /** @var Result<DiskIOStats[]> */
            return Result::failure(new ParseException('iostat output too short'));
        }

        // First line contains disk names
        $diskLine = trim($lines[0]);
        $diskNames = preg_split('/\s+/', $diskLine);

        if ($diskNames === false) {
            /** @var Result<DiskIOStats[]> */
            return Result::failure(new ParseException('Failed to parse disk names'));
        }

        // Remove empty entries
        $diskNames = array_values(array_filter($diskNames, fn ($name) => $name !== ''));

        // Third line contains the actual data
        $dataLine = trim($lines[2]);
        $dataFields = preg_split('/\s+/', $dataLine);

        if ($dataFields === false) {
            /** @var Result<DiskIOStats[]> */
            return Result::failure(new ParseException('Failed to parse iostat data'));
        }

        $diskStats = [];

        // Each disk has 3 fields: KB/t, tps, MB/s
        $fieldsPerDisk = 3;
        $diskCount = count($diskNames);

        for ($i = 0; $i < $diskCount; $i++) {
            $offset = $i * $fieldsPerDisk;

            if ($offset + 2 >= count($dataFields)) {
                break; // Not enough data
            }

            $device = $diskNames[$i];
            // $kbPerTransfer = (float) $dataFields[$offset];
            // $tps = (float) $dataFields[$offset + 1];
            // $mbPerSecond = (float) $dataFields[$offset + 2];

            // macOS iostat doesn't provide cumulative counters
            // Return zeros for now - this would require tracking over time
            $diskStats[] = new DiskIOStats(
                device: $device,
                readsCompleted: 0,
                readBytes: 0,
                writesCompleted: 0,
                writeBytes: 0,
                ioTimeMs: 0,
                weightedIOTimeMs: 0,
            );
        }

        return Result::success($diskStats);
    }
}
