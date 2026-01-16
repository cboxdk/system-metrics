<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Storage;

use Cbox\SystemMetrics\Contracts\ProcessRunnerInterface;
use Cbox\SystemMetrics\Contracts\StorageMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Storage\StorageSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;
use Cbox\SystemMetrics\Support\Parser\MacOsDfParser;
use Cbox\SystemMetrics\Support\Parser\MacOsIostatParser;
use Cbox\SystemMetrics\Support\ProcessRunner;

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
            $error = $mountPointsResult->getError();
            assert($error !== null);

            /** @var Result<StorageSnapshot> */
            return Result::failure($error);
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
