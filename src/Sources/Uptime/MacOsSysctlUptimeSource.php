<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Uptime;

use PHPeek\SystemMetrics\Contracts\UptimeSource;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Support\Parser\MacOsSysctlBoottimeParser;
use PHPeek\SystemMetrics\Support\ProcessRunner;

/**
 * macOS uptime source using sysctl kern.boottime.
 */
final class MacOsSysctlUptimeSource implements UptimeSource
{
    public function __construct(
        private readonly ProcessRunner $processRunner = new ProcessRunner,
        private readonly MacOsSysctlBoottimeParser $parser = new MacOsSysctlBoottimeParser,
    ) {}

    public function read(): Result
    {
        $result = $this->processRunner->execute('sysctl kern.boottime');

        if ($result->isFailure()) {
            /** @var Result<\PHPeek\SystemMetrics\DTO\Metrics\UptimeSnapshot> */
            return $result;
        }

        return $this->parser->parse($result->getValue());
    }
}
