<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Network;

use PHPeek\SystemMetrics\Contracts\FileReaderInterface;
use PHPeek\SystemMetrics\Contracts\NetworkMetricsSource;
use PHPeek\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;
use PHPeek\SystemMetrics\Support\FileReader;
use PHPeek\SystemMetrics\Support\Parser\LinuxProcNetDevParser;
use PHPeek\SystemMetrics\Support\Parser\LinuxProcNetTcpParser;

/**
 * Read network metrics from Linux /proc/net/* files.
 */
final class LinuxProcNetworkMetricsSource implements NetworkMetricsSource
{
    public function __construct(
        private readonly FileReaderInterface $fileReader = new FileReader,
        private readonly LinuxProcNetDevParser $netDevParser = new LinuxProcNetDevParser,
        private readonly LinuxProcNetTcpParser $netTcpParser = new LinuxProcNetTcpParser,
    ) {}

    public function read(): Result
    {
        // Read network interface statistics
        $netDevResult = $this->fileReader->read('/proc/net/dev');
        if ($netDevResult->isFailure()) {
            /** @var Result<NetworkSnapshot> */
            return Result::failure(
                new SystemMetricsException('Failed to read /proc/net/dev')
            );
        }

        $interfacesResult = $this->netDevParser->parse($netDevResult->getValue());
        if ($interfacesResult->isFailure()) {
            /** @var Result<NetworkSnapshot> */
            return Result::failure($interfacesResult->getError());
        }

        $interfaces = $interfacesResult->getValue();

        // Try to read connection statistics
        $connections = null;
        $tcpResult = $this->fileReader->read('/proc/net/tcp');
        $udpResult = $this->fileReader->read('/proc/net/udp');

        if ($tcpResult->isSuccess() && $udpResult->isSuccess()) {
            $connectionsResult = $this->netTcpParser->parse(
                $tcpResult->getValue(),
                $udpResult->getValue()
            );

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
