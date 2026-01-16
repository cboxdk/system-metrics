<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Support\Parser;

use Cbox\SystemMetrics\DTO\Metrics\UptimeSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\ParseException;
use DateTimeImmutable;

/**
 * Parse macOS sysctl kern.boottime format.
 */
final class MacOsSysctlBoottimeParser
{
    /**
     * Parse sysctl kern.boottime output.
     *
     * Format: "{ sec = 1762527162, usec = 610941 } Sat Jan 11 22:39:22 2025\n"
     * Extract 'sec' value as Unix timestamp
     *
     * @return Result<UptimeSnapshot>
     */
    public function parse(string $contents): Result
    {
        $contents = trim($contents);

        if ($contents === '') {
            /** @var Result<UptimeSnapshot> */
            return Result::failure(new ParseException('Empty sysctl kern.boottime output'));
        }

        // Extract seconds: { sec = 1762527162, usec = 610941 }
        if (! preg_match('/sec\s*=\s*(\d+)/', $contents, $matches)) {
            /** @var Result<UptimeSnapshot> */
            return Result::failure(new ParseException('Could not parse boot time seconds from sysctl output'));
        }

        $bootTimestamp = (int) $matches[1];

        if ($bootTimestamp <= 0) {
            /** @var Result<UptimeSnapshot> */
            return Result::failure(new ParseException('Invalid boot timestamp'));
        }

        $bootTime = (new DateTimeImmutable)->setTimestamp($bootTimestamp);
        $timestamp = new DateTimeImmutable;
        $totalSeconds = $timestamp->getTimestamp() - $bootTimestamp;

        if ($totalSeconds < 0) {
            /** @var Result<UptimeSnapshot> */
            return Result::failure(new ParseException('Boot time is in the future'));
        }

        return Result::success(new UptimeSnapshot(
            totalSeconds: $totalSeconds,
            bootTime: $bootTime,
            timestamp: $timestamp,
        ));
    }
}
