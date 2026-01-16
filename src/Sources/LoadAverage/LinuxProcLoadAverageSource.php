<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\LoadAverage;

use Cbox\SystemMetrics\Contracts\FileReaderInterface;
use Cbox\SystemMetrics\Contracts\LoadAverageSource;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Support\FileReader;
use Cbox\SystemMetrics\Support\Parser\LinuxProcLoadavgParser;

/**
 * Linux implementation for reading load average from /proc/loadavg.
 */
final readonly class LinuxProcLoadAverageSource implements LoadAverageSource
{
    private const LOADAVG_PATH = '/proc/loadavg';

    public function __construct(
        private readonly FileReaderInterface $fileReader = new FileReader,
        private readonly LinuxProcLoadavgParser $parser = new LinuxProcLoadavgParser,
    ) {}

    /**
     * Read load average from /proc/loadavg.
     *
     * @return Result<\Cbox\SystemMetrics\DTO\Metrics\LoadAverageSnapshot>
     */
    public function read(): Result
    {
        $result = $this->fileReader->read(self::LOADAVG_PATH);

        if ($result->isFailure()) {
            $error = $result->getError();
            assert($error !== null);

            /** @var Result<\Cbox\SystemMetrics\DTO\Metrics\LoadAverageSnapshot> */
            return Result::failure($error);
        }

        return $this->parser->parse($result->getValue());
    }
}
