<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Metrics\Network;

/**
 * Complete network metrics snapshot.
 */
final readonly class NetworkSnapshot
{
    /**
     * @param  NetworkInterface[]  $interfaces
     */
    public function __construct(
        public array $interfaces,
        public ?NetworkConnectionStats $connections,
    ) {}

    /**
     * Total bytes received across all interfaces.
     */
    public function totalBytesReceived(): int
    {
        return array_sum(array_map(fn (NetworkInterface $iface) => $iface->stats->bytesReceived, $this->interfaces));
    }

    /**
     * Total bytes sent across all interfaces.
     */
    public function totalBytesSent(): int
    {
        return array_sum(array_map(fn (NetworkInterface $iface) => $iface->stats->bytesSent, $this->interfaces));
    }

    /**
     * Total packets received across all interfaces.
     */
    public function totalPacketsReceived(): int
    {
        return array_sum(array_map(fn (NetworkInterface $iface) => $iface->stats->packetsReceived, $this->interfaces));
    }

    /**
     * Total packets sent across all interfaces.
     */
    public function totalPacketsSent(): int
    {
        return array_sum(array_map(fn (NetworkInterface $iface) => $iface->stats->packetsSent, $this->interfaces));
    }
}
