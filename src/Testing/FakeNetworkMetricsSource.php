<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Testing;

use Cbox\SystemMetrics\Contracts\NetworkMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterface;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterfaceStats;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterfaceType;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Fake NetworkMetricsSource for testing.
 */
final class FakeNetworkMetricsSource implements NetworkMetricsSource
{
    private ?NetworkSnapshot $snapshot = null;

    private ?SystemMetricsException $exception = null;

    /**
     * @return Result<NetworkSnapshot>
     */
    public function read(): Result
    {
        if ($this->exception !== null) {
            /** @var Result<NetworkSnapshot> */
            return Result::failure($this->exception);
        }

        return Result::success($this->snapshot ?? self::default());
    }

    public function set(NetworkSnapshot $snapshot): self
    {
        $this->snapshot = $snapshot;

        return $this;
    }

    public function failWith(SystemMetricsException $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    public static function default(): NetworkSnapshot
    {
        return new NetworkSnapshot(
            interfaces: [
                new NetworkInterface(
                    name: 'eth0',
                    type: NetworkInterfaceType::ETHERNET,
                    macAddress: '00:00:00:00:00:01',
                    stats: new NetworkInterfaceStats(
                        bytesReceived: 1_073_741_824,    // 1 GB
                        bytesSent: 536_870_912,          // 512 MB
                        packetsReceived: 1_000_000,
                        packetsSent: 500_000,
                        receiveErrors: 0,
                        transmitErrors: 0,
                        receiveDrops: 0,
                        transmitDrops: 0,
                    ),
                    isUp: true,
                    mtu: 1500,
                ),
            ],
            connections: null,
        );
    }
}
