<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support\Parser;

use PHPeek\SystemMetrics\DTO\Metrics\Network\NetworkInterface;
use PHPeek\SystemMetrics\DTO\Metrics\Network\NetworkInterfaceStats;
use PHPeek\SystemMetrics\DTO\Metrics\Network\NetworkInterfaceType;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\ParseException;

/**
 * Parse /proc/net/dev for network interface statistics.
 */
final class LinuxProcNetDevParser
{
    /**
     * Parse /proc/net/dev content.
     *
     * Expected format:
     * Inter-|   Receive                                                |  Transmit
     *  face |bytes    packets errs drop fifo frame compressed multicast|bytes    packets errs drop fifo colls carrier compressed
     *     lo: 156756287837 141995644    0    0    0     0          0         0 156756287837 141995644    0    0    0     0       0          0
     *   eth0: 1234567890 9876543    0    0    0     0          0    123456 987654321 7654321    0    0    0     0       0          0
     *
     * @return Result<NetworkInterface[]>
     */
    public function parse(string $content): Result
    {
        $lines = explode("\n", trim($content));

        if (count($lines) < 3) {
            /** @var Result<NetworkInterface[]> */
            return Result::failure(new ParseException('/proc/net/dev content too short'));
        }

        // Skip first two header lines
        array_shift($lines);
        array_shift($lines);

        $interfaces = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Format: "interface_name: rx_bytes rx_packets ... tx_bytes tx_packets ..."
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $interfaceName = trim($parts[0]);
            $stats = preg_split('/\s+/', trim($parts[1]));

            if ($stats === false || count($stats) < 16) {
                continue;
            }

            // Receive fields: bytes packets errs drop fifo frame compressed multicast
            $bytesReceived = (int) $stats[0];
            $packetsReceived = (int) $stats[1];
            $receiveErrors = (int) $stats[2];
            $receiveDrops = (int) $stats[3];

            // Transmit fields: bytes packets errs drop fifo colls carrier compressed
            $bytesSent = (int) $stats[8];
            $packetsSent = (int) $stats[9];
            $transmitErrors = (int) $stats[10];
            $transmitDrops = (int) $stats[11];

            $interfaces[] = new NetworkInterface(
                name: $interfaceName,
                type: NetworkInterfaceType::fromInterfaceName($interfaceName),
                macAddress: '', // Not available in /proc/net/dev
                stats: new NetworkInterfaceStats(
                    bytesReceived: $bytesReceived,
                    bytesSent: $bytesSent,
                    packetsReceived: $packetsReceived,
                    packetsSent: $packetsSent,
                    receiveErrors: $receiveErrors,
                    transmitErrors: $transmitErrors,
                    receiveDrops: $receiveDrops,
                    transmitDrops: $transmitDrops,
                ),
                isUp: true, // Assume up if in /proc/net/dev
                mtu: 0,     // Not available in /proc/net/dev
            );
        }

        return Result::success($interfaces);
    }
}
