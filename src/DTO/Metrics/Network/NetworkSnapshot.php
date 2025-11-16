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

    /**
     * Find network interface by exact name.
     */
    public function findInterface(string $name): ?NetworkInterface
    {
        foreach ($this->interfaces as $interface) {
            if ($interface->name === $name) {
                return $interface;
            }
        }

        return null;
    }

    /**
     * Find all network interfaces of a given type.
     *
     * @return NetworkInterface[]
     */
    public function findByType(NetworkInterfaceType $type): array
    {
        return array_values(array_filter(
            $this->interfaces,
            fn (NetworkInterface $iface) => $iface->type === $type
        ));
    }

    /**
     * Find all active (up) interfaces.
     *
     * @return NetworkInterface[]
     */
    public function findActiveInterfaces(): array
    {
        return array_values(array_filter(
            $this->interfaces,
            fn (NetworkInterface $iface) => $iface->isUp
        ));
    }

    /**
     * Find network interface by MAC address.
     */
    public function findByMacAddress(string $mac): ?NetworkInterface
    {
        foreach ($this->interfaces as $interface) {
            if ($interface->macAddress === $mac) {
                return $interface;
            }
        }

        return null;
    }
}
