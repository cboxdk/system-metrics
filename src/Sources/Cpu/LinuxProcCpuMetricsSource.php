<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Cpu;

use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;
use PHPeek\SystemMetrics\Contracts\FileReaderInterface;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Support\FileReader;
use PHPeek\SystemMetrics\Support\Parser\LinuxProcStatParser;

/**
 * Reads CPU metrics from Linux /proc/stat.
 */
final class LinuxProcCpuMetricsSource implements CpuMetricsSource
{
    public function __construct(
        private readonly FileReaderInterface $fileReader = new FileReader,
        private readonly LinuxProcStatParser $parser = new LinuxProcStatParser,
    ) {}

    public function read(): Result
    {
        $result = $this->fileReader->read('/proc/stat');

        if ($result->isFailure()) {
            $error = $result->getError();
            assert($error !== null);

            return Result::failure($error);
        }

        return $this->parser->parse($result->getValue());
    }
}
