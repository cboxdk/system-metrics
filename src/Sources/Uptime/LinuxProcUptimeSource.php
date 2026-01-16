<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Uptime;

use Cbox\SystemMetrics\Contracts\UptimeSource;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Support\FileReader;
use Cbox\SystemMetrics\Support\Parser\LinuxProcUptimeParser;

/**
 * Linux uptime source using /proc/uptime.
 */
final class LinuxProcUptimeSource implements UptimeSource
{
    private const PROC_UPTIME = '/proc/uptime';

    public function __construct(
        private readonly FileReader $fileReader = new FileReader,
        private readonly LinuxProcUptimeParser $parser = new LinuxProcUptimeParser,
    ) {}

    public function read(): Result
    {
        $result = $this->fileReader->read(self::PROC_UPTIME);

        if ($result->isFailure()) {
            /** @var Result<\Cbox\SystemMetrics\DTO\Metrics\UptimeSnapshot> */
            return $result;
        }

        return $this->parser->parse($result->getValue());
    }
}
