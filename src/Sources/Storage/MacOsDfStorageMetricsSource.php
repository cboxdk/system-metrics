<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Storage;

use PHPeek\SystemMetrics\Contracts\ProcessRunnerInterface;
use PHPeek\SystemMetrics\Contracts\StorageMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;
use PHPeek\SystemMetrics\Support\Parser\MacOsDfParser;
use PHPeek\SystemMetrics\Support\Parser\MacOsIostatParser;
use PHPeek\SystemMetrics\Support\ProcessRunner;

/**
 * Read storage metrics from macOS df and iostat commands.
 */
final class MacOsDfStorageMetricsSource implements StorageMetricsSource
{
    public function __construct(
        private readonly ProcessRunnerInterface $processRunner = new ProcessRunner,
        private readonly MacOsDfParser $dfParser = new MacOsDfParser,
        private readonly MacOsIostatParser $iostatParser = new MacOsIostatParser,
    ) {}

    public function read(): Result
    {
        // Read mount point information from df -ki (includes inodes)
        $dfResult = $this->processRunner->execute('df -ki');
        if ($dfResult->isFailure()) {
            /** @var Result<StorageSnapshot> */
            return Result::failure(
                new SystemMetricsException('Failed to execute df command')
            );
        }

        $mountPointsResult = $this->dfParser->parse($dfResult->getValue());
        if ($mountPointsResult->isFailure()) {
            /** @var Result<StorageSnapshot> */
            return Result::failure($mountPointsResult->getError());
        }

        $mountPoints = $mountPointsResult->getValue();

        // Try to read disk I/O stats from iostat
        $diskIO = [];
        $iostatResult = $this->processRunner->execute('iostat -Id disk0 disk1 disk2');
        if ($iostatResult->isSuccess()) {
            $parsedDiskIO = $this->iostatParser->parse($iostatResult->getValue());
            if ($parsedDiskIO->isSuccess()) {
                $diskIO = $parsedDiskIO->getValue();
            }
        }

        return Result::success(new StorageSnapshot(
            mountPoints: $mountPoints,
            diskIO: $diskIO,
        ));
    }
}
