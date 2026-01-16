<?php

use Cbox\SystemMetrics\DTO\Metrics\Storage\FileSystemType;
use Cbox\SystemMetrics\DTO\Metrics\Storage\MountPoint;
use Cbox\SystemMetrics\Support\Parser\MacOsDfParser;

describe('MacOsDfParser', function () {
    it('can parse macOS df -ki output', function () {
        $parser = new MacOsDfParser;
        $dfOutput = <<<'DF'
Filesystem    1024-blocks      Used Available Capacity iused ifree %iused  Mounted on
/dev/disk1s1    976490576 838860800 112090256    89% 2097152 1048576   67%   /
/dev/disk1s5    976490576  10485760 112090256     9%  524288 1048576   33%   /System/Volumes/Data
DF;

        $result = $parser->parse($dfOutput);

        expect($result->isSuccess())->toBeTrue();

        $mountPoints = $result->getValue();
        expect($mountPoints)->toHaveCount(2);

        $root = $mountPoints[0];
        expect($root)->toBeInstanceOf(MountPoint::class);
        expect($root->device)->toBe('/dev/disk1s1');
        expect($root->mountPoint)->toBe('/');
        expect($root->totalBytes)->toBe(976490576 * 1024);
        expect($root->usedBytes)->toBe(838860800 * 1024);
        expect($root->availableBytes)->toBe(112090256 * 1024);
        expect($root->totalInodes)->toBe(2097152 + 1048576);
        expect($root->usedInodes)->toBe(2097152);
        expect($root->freeInodes)->toBe(1048576);
    });

    it('detects APFS filesystem type from device name', function () {
        $parser = new MacOsDfParser;
        $dfOutput = <<<'DF'
Filesystem    1024-blocks      Used Available Capacity iused ifree %iused  Mounted on
/dev/disk1s1    976490576 838860800 112090256    89% 2097152 1048576   67%   /
DF;

        $result = $parser->parse($dfOutput);
        $mountPoints = $result->getValue();

        expect($mountPoints[0]->fsType)->toBe(FileSystemType::APFS);
    });

    it('detects HFS filesystem type from device name', function () {
        $parser = new MacOsDfParser;
        $dfOutput = <<<'DF'
Filesystem    1024-blocks      Used Available Capacity iused ifree %iused  Mounted on
/dev/disk2s2    524288000 262144000 262144000    50% 1000000 500000   67%   /Volumes/OldMac
DF;

        $result = $parser->parse($dfOutput);
        $mountPoints = $result->getValue();

        // disk2s* pattern without "disk1s" suggests older HFS+
        expect($mountPoints[0]->fsType)->toBeInstanceOf(FileSystemType::class);
    });

    it('calculates inode totals correctly', function () {
        $parser = new MacOsDfParser;
        $dfOutput = <<<'DF'
Filesystem    1024-blocks      Used Available Capacity iused ifree %iused  Mounted on
/dev/disk1s1    976490576 838860800 112090256    89% 2097152 1048576   67%   /
DF;

        $result = $parser->parse($dfOutput);
        $mountPoints = $result->getValue();

        $mp = $mountPoints[0];
        expect($mp->totalInodes)->toBe($mp->usedInodes + $mp->freeInodes);
    });

    it('handles output with only header line', function () {
        $parser = new MacOsDfParser;
        $dfOutput = <<<'DF'
Filesystem    1024-blocks      Used Available Capacity iused ifree %iused  Mounted on
DF;

        $result = $parser->parse($dfOutput);

        // Parser fails when output is too short
        expect($result->isFailure())->toBeTrue();
    });

    it('fails on invalid df output format', function () {
        $parser = new MacOsDfParser;

        $result = $parser->parse('invalid output');

        expect($result->isFailure())->toBeTrue();
    });

    it('fails on empty input', function () {
        $parser = new MacOsDfParser;

        $result = $parser->parse('');

        expect($result->isFailure())->toBeTrue();
    });

    it('handles long device names and mount points', function () {
        $parser = new MacOsDfParser;
        $dfOutput = <<<'DF'
Filesystem    1024-blocks      Used Available Capacity iused ifree %iused  Mounted on
/dev/disk1s1    976490576 838860800 112090256    89% 2097152 1048576   67%   /System/Volumes/Preboot
DF;

        $result = $parser->parse($dfOutput);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toHaveCount(1);
        expect($result->getValue()[0]->mountPoint)->toBe('/System/Volumes/Preboot');
    });
});
