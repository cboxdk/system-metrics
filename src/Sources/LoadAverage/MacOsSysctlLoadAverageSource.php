<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\LoadAverage;

use PHPeek\SystemMetrics\Contracts\LoadAverageSource;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Support\Parser\MacOsSysctlLoadavgParser;
use PHPeek\SystemMetrics\Support\ProcessRunner;

/**
 * macOS implementation for reading load average via sysctl.
 */
final readonly class MacOsSysctlLoadAverageSource implements LoadAverageSource
{
    public function __construct(
        private ProcessRunner $processRunner = new ProcessRunner,
        private MacOsSysctlLoadavgParser $parser = new MacOsSysctlLoadavgParser,
    ) {}

    /**
     * Read load average via sysctl vm.loadavg.
     *
     * @return Result<\PHPeek\SystemMetrics\DTO\Metrics\LoadAverageSnapshot>
     */
    public function read(): Result
    {
        $result = $this->processRunner->execute('sysctl -n vm.loadavg');

        if ($result->isFailure()) {
            $error = $result->getError();
            assert($error !== null);

            /** @var Result<\PHPeek\SystemMetrics\DTO\Metrics\LoadAverageSnapshot> */
            return Result::failure($error);
        }

        return $this->parser->parse($result->getValue());
    }
}
