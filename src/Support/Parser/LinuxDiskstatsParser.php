<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support\Parser;

use PHPeek\SystemMetrics\DTO\Metrics\Storage\DiskIOStats;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\ParseException;

/**
 * Parse /proc/diskstats for disk I/O statistics.
 */
final class LinuxDiskstatsParser
{
    /**
     * Parse /proc/diskstats content.
     *
     * Format (space-separated):
     * major minor name reads reads_merged sectors_read time_reading writes writes_merged sectors_written time_writing ios_in_progress time_io weighted_time_io ...
     *
     * Fields we care about:
     * [0] major number
     * [1] minor number
     * [2] device name
     * [3] reads completed successfully
     * [5] sectors read
     * [7] writes completed
     * [9] sectors written
     * [12] time spent doing I/Os (ms)
     * [13] weighted time spent doing I/Os (ms)
     *
     * @return Result<DiskIOStats[]>
     */
    public function parse(string $content): Result
    {
        $lines = explode("\n", trim($content));

        if (count($lines) === 0) {
            /** @var Result<DiskIOStats[]> */
            return Result::failure(new ParseException('diskstats content is empty'));
        }

        $diskStats = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $fields = preg_split('/\s+/', $line);

            if ($fields === false || count($fields) < 14) {
                continue; // Skip malformed lines
            }

            $device = $fields[2];

            // Skip partition devices (e.g., sda1, nvme0n1p1) - only track whole disks
            // Partitions have non-zero minor number modulo 16 for sd* devices
            // For nvme devices, partitions end with 'p' followed by digit
            if (
                preg_match('/^sd[a-z]\d+$/', $device) ||
                preg_match('/^nvme\d+n\d+p\d+$/', $device) ||
                preg_match('/^mmcblk\d+p\d+$/', $device)
            ) {
                continue;
            }

            $readsCompleted = (int) $fields[3];
            $sectorsRead = (int) $fields[5];
            $writesCompleted = (int) $fields[7];
            $sectorsWritten = (int) $fields[9];
            $ioTimeMs = (int) $fields[12];
            $weightedIOTimeMs = (int) $fields[13];

            // Convert sectors to bytes (sector size is typically 512 bytes)
            $readBytes = $sectorsRead * 512;
            $writeBytes = $sectorsWritten * 512;

            $diskStats[] = new DiskIOStats(
                device: $device,
                readsCompleted: $readsCompleted,
                readBytes: $readBytes,
                writesCompleted: $writesCompleted,
                writeBytes: $writeBytes,
                ioTimeMs: $ioTimeMs,
                weightedIOTimeMs: $weightedIOTimeMs,
            );
        }

        return Result::success($diskStats);
    }
}
