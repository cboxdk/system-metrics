<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Metrics\Network;

/**
 * Network interface information with statistics.
 */
final readonly class NetworkInterface
{
    public function __construct(
        public string $name,
        public NetworkInterfaceType $type,
        public string $macAddress,
        public NetworkInterfaceStats $stats,
        public bool $isUp,
        public int $mtu,
    ) {}
}
