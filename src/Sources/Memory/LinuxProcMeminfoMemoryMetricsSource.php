<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Memory;

use PHPeek\SystemMetrics\Contracts\FileReaderInterface;
use PHPeek\SystemMetrics\Contracts\MemoryMetricsSource;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Support\FileReader;
use PHPeek\SystemMetrics\Support\Parser\LinuxMeminfoParser;

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

            return Result::failure($error);
        }

        return $this->parser->parse($result->getValue());
    }
}
