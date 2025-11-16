<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support\Parser;

use PHPeek\SystemMetrics\DTO\Metrics\Storage\FileSystemType;
use PHPeek\SystemMetrics\DTO\Metrics\Storage\MountPoint;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\ParseException;

/**
 * Parse macOS df -k output for mount point information.
 */
final class MacOsDfParser
{
    /**
     * Parse df -ki output (includes inodes).
     *
     * Expected format:
     * Filesystem                        1024-blocks      Used Available Capacity iused      ifree %iused  Mounted on
     * /dev/disk3s1s1                    1948455240  16410988 180013604      9%  446774 1800136040   0%   /
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

            if ($fields === false || count($fields) < 9) {
                continue; // Skip malformed lines
            }

            $device = $fields[0];
            $totalKb = (int) $fields[1];
            $usedKb = (int) $fields[2];
            $availableKb = (int) $fields[3];
            $inodesUsed = (int) $fields[5];
            $inodesFree = (int) $fields[6];
            $mountPoint = $fields[8];

            // Try to determine filesystem type from device name
            $fsType = $this->detectFilesystemType($device);

            $inodesTotal = $inodesUsed + $inodesFree;

            $mountPoints[] = new MountPoint(
                device: $device,
                mountPoint: $mountPoint,
                fsType: $fsType,
                totalBytes: $totalKb * 1024,
                usedBytes: $usedKb * 1024,
                availableBytes: $availableKb * 1024,
                totalInodes: $inodesTotal,
                usedInodes: $inodesUsed,
                freeInodes: $inodesFree,
            );
        }

        return Result::success($mountPoints);
    }

    /**
     * Detect filesystem type from device name (macOS heuristics).
     */
    private function detectFilesystemType(string $device): FileSystemType
    {
        // APFS devices typically start with /dev/disk
        if (str_starts_with($device, '/dev/disk')) {
            return FileSystemType::APFS;
        }

        // Map filesystem names
        return match (true) {
            str_contains($device, 'apfs') => FileSystemType::APFS,
            str_contains($device, 'hfs') => FileSystemType::HFS_PLUS,
            str_contains($device, 'ntfs') => FileSystemType::NTFS,
            str_contains($device, 'exfat') => FileSystemType::EXFAT,
            str_contains($device, 'fat') => FileSystemType::FAT32,
            default => FileSystemType::OTHER,
        };
    }
}
