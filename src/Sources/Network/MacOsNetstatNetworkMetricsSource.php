<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Network;

use Cbox\SystemMetrics\Contracts\NetworkMetricsSource;
use Cbox\SystemMetrics\Contracts\ProcessRunnerInterface;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;
use Cbox\SystemMetrics\Support\Parser\MacOsNetstatConnectionParser;
use Cbox\SystemMetrics\Support\Parser\MacOsNetstatInterfaceParser;
use Cbox\SystemMetrics\Support\ProcessRunner;

/**
 * Read network metrics from macOS netstat command.
 */
final class MacOsNetstatNetworkMetricsSource implements NetworkMetricsSource
{
    public function __construct(
        private readonly ProcessRunnerInterface $processRunner = new ProcessRunner,
        private readonly MacOsNetstatInterfaceParser $interfaceParser = new MacOsNetstatInterfaceParser,
        private readonly MacOsNetstatConnectionParser $connectionParser = new MacOsNetstatConnectionParser,
    ) {}

    public function read(): Result
    {
        // Read network interface statistics
        $netstatIbResult = $this->processRunner->execute('netstat -ib');
        if ($netstatIbResult->isFailure()) {
            /** @var Result<NetworkSnapshot> */
            return Result::failure(
                new SystemMetricsException('Failed to execute netstat -ib command')
            );
        }

        $interfacesResult = $this->interfaceParser->parse($netstatIbResult->getValue());
        if ($interfacesResult->isFailure()) {
            $error = $interfacesResult->getError();
            assert($error !== null);

            /** @var Result<NetworkSnapshot> */
            return Result::failure($error);
        }

        $interfaces = $interfacesResult->getValue();

        // Try to read connection statistics
        $connections = null;
        $netstatAnResult = $this->processRunner->execute('netstat -an');
        if ($netstatAnResult->isSuccess()) {
            $connectionsResult = $this->connectionParser->parse($netstatAnResult->getValue());

            if ($connectionsResult->isSuccess()) {
                $connections = $connectionsResult->getValue();
            }
        }

        return Result::success(new NetworkSnapshot(
            interfaces: $interfaces,
            connections: $connections,
        ));
    }
}
