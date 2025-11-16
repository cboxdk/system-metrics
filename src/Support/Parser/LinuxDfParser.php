<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support\Parser;

use PHPeek\SystemMetrics\DTO\Metrics\Storage\FileSystemType;
use PHPeek\SystemMetrics\DTO\Metrics\Storage\MountPoint;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\ParseException;

/**
 * Parse df -k output for mount point information.
 */
final class LinuxDfParser
{
    /**
     * Parse df -k output.
     *
     * Expected format:
     * Filesystem     1K-blocks    Used Available Use% Mounted on
     * /dev/sda1       61796348 14971928  43657056  26% /
     *
     * @return Result<MountPoint[]>
     */
    public function parse(string $output): Result
    {
        $lines = explode("\n", trim($output));

        if (count($lines) < 2) {
            /** @var Result<MountPoint[]> */
            return Result::failure(new ParseException('df output too short'));
        }

        // Skip header line
        array_shift($lines);

        $mountPoints = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Split by whitespace
            $fields = preg_split('/\s+/', $line);

            if ($fields === false || count($fields) < 6) {
                continue; // Skip malformed lines
            }

            $device = $fields[0];
            $totalKb = (int) $fields[1];
            $usedKb = (int) $fields[2];
            $availableKb = (int) $fields[3];
            $mountPoint = $fields[5];

            // Try to determine filesystem type (not provided by df -k, will be 'other')
            $fsType = FileSystemType::OTHER;

            $mountPoints[] = new MountPoint(
                device: $device,
                mountPoint: $mountPoint,
                fsType: $fsType,
                totalBytes: $totalKb * 1024,
                usedBytes: $usedKb * 1024,
                availableBytes: $availableKb * 1024,
                totalInodes: 0, // Not provided by df -k
                usedInodes: 0,  // Not provided by df -k
                freeInodes: 0,  // Not provided by df -k
            );
        }

        return Result::success($mountPoints);
    }

    /**
     * Parse df -i output for inode information.
     *
     * Expected format:
     * Filesystem      Inodes  IUsed   IFree IUse% Mounted on
     * /dev/sda1      3932160 283421 3648739    8% /
     *
     * @return Result<array<string, array{total: int, used: int, free: int}>>
     */
    public function parseInodes(string $output): Result
    {
        $lines = explode("\n", trim($output));

        if (count($lines) < 2) {
            /** @var Result<array<string, array{total: int, used: int, free: int}>> */
            return Result::failure(new ParseException('df -i output too short'));
        }

        // Skip header line
        array_shift($lines);

        $inodeData = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $fields = preg_split('/\s+/', $line);

            if ($fields === false || count($fields) < 6) {
                continue;
            }

            $mountPoint = $fields[5];
            $totalInodes = (int) $fields[1];
            $usedInodes = (int) $fields[2];
            $freeInodes = (int) $fields[3];

            $inodeData[$mountPoint] = [
                'total' => $totalInodes,
                'used' => $usedInodes,
                'free' => $freeInodes,
            ];
        }

        return Result::success($inodeData);
    }
}
