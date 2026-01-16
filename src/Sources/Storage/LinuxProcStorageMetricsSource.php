<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Storage;

use Cbox\SystemMetrics\Contracts\FileReaderInterface;
use Cbox\SystemMetrics\Contracts\ProcessRunnerInterface;
use Cbox\SystemMetrics\Contracts\StorageMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;
use Cbox\SystemMetrics\Support\FileReader;
use Cbox\SystemMetrics\Support\Parser\LinuxDfParser;
use Cbox\SystemMetrics\Support\Parser\LinuxDiskstatsParser;
use Cbox\SystemMetrics\Support\ProcessRunner;

/**
 * Read storage metrics from Linux /proc/diskstats and df command.
 */
final class LinuxProcStorageMetricsSource implements StorageMetricsSource
{
    public function __construct(
        private readonly FileReaderInterface $fileReader = new FileReader,
        private readonly ProcessRunnerInterface $processRunner = new ProcessRunner,
        private readonly LinuxDiskstatsParser $diskstatsParser = new LinuxDiskstatsParser,
        private readonly LinuxDfParser $dfParser = new LinuxDfParser,
    ) {}

    public function read(): Result
    {
        // Read disk I/O stats from /proc/diskstats
        $diskstatsResult = $this->fileReader->read('/proc/diskstats');
        $diskIO = [];

        if ($diskstatsResult->isSuccess()) {
            $parsedDiskIO = $this->diskstatsParser->parse($diskstatsResult->getValue());
            if ($parsedDiskIO->isSuccess()) {
                $diskIO = $parsedDiskIO->getValue();
            }
        }

        // Read mount point information from df
        $dfResult = $this->processRunner->execute('df -k');
        if ($dfResult->isFailure()) {
            /** @var Result<StorageSnapshot> */
            return Result::failure(
                new SystemMetricsException('Failed to execute df command')
            );
        }

        $mountPointsResult = $this->dfParser->parse($dfResult->getValue());
        if ($mountPointsResult->isFailure()) {
            $error = $mountPointsResult->getError();
            assert($error !== null);

            /** @var Result<StorageSnapshot> */
            return Result::failure($error);
        }

        $mountPoints = $mountPointsResult->getValue();

        // Try to get inode information
        $dfInodesResult = $this->processRunner->execute('df -i');
        if ($dfInodesResult->isSuccess()) {
            $inodeDataResult = $this->dfParser->parseInodes($dfInodesResult->getValue());
            if ($inodeDataResult->isSuccess()) {
                $inodeData = $inodeDataResult->getValue();

                // Merge inode data into mount points
                $mountPoints = array_map(function ($mp) use ($inodeData) {
                    if (isset($inodeData[$mp->mountPoint])) {
                        $inodes = $inodeData[$mp->mountPoint];

                        return new \Cbox\SystemMetrics\DTO\Metrics\Storage\MountPoint(
                            device: $mp->device,
                            mountPoint: $mp->mountPoint,
                            fsType: $mp->fsType,
                            totalBytes: $mp->totalBytes,
                            usedBytes: $mp->usedBytes,
                            availableBytes: $mp->availableBytes,
                            totalInodes: $inodes['total'],
                            usedInodes: $inodes['used'],
                            freeInodes: $inodes['free'],
                        );
                    }

                    return $mp;
                }, $mountPoints);
            }
        }

        return Result::success(new StorageSnapshot(
            mountPoints: $mountPoints,
            diskIO: $diskIO,
        ));
    }
}
