<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Metrics\Storage;

/**
 * Filesystem type enumeration.
 */
enum FileSystemType: string
{
    case EXT2 = 'ext2';
    case EXT3 = 'ext3';
    case EXT4 = 'ext4';
    case XFS = 'xfs';
    case BTRFS = 'btrfs';
    case ZFS = 'zfs';
    case UFS = 'ufs';
    case APFS = 'apfs';
    case HFS = 'hfs';
    case HFS_PLUS = 'hfs+';
    case NTFS = 'ntfs';
    case FAT32 = 'fat32';
    case EXFAT = 'exfat';
    case TMPFS = 'tmpfs';
    case DEVTMPFS = 'devtmpfs';
    case NFS = 'nfs';
    case CIFS = 'cifs';
    case FUSE = 'fuse';
    case OTHER = 'other';

    /**
     * Create from string, defaulting to OTHER for unknown types.
     */
    public static function fromString(string $type): self
    {
        $normalized = strtolower(trim($type));

        return match ($normalized) {
            'ext2' => self::EXT2,
            'ext3' => self::EXT3,
            'ext4' => self::EXT4,
            'xfs' => self::XFS,
            'btrfs' => self::BTRFS,
            'zfs' => self::ZFS,
            'ufs', 'ffs' => self::UFS,
            'apfs' => self::APFS,
            'hfs' => self::HFS,
            'hfs+', 'hfsplus' => self::HFS_PLUS,
            'ntfs' => self::NTFS,
            'fat32', 'vfat', 'msdos' => self::FAT32,
            'exfat' => self::EXFAT,
            'tmpfs' => self::TMPFS,
            'devtmpfs' => self::DEVTMPFS,
            'nfs', 'nfs4' => self::NFS,
            'cifs', 'smb' => self::CIFS,
            'fuse', 'fuseblk' => self::FUSE,
            default => self::OTHER,
        };
    }
}
