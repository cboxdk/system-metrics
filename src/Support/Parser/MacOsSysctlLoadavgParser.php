<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Support\Parser;

use Cbox\SystemMetrics\DTO\Metrics\LoadAverageSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\ParseException;

/**
 * Parses macOS sysctl vm.loadavg output for load average metrics.
 *
 * Expected format: sysctl -n vm.loadavg
 * Output: { 0.57 0.80 0.85 }
 *           ^    ^    ^
 *           1min 5min 15min
 */
final class MacOsSysctlLoadavgParser
{
    /**
     * Parse sysctl vm.loadavg output into LoadAverageSnapshot.
     *
     * @return Result<LoadAverageSnapshot>
     */
    public function parse(string $output): Result
    {
        $output = trim($output);

        if ($output === '') {
            /** @var Result<LoadAverageSnapshot> */
            return Result::failure(
                ParseException::forCommand('sysctl vm.loadavg', 'Empty output')
            );
        }

        // Remove braces: "{ 0.57 0.80 0.85 }" -> "0.57 0.80 0.85"
        $output = trim($output, '{}');
        $output = trim($output);

        $fields = preg_split('/\s+/', $output);

        if ($fields === false || count($fields) < 3) {
            /** @var Result<LoadAverageSnapshot> */
            return Result::failure(
                ParseException::forCommand('sysctl vm.loadavg', 'Insufficient fields')
            );
        }

        $oneMinute = (float) $fields[0];
        $fiveMinutes = (float) $fields[1];
        $fifteenMinutes = (float) $fields[2];

        return Result::success(new LoadAverageSnapshot(
            oneMinute: $oneMinute,
            fiveMinutes: $fiveMinutes,
            fifteenMinutes: $fifteenMinutes
        ));
    }
}
