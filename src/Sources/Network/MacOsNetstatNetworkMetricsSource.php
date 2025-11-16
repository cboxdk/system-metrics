<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Network;

use PHPeek\SystemMetrics\Contracts\NetworkMetricsSource;
use PHPeek\SystemMetrics\Contracts\ProcessRunnerInterface;
use PHPeek\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;
use PHPeek\SystemMetrics\Support\Parser\MacOsNetstatConnectionParser;
use PHPeek\SystemMetrics\Support\Parser\MacOsNetstatInterfaceParser;
use PHPeek\SystemMetrics\Support\ProcessRunner;

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
            /** @var Result<NetworkSnapshot> */
            return Result::failure($interfacesResult->getError());
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
