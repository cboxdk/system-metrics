<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Metrics\Network;

/**
 * Network interface traffic statistics.
 */
final readonly class NetworkInterfaceStats
{
    public function __construct(
        public int $bytesReceived,
        public int $bytesSent,
        public int $packetsReceived,
        public int $packetsSent,
        public int $receiveErrors,
        public int $transmitErrors,
        public int $receiveDrops,
        public int $transmitDrops,
    ) {}

    /**
     * Total bytes (received + sent).
     */
    public function totalBytes(): int
    {
        return $this->bytesReceived + $this->bytesSent;
    }

    /**
     * Total packets (received + sent).
     */
    public function totalPackets(): int
    {
        return $this->packetsReceived + $this->packetsSent;
    }

    /**
     * Total errors (receive + transmit).
     */
    public function totalErrors(): int
    {
        return $this->receiveErrors + $this->transmitErrors;
    }

    /**
     * Total drops (receive + transmit).
     */
    public function totalDrops(): int
    {
        return $this->receiveDrops + $this->transmitDrops;
    }
}
