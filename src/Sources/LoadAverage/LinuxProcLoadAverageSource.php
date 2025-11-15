<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\LoadAverage;

use PHPeek\SystemMetrics\Contracts\LoadAverageSource;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Support\FileReader;
use PHPeek\SystemMetrics\Support\Parser\LinuxProcLoadavgParser;

/**
 * Linux implementation for reading load average from /proc/loadavg.
 */
final readonly class LinuxProcLoadAverageSource implements LoadAverageSource
{
    private const LOADAVG_PATH = '/proc/loadavg';

    public function __construct(
        private FileReader $fileReader = new FileReader,
        private LinuxProcLoadavgParser $parser = new LinuxProcLoadavgParser,
    ) {}

    /**
     * Read load average from /proc/loadavg.
     *
     * @return Result<\PHPeek\SystemMetrics\DTO\Metrics\LoadAverageSnapshot>
     */
    public function read(): Result
    {
        $result = $this->fileReader->read(self::LOADAVG_PATH);

        if ($result->isFailure()) {
            $error = $result->getError();
            assert($error !== null);

            /** @var Result<\PHPeek\SystemMetrics\DTO\Metrics\LoadAverageSnapshot> */
            return Result::failure($error);
        }

        return $this->parser->parse($result->getValue());
    }
}
