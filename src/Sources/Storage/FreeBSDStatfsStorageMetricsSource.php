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
 * Read storage metrics from FreeBSD using statfs() via FFI.
 *
 * FreeBSD provides filesystem statistics via statfs() system call.
 * Mount points are read from /etc/fstab or by calling getmntinfo().
 *
 * This implementation uses getmntinfo() to enumerate all mounted filesystems,
 * then uses statfs() to get detailed statistics for each.
 */
final class FreeBSDStatfsStorageMetricsSource implements StorageMetricsSource
{
    /** @var FFI|null Cached FFI instance */
    private static ?FFI $ffi = null;

    public function read(): Result
    {
        try {
            $mountPoints = [];

            // Get all mounted filesystems via getmntinfo()
            $mounts = $this->getMountedFilesystems();

            foreach ($mounts as $mount) {
                $mountPoint = $mount['mountPoint'];
                $device = $mount['device'];
                $fsType = $this->mapFilesystemType($mount['fsType']);

                // Get detailed stats via statfs()
                $stats = $this->getStatfs($mountPoint);

                if ($stats === null) {
                    continue; // Skip on error
                }

                $mountPoints[] = new MountPoint(
                    device: $device,
                    mountPoint: $mountPoint,
                    fsType: $fsType,
                    totalBytes: $stats['totalBytes'],
                    usedBytes: $stats['usedBytes'],
                    availableBytes: $stats['availableBytes'],
                    totalInodes: $stats['totalInodes'],
                    usedInodes: $stats['usedInodes'],
                    freeInodes: $stats['freeInodes'],
                );
            }

            return Result::success(new StorageSnapshot(
                mountPoints: $mountPoints,
                diskIO: [], // Disk I/O would require separate sysctl queries
            ));

        } catch (\Throwable $e) {
            /** @var Result<StorageSnapshot> */
            return Result::failure(
                new SystemMetricsException(
                    'Failed to read storage metrics: '.$e->getMessage(),
                    previous: $e
                )
            );
        }
    }

    /**
     * Get list of mounted filesystems via getmntinfo().
     *
     * @return array<array{mountPoint: string, device: string, fsType: string}>
     */
    private function getMountedFilesystems(): array
    {
        $ffi = $this->getFFI();

        $mntbuf = $ffi->new('struct statfs*');
        // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
        if ($mntbuf === null) {
            return [];
        }

        // @phpstan-ignore method.notFound (FFI methods defined via cdef)
        $count = $ffi->getmntinfo(FFI::addr($mntbuf), 1); // MNT_WAIT = 1

        if ($count <= 0) {
            return [];
        }

        $mounts = [];

        for ($i = 0; $i < $count; $i++) {
            $entry = $mntbuf[$i];

            $mountPoint = FFI::string($entry->f_mntonname);
            $device = FFI::string($entry->f_mntfromname);
            $fsType = FFI::string($entry->f_fstypename);

            $mounts[] = [
                'mountPoint' => $mountPoint,
                'device' => $device,
                'fsType' => $fsType,
            ];
        }

        return $mounts;
    }

    /**
     * Get filesystem statistics via statfs().
     *
     * @return array{totalBytes: int, usedBytes: int, availableBytes: int, totalInodes: int, usedInodes: int, freeInodes: int}|null
     */
    private function getStatfs(string $path): ?array
    {
        $ffi = $this->getFFI();

        $buf = $ffi->new('struct statfs');
        // @phpstan-ignore identical.alwaysFalse (FFI returns CData|null in some environments)
        if ($buf === null) {
            return null;
        }

        // @phpstan-ignore method.notFound (FFI methods defined via cdef)
        $result = $ffi->statfs($path, FFI::addr($buf));

        if ($result !== 0) {
            return null;
        }

        // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
        $bsize = (int) $buf->f_bsize; // Block size
        // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
        $blocks = (int) $buf->f_blocks; // Total blocks
        // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
        $bfree = (int) $buf->f_bfree; // Free blocks
        // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
        $bavail = (int) $buf->f_bavail; // Available blocks (non-root)

        // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
        $files = (int) $buf->f_files; // Total inodes
        // @phpstan-ignore property.notFound (FFI struct properties defined via cdef)
        $ffree = (int) $buf->f_ffree; // Free inodes

        $totalBytes = $blocks * $bsize;
        $availableBytes = $bavail * $bsize;
        $usedBytes = $totalBytes - ($bfree * $bsize);

        $usedInodes = $files - $ffree;

        return [
            'totalBytes' => $totalBytes,
            'usedBytes' => $usedBytes,
            'availableBytes' => $availableBytes,
            'totalInodes' => $files,
            'usedInodes' => $usedInodes,
            'freeInodes' => $ffree,
        ];
    }

    /**
     * Map FreeBSD filesystem type string to FileSystemType enum.
     */
    private function mapFilesystemType(string $fsType): FileSystemType
    {
        return match (strtolower($fsType)) {
            'ufs', 'ffs' => FileSystemType::UFS,
            'zfs' => FileSystemType::ZFS,
            'nfs' => FileSystemType::NFS,
            'msdosfs', 'fat' => FileSystemType::FAT32,
            'ntfs' => FileSystemType::NTFS,
            'ext2fs' => FileSystemType::EXT2,
            'ext3' => FileSystemType::EXT3,
            'ext4' => FileSystemType::EXT4,
            'tmpfs' => FileSystemType::TMPFS,
            'devfs', 'procfs' => FileSystemType::OTHER,
            default => FileSystemType::OTHER,
        };
    }

    /**
     * Get or create FFI instance (cached for performance).
     */
    private function getFFI(): FFI
    {
        if (self::$ffi === null) {
            self::$ffi = FFI::cdef('
                // FreeBSD statfs structure
                struct statfs {
                    unsigned int f_version;
                    unsigned int f_type;
                    unsigned long long f_flags;
                    unsigned long long f_bsize;
                    unsigned long long f_iosize;
                    unsigned long long f_blocks;
                    unsigned long long f_bfree;
                    long long f_bavail;
                    unsigned long long f_files;
                    long long f_ffree;
                    unsigned long long f_syncwrites;
                    unsigned long long f_asyncwrites;
                    unsigned long long f_syncreads;
                    unsigned long long f_asyncreads;
                    unsigned long long f_spare[10];
                    unsigned int f_namemax;
                    unsigned int f_owner;
                    unsigned int f_fsid[2];
                    char f_charspare[80];
                    char f_fstypename[16];
                    char f_mntfromname[1024];
                    char f_mntonname[1024];
                };

                // Get filesystem statistics
                int statfs(const char *path, struct statfs *buf);

                // Get all mounted filesystems
                int getmntinfo(struct statfs **mntbufp, int flags);
            ', null); // FreeBSD: libc functions available without explicit library
        }

        return self::$ffi;
    }
}
