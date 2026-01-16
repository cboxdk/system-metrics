<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Cpu;

use Cbox\SystemMetrics\Contracts\CpuMetricsSource;
use Cbox\SystemMetrics\Contracts\FileReaderInterface;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Support\FileReader;
use Cbox\SystemMetrics\Support\Parser\LinuxProcStatParser;

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

            /** @var Result<\Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot> */
            return Result::failure($error);
        }

        return $this->parser->parse($result->getValue());
    }
}
