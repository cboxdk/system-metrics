<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support\Parser;

use PHPeek\SystemMetrics\DTO\Metrics\LoadAverageSnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\ParseException;

/**
 * Parses /proc/loadavg format for load average metrics.
 *
 * Expected format: /proc/loadavg
 * Output: 0.05 0.04 0.01 1/234 1234
 *         ^    ^    ^    ^      ^
 *         1min 5min 15min running/total last_pid
 *
 * We only parse the first three values (load averages).
 */
final class LinuxProcLoadavgParser
{
    /**
     * Parse /proc/loadavg content into LoadAverageSnapshot.
     *
     * @return Result<LoadAverageSnapshot>
     */
    public function parse(string $content): Result
    {
        $content = trim($content);

        if ($content === '') {
            /** @var Result<LoadAverageSnapshot> */
            return Result::failure(
                ParseException::forFile('/proc/loadavg', 'Empty content')
            );
        }

        $fields = preg_split('/\s+/', $content);

        if ($fields === false || count($fields) < 3) {
            /** @var Result<LoadAverageSnapshot> */
            return Result::failure(
                ParseException::forFile('/proc/loadavg', 'Insufficient fields')
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
