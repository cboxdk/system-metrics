<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\LoadAverage;

use Cbox\SystemMetrics\Contracts\LoadAverageSource;
use Cbox\SystemMetrics\Contracts\ProcessRunnerInterface;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Support\Parser\MacOsSysctlLoadavgParser;
use Cbox\SystemMetrics\Support\ProcessRunner;

/**
 * macOS implementation for reading load average via sysctl.
 */
final readonly class MacOsSysctlLoadAverageSource implements LoadAverageSource
{
    public function __construct(
        private readonly ProcessRunnerInterface $processRunner = new ProcessRunner,
        private readonly MacOsSysctlLoadavgParser $parser = new MacOsSysctlLoadavgParser,
    ) {}

    /**
     * Read load average via sysctl vm.loadavg.
     *
     * @return Result<\Cbox\SystemMetrics\DTO\Metrics\LoadAverageSnapshot>
     */
    public function read(): Result
    {
        $result = $this->processRunner->execute('sysctl -n vm.loadavg');

        if ($result->isFailure()) {
            $error = $result->getError();
            assert($error !== null);

            /** @var Result<\Cbox\SystemMetrics\DTO\Metrics\LoadAverageSnapshot> */
            return Result::failure($error);
        }

        return $this->parser->parse($result->getValue());
    }
}
