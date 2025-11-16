<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Uptime;

use PHPeek\SystemMetrics\Contracts\UptimeSource;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Support\FileReader;
use PHPeek\SystemMetrics\Support\Parser\LinuxProcUptimeParser;

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
            /** @var Result<\PHPeek\SystemMetrics\DTO\Metrics\UptimeSnapshot> */
            return $result;
        }

        return $this->parser->parse($result->getValue());
    }
}
