<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Storage;

use FFI;
use PHPeek\SystemMetrics\Contracts\FileReaderInterface;
use PHPeek\SystemMetrics\Contracts\StorageMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Storage\FileSystemType;
use PHPeek\SystemMetrics\DTO\Metrics\Storage\MountPoint;
use PHPeek\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;
use PHPeek\SystemMetrics\Support\FileReader;
use PHPeek\SystemMetrics\Support\Parser\LinuxDiskstatsParser;

/**
 * Read storage metrics from Linux using /proc/mounts and statfs() via FFI.
 *
 * This is the preferred method for Linux systems as it provides:
 * - ⚡ Fast performance (direct syscalls vs fork/exec of df)
 * - 📊 Pure /proc/mounts + statfs() - no shell commands
 * - 🔒 Native filesystem statistics via statfs64()
 *
 * Requires PHP FFI extension (enabled by default in PHP 7.4+).
 */
final class LinuxStatfsStorageMetricsSource implements StorageMetricsSource
{
    /** @var FFI|null Cached FFI instance */
    private static ?FFI $ffi = null;

    public function __construct(
        private readonly FileReaderInterface $fileReader = new FileReader,
        private readonly LinuxDiskstatsParser $diskstatsParser = new LinuxDiskstatsParser,
    ) {}

    public function read(): Result
    {
        if (! extension_loaded('ffi')) {
            /** @var Result<StorageSnapshot> */
            return Result::failure(
                new SystemMetricsException('FFI extension not available')
            );
        }

        try {
            // Read disk I/O stats from /proc/diskstats
            $diskIO = [];
            $diskstatsResult = $this->fileReader->read('/proc/diskstats');
            if ($diskstatsResult->isSuccess()) {
                $parsedDiskIO = $this->diskstatsParser->parse($diskstatsResult->getValue());
                if ($parsedDiskIO->isSuccess()) {
                    $diskIO = $parsedDiskIO->getValue();
                }
            }

            // Read mount points from /proc/mounts
            $mountsResult = $this->fileReader->read('/proc/mounts');
            if ($mountsResult->isFailure()) {
                /** @var Result<StorageSnapshot> */
                return Result::failure(
                    new SystemMetricsException('Failed to read /proc/mounts')
                );
            }

            $mountPoints = $this->parseMounts($mountsResult->getValue());

            return Result::success(new StorageSnapshot(
                mountPoints: $mountPoints,
                diskIO: $diskIO,
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
     * Parse /proc/mounts and get statfs() info for each mount point.
     *
     * @return MountPoint[]
     */
    private function parseMounts(string $mountsContent): array
    {
        $lines = explode("\n", trim($mountsContent));
        $mountPoints = [];
        $ffi = $this->getFFI();

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // /proc/mounts format: device mountpoint fstype options freq passno
            $fields = preg_split('/\s+/', $line);
            if ($fields === false || count($fields) < 3) {
                continue;
            }

            $device = $fields[0];
            $mountPoint = $fields[1];
            $fsTypeStr = $fields[2];

            // Skip pseudo filesystems
            if ($this->isPseudoFilesystem($fsTypeStr)) {
                continue;
            }

            // Get filesystem statistics via statfs64()
            $stats = $ffi->new('struct statfs64');
            $result = $ffi->statfs64( // @phpstan-ignore method.notFound (FFI methods defined via cdef)
                $mountPoint, FFI::addr($stats));

            if ($result !== 0) {
                // statfs failed (permission denied, etc.) - skip this mount
                continue;
            }

            // Extract filesystem stats
            $blockSize = (int) $stats->f_bsize; // @phpstan-ignore property.notFound
            $totalBlocks = (int) $stats->f_blocks; // @phpstan-ignore property.notFound
            $freeBlocks = (int) $stats->f_bfree; // @phpstan-ignore property.notFound
            $availableBlocks = (int) $stats->f_bavail; // @phpstan-ignore property.notFound

            $totalBytes = $totalBlocks * $blockSize;
            $freeBytes = $freeBlocks * $blockSize;
            $availableBytes = $availableBlocks * $blockSize;
            $usedBytes = $totalBytes - $freeBytes;

            // Inode statistics
            $totalInodes = (int) $stats->f_files; // @phpstan-ignore property.notFound
            $freeInodes = (int) $stats->f_ffree; // @phpstan-ignore property.notFound
            $usedInodes = $totalInodes - $freeInodes;

            $mountPoints[] = new MountPoint(
                device: $device,
                mountPoint: $mountPoint,
                fsType: $this->mapFilesystemType($fsTypeStr),
                totalBytes: $totalBytes,
                usedBytes: $usedBytes,
                availableBytes: $availableBytes,
                totalInodes: $totalInodes,
                usedInodes: $usedInodes,
                freeInodes: $freeInodes,
            );
        }

        return $mountPoints;
    }

    /**
     * Check if filesystem type is a pseudo/virtual filesystem.
     */
    private function isPseudoFilesystem(string $fsType): bool
    {
        $pseudoTypes = [
            'proc', 'sysfs', 'devpts', 'tmpfs', 'devtmpfs', 'cgroup', 'cgroup2',
            'pstore', 'bpf', 'tracefs', 'debugfs', 'securityfs', 'fusectl',
            'configfs', 'mqueue', 'hugetlbfs', 'autofs', 'binfmt_misc', 'ramfs',
        ];

        return in_array($fsType, $pseudoTypes, true);
    }

    /**
     * Map filesystem type string to enum.
     */
    private function mapFilesystemType(string $fsType): FileSystemType
    {
        return match ($fsType) {
            'ext4' => FileSystemType::EXT4,
            'ext3' => FileSystemType::EXT4, // Close enough
            'ext2' => FileSystemType::EXT4, // Close enough
            'xfs' => FileSystemType::XFS,
            'btrfs' => FileSystemType::BTRFS,
            'zfs' => FileSystemType::ZFS,
            'vfat', 'fat32' => FileSystemType::FAT32,
            'ntfs', 'ntfs3' => FileSystemType::NTFS,
            'tmpfs' => FileSystemType::TMPFS,
            'devtmpfs' => FileSystemType::DEVTMPFS,
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
                typedef unsigned long long uint64_t;
                typedef long long int64_t;
                typedef unsigned long fsblkcnt_t;
                typedef unsigned long fsfilcnt_t;

                // statfs64 structure (64-bit version for large filesystems)
                struct statfs64 {
                    int64_t f_type;
                    int64_t f_bsize;
                    uint64_t f_blocks;
                    uint64_t f_bfree;
                    uint64_t f_bavail;
                    uint64_t f_files;
                    uint64_t f_ffree;
                    int64_t f_fsid[2];
                    int64_t f_namelen;
                    int64_t f_frsize;
                    int64_t f_flags;
                    int64_t f_spare[4];
                };

                int statfs64(const char *path, struct statfs64 *buf);
            ');
        }

        return self::$ffi;
    }
}
