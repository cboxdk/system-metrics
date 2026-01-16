<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Metrics\Network;

/**
 * Network connection statistics.
 */
final readonly class NetworkConnectionStats
{
    public function __construct(
        public int $tcpEstablished,
        public int $tcpListening,
        public int $tcpTimeWait,
        public int $udpListening,
        public int $totalConnections,
    ) {}
}
