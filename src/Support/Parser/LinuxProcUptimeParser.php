<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Support\Parser;

use Cbox\SystemMetrics\DTO\Metrics\UptimeSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\ParseException;
use DateTimeImmutable;

/**
 * Parse /proc/uptime format.
 */
final class LinuxProcUptimeParser
{
    /**
     * Parse /proc/uptime content.
     *
     * Format: "123456.78 987654.32\n"
     * First field: uptime in seconds (float)
     * Second field: idle time (unused)
     *
     * @return Result<UptimeSnapshot>
     */
    public function parse(string $contents): Result
    {
        $contents = trim($contents);

        if ($contents === '') {
            /** @var Result<UptimeSnapshot> */
            return Result::failure(new ParseException('Empty /proc/uptime content'));
        }

        $parts = preg_split('/\s+/', $contents);

        if ($parts === false || count($parts) < 2) {
            /** @var Result<UptimeSnapshot> */
            return Result::failure(new ParseException('Invalid /proc/uptime format'));
        }

        $uptimeSeconds = (float) $parts[0];

        if ($uptimeSeconds < 0) {
            /** @var Result<UptimeSnapshot> */
            return Result::failure(new ParseException('Invalid uptime value: negative seconds'));
        }

        $totalSeconds = (int) floor($uptimeSeconds);
        $timestamp = new DateTimeImmutable;
        $bootTime = $timestamp->modify("-{$totalSeconds} seconds");

        return Result::success(new UptimeSnapshot(
            totalSeconds: $totalSeconds,
            bootTime: $bootTime,
            timestamp: $timestamp,
        ));
    }
}
