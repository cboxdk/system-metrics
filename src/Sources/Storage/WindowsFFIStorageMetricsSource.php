<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Storage;

use FFI;
use PHPeek\SystemMetrics\Contracts\StorageMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Storage\FileSystemType;
use PHPeek\SystemMetrics\DTO\Metrics\Storage\MountPoint;
use PHPeek\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Read storage metrics from Windows using GetDiskFreeSpaceEx() and GetVolumeInformation() via FFI.
 *
 * This is the preferred method for Windows systems as it provides:
 * - ⚡ Fast performance (direct syscalls vs WMI queries)
 * - 📊 Volume space information
 * - 🔒 Native Win32 API access
 *
 * Note: Windows uses drive letters (C:\, D:\) instead of mount points like Unix.
 * Inode information is not available on Windows (NTFS uses MFT records differently).
 *
 * Requires PHP FFI extension (enabled by default in PHP 7.4+).
 */
final class WindowsFFIStorageMetricsSource implements StorageMetricsSource
{
    /** @var FFI|null Cached FFI instance */
    private static ?FFI $ffi = null;

    public function read(): Result
    {
        if (! extension_loaded('ffi')) {
            /** @var Result<StorageSnapshot> */
            return Result::failure(
                new SystemMetricsException('FFI extension not available')
            );
        }

        try {
            // Get all drive letters (A-Z)
            $mountPoints = [];

            foreach (range('A', 'Z') as $letter) {
                $drive = "{$letter}:\\";

                // Check if drive exists and get its type
                $driveType = $this->getDriveType($drive);

                // Skip non-existent drives and removable/network drives
                if ($driveType === 0 || $driveType === 1 || $driveType === 2 || $driveType === 4) {
                    continue; // 0=unknown, 1=invalid, 2=removable, 4=network
                }

                // Get disk space information
                $spaceResult = $this->getDiskSpace($drive);
                if ($spaceResult->isFailure()) {
                    continue; // Skip drives we can't read
                }

                $spaceInfo = $spaceResult->getValue();

                // Get filesystem type
                $fsType = $this->getFilesystemType($drive);

                $mountPoints[] = new MountPoint(
                    device: $drive,
                    mountPoint: $drive,
                    fsType: $fsType,
                    totalBytes: $spaceInfo['total'],
                    usedBytes: $spaceInfo['used'],
                    availableBytes: $spaceInfo['available'],
                    totalInodes: 0,  // Not applicable on Windows
                    usedInodes: 0,   // Not applicable on Windows
                    freeInodes: 0    // Not applicable on Windows
                );
            }

            return Result::success(new StorageSnapshot(
                mountPoints: $mountPoints,
                diskIO: [] // Would need performance counters for disk I/O on Windows
            ));

        } catch (\Throwable $e) {
            /** @var Result<StorageSnapshot> */
            return Result::failure(
                new SystemMetricsException(
                    'Failed to read storage metrics via FFI: '.$e->getMessage(),
                    previous: $e
                )
            );
        }
    }

    /**
     * Get drive type using GetDriveType().
     *
     * @return int Drive type: 0=unknown, 1=invalid, 2=removable, 3=fixed, 4=network, 5=cdrom, 6=ramdisk
     */
    private function getDriveType(string $drive): int
    {
        $ffi = $this->getFFI();

        // @phpstan-ignore method.notFound (FFI methods defined via cdef)
        return (int) $ffi->GetDriveTypeA($drive);
    }

    /**
     * Get disk space information for a drive.
     *
     * @return Result<array{total: int, available: int, used: int}>
     */
    private function getDiskSpace(string $drive): Result
    {
        try {
            $ffi = $this->getFFI();

            $freeBytesAvailable = $ffi->new('unsigned long long');
            // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
            if ($freeBytesAvailable === null) {
                /** @var Result<array{total: int, available: int, used: int}> */
                return Result::failure(
                    new SystemMetricsException('Failed to allocate memory for freeBytesAvailable')
                );
            }

            $totalBytes = $ffi->new('unsigned long long');
            // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
            if ($totalBytes === null) {
                /** @var Result<array{total: int, available: int, used: int}> */
                return Result::failure(
                    new SystemMetricsException('Failed to allocate memory for totalBytes')
                );
            }

            $totalFreeBytes = $ffi->new('unsigned long long');
            // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
            if ($totalFreeBytes === null) {
                /** @var Result<array{total: int, available: int, used: int}> */
                return Result::failure(
                    new SystemMetricsException('Failed to allocate memory for totalFreeBytes')
                );
            }

            // @phpstan-ignore method.notFound (FFI methods defined via cdef)
            $result = $ffi->GetDiskFreeSpaceExA(
                $drive,
                FFI::addr($freeBytesAvailable),
                FFI::addr($totalBytes),
                FFI::addr($totalFreeBytes)
            );

            if ($result === 0) {
                /** @var Result<array{total: int, available: int, used: int}> */
                return Result::failure(
                    new SystemMetricsException("Failed to get disk space for {$drive}")
                );
            }

            // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
            $total = (int) $totalBytes->cdata;
            // @phpstan-ignore property.notFound
            $available = (int) $freeBytesAvailable->cdata;
            $used = $total - $available;

            return Result::success([
                'total' => $total,
                'available' => $available,
                'used' => $used,
            ]);

        } catch (\Throwable $e) {
            /** @var Result<array{total: int, available: int, used: int}> */
            return Result::failure(
                new SystemMetricsException("Error reading disk space: {$e->getMessage()}", previous: $e)
            );
        }
    }

    /**
     * Get filesystem type for a drive.
     */
    private function getFilesystemType(string $drive): FileSystemType
    {
        try {
            $ffi = $this->getFFI();

            $fileSystemName = $ffi->new('char[256]');
            // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
            if ($fileSystemName === null) {
                return FileSystemType::OTHER;
            }

            $volumeName = $ffi->new('char[256]');
            // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
            if ($volumeName === null) {
                return FileSystemType::OTHER;
            }

            $volumeSerialNumber = $ffi->new('unsigned int');
            // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
            if ($volumeSerialNumber === null) {
                return FileSystemType::OTHER;
            }

            $maximumComponentLength = $ffi->new('unsigned int');
            // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
            if ($maximumComponentLength === null) {
                return FileSystemType::OTHER;
            }

            $fileSystemFlags = $ffi->new('unsigned int');
            // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
            if ($fileSystemFlags === null) {
                return FileSystemType::OTHER;
            }

            // @phpstan-ignore method.notFound (FFI methods defined via cdef)
            $result = $ffi->GetVolumeInformationA(
                $drive,
                $volumeName,
                256,
                FFI::addr($volumeSerialNumber),
                FFI::addr($maximumComponentLength),
                FFI::addr($fileSystemFlags),
                $fileSystemName,
                256
            );

            if ($result === 0) {
                return FileSystemType::OTHER;
            }

            // Convert C string to PHP string
            $fsName = FFI::string($fileSystemName);

            return match (strtolower($fsName)) {
                'ntfs' => FileSystemType::NTFS,
                'fat32' => FileSystemType::FAT32,
                'exfat' => FileSystemType::OTHER,
                'refs' => FileSystemType::OTHER, // ReFS (Resilient File System)
                default => FileSystemType::OTHER,
            };

        } catch (\Throwable) {
            return FileSystemType::OTHER;
        }
    }

    /**
     * Get or create FFI instance (cached for performance).
     */
    private function getFFI(): FFI
    {
        if (self::$ffi === null) {
            self::$ffi = FFI::cdef('
                // Get drive type (fixed, removable, network, etc.)
                unsigned int GetDriveTypeA(const char* lpRootPathName);

                // Get disk space information
                int GetDiskFreeSpaceExA(
                    const char* lpDirectoryName,
                    unsigned long long* lpFreeBytesAvailableToCaller,
                    unsigned long long* lpTotalNumberOfBytes,
                    unsigned long long* lpTotalNumberOfFreeBytes
                );

                // Get volume information including filesystem type
                int GetVolumeInformationA(
                    const char* lpRootPathName,
                    char* lpVolumeNameBuffer,
                    unsigned int nVolumeNameSize,
                    unsigned int* lpVolumeSerialNumber,
                    unsigned int* lpMaximumComponentLength,
                    unsigned int* lpFileSystemFlags,
                    char* lpFileSystemNameBuffer,
                    unsigned int nFileSystemNameSize
                );
            ', 'kernel32.dll');
        }

        return self::$ffi;
    }
}
