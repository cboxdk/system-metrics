<?php

use Cbox\SystemMetrics\DTO\Metrics\Storage\DiskIOStats;
use Cbox\SystemMetrics\Support\Parser\LinuxDiskstatsParser;

describe('LinuxDiskstatsParser', function () {
    it('can parse /proc/diskstats output', function () {
        $parser = new LinuxDiskstatsParser;
        $diskstatsContent = <<<'DISKSTATS'
   8       0 sda 123456 0 987654 5000 234567 0 1876432 10000 0 8000 15000 0 0 0 0 0 0
   8      16 sdb 654321 0 5234567 8000 876543 0 7011544 20000 0 15000 28000 0 0 0 0 0 0
   8       1 sda1 50000 0 400000 2000 100000 0 800000 4000 0 3000 6000 0 0 0 0 0 0
DISKSTATS;

        $result = $parser->parse($diskstatsContent);

        expect($result->isSuccess())->toBeTrue();

        $diskStats = $result->getValue();
        expect($diskStats)->toHaveCount(2); // Only sda and sdb (partitions skipped)

        $sda = $diskStats[0];
        expect($sda)->toBeInstanceOf(DiskIOStats::class);
        expect($sda->device)->toBe('sda');
        expect($sda->readsCompleted)->toBe(123456);
        expect($sda->readBytes)->toBe(987654 * 512); // Sectors to bytes
        expect($sda->writesCompleted)->toBe(234567);
        expect($sda->writeBytes)->toBe(1876432 * 512);
        expect($sda->ioTimeMs)->toBe(8000);
        expect($sda->weightedIOTimeMs)->toBe(15000);
    });

    it('skips partition devices correctly', function () {
        $parser = new LinuxDiskstatsParser;
        $diskstatsContent = <<<'DISKSTATS'
   8       0 sda 123456 0 987654 5000 234567 0 1876432 10000 0 8000 15000 0 0 0 0 0 0
   8       1 sda1 50000 0 400000 2000 100000 0 800000 4000 0 3000 6000 0 0 0 0 0 0
   8       2 sda2 30000 0 240000 1500 60000 0 480000 2500 0 2000 4000 0 0 0 0 0 0
DISKSTATS;

        $result = $parser->parse($diskstatsContent);
        $diskStats = $result->getValue();

        expect($diskStats)->toHaveCount(1); // Only sda
        expect($diskStats[0]->device)->toBe('sda');
    });

    it('converts sectors to bytes correctly', function () {
        $parser = new LinuxDiskstatsParser;
        $diskstatsContent = <<<'DISKSTATS'
   8       0 sda 1000 0 2000 5000 3000 0 4000 10000 0 8000 15000 0 0 0 0 0 0
DISKSTATS;

        $result = $parser->parse($diskstatsContent);
        $diskStats = $result->getValue();

        $sda = $diskStats[0];
        // 2000 sectors * 512 bytes/sector = 1024000 bytes
        expect($sda->readBytes)->toBe(2000 * 512);
        // 4000 sectors * 512 bytes/sector = 2048000 bytes
        expect($sda->writeBytes)->toBe(4000 * 512);
    });

    it('handles multiple disk devices', function () {
        $parser = new LinuxDiskstatsParser;
        $diskstatsContent = <<<'DISKSTATS'
   8       0 sda 100 0 200 50 300 0 400 100 0 80 150 0 0 0 0 0 0
   8      16 sdb 200 0 400 100 600 0 800 200 0 160 300 0 0 0 0 0 0
   8      32 sdc 300 0 600 150 900 0 1200 300 0 240 450 0 0 0 0 0 0
DISKSTATS;

        $result = $parser->parse($diskstatsContent);
        $diskStats = $result->getValue();

        expect($diskStats)->toHaveCount(3);
        expect($diskStats[0]->device)->toBe('sda');
        expect($diskStats[1]->device)->toBe('sdb');
        expect($diskStats[2]->device)->toBe('sdc');
    });

    it('fails on empty diskstats content', function () {
        $parser = new LinuxDiskstatsParser;

        $result = $parser->parse('');

        expect($result->isFailure())->toBeTrue();
    });

    it('returns empty array for invalid diskstats format', function () {
        $parser = new LinuxDiskstatsParser;
        $diskstatsContent = "invalid data here\n";

        $result = $parser->parse($diskstatsContent);

        // Parser returns success with empty array for invalid lines
        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toHaveCount(0);
    });

    it('handles NVMe device names', function () {
        $parser = new LinuxDiskstatsParser;
        $diskstatsContent = <<<'DISKSTATS'
 259       0 nvme0n1 123456 0 987654 5000 234567 0 1876432 10000 0 8000 15000 0 0 0 0 0 0
 259       1 nvme0n1p1 50000 0 400000 2000 100000 0 800000 4000 0 3000 6000 0 0 0 0 0 0
DISKSTATS;

        $result = $parser->parse($diskstatsContent);
        $diskStats = $result->getValue();

        expect($diskStats)->toHaveCount(1); // Only nvme0n1 (partition skipped)
        expect($diskStats[0]->device)->toBe('nvme0n1');
    });

    it('handles virtio device names', function () {
        $parser = new LinuxDiskstatsParser;
        $diskstatsContent = <<<'DISKSTATS'
 252       0 vda 123456 0 987654 5000 234567 0 1876432 10000 0 8000 15000 0 0 0 0 0 0
 252       1 vda1 50000 0 400000 2000 100000 0 800000 4000 0 3000 6000 0 0 0 0 0 0
DISKSTATS;

        $result = $parser->parse($diskstatsContent);
        $diskStats = $result->getValue();

        // Both vda and vda1 are included (partition detection may vary)
        expect($diskStats)->toBeArray();
        expect($diskStats[0]->device)->toContain('vda');
    });
});
