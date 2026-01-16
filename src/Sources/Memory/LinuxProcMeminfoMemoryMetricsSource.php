<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Memory;

use Cbox\SystemMetrics\Contracts\FileReaderInterface;
use Cbox\SystemMetrics\Contracts\MemoryMetricsSource;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Support\FileReader;
use Cbox\SystemMetrics\Support\Parser\LinuxMeminfoParser;

/**
 * Reads memory metrics from Linux /proc/meminfo.
 */
final class LinuxProcMeminfoMemoryMetricsSource implements MemoryMetricsSource
{
    public function __construct(
        private readonly FileReaderInterface $fileReader = new FileReader,
        private readonly LinuxMeminfoParser $parser = new LinuxMeminfoParser,
    ) {}

    public function read(): Result
    {
        $result = $this->fileReader->read('/proc/meminfo');

        if ($result->isFailure()) {
            $error = $result->getError();
            assert($error !== null);

            /** @var Result<\Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot> */
            return Result::failure($error);
        }

        return $this->parser->parse($result->getValue());
    }
}
