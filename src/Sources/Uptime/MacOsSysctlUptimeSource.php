<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Uptime;

use Cbox\SystemMetrics\Contracts\UptimeSource;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Support\Parser\MacOsSysctlBoottimeParser;
use Cbox\SystemMetrics\Support\ProcessRunner;

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
            /** @var Result<\Cbox\SystemMetrics\DTO\Metrics\UptimeSnapshot> */
            return $result;
        }

        return $this->parser->parse($result->getValue());
    }
}
