<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Support\Parser;

use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterface;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterfaceStats;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterfaceType;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\ParseException;

/**
 * Parse macOS netstat -ib output for network interface statistics.
 */
final class MacOsNetstatInterfaceParser
{
    /**
     * Parse netstat -ib output.
     *
     * Expected format:
     * Name       Mtu   Network       Address            Ipkts Ierrs     Ibytes    Opkts Oerrs     Obytes  Coll
     * lo0        16384 <Link#1>                      141995644     0 156756287837 141995644     0 156756287837     0
     * en0        1500  <Link#7>    ab:cd:ef:12:34:56  12345678     0 1234567890  9876543     0  987654321     0
     *
     * @return Result<NetworkInterface[]>
     */
    public function parse(string $output): Result
    {
        $lines = explode("\n", trim($output));

        if (count($lines) < 2) {
            /** @var Result<NetworkInterface[]> */
            return Result::failure(new ParseException('netstat output too short'));
        }

        // Skip header line
        array_shift($lines);

        $interfaces = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $fields = preg_split('/\s+/', $line);

            if ($fields === false || count($fields) < 11) {
                continue;
            }

            $interfaceName = $fields[0];
            $mtu = (int) $fields[1];
            $macAddress = $fields[3];
            $packetsReceived = (int) $fields[4];
            $receiveErrors = (int) $fields[5];
            $bytesReceived = (int) $fields[6];
            $packetsSent = (int) $fields[7];
            $transmitErrors = (int) $fields[8];
            $bytesSent = (int) $fields[9];

            // If MAC address is not in format xx:xx:xx:xx:xx:xx, it's not available
            if (! preg_match('/^[0-9a-f]{1,2}:[0-9a-f]{1,2}:[0-9a-f]{1,2}:[0-9a-f]{1,2}:[0-9a-f]{1,2}:[0-9a-f]{1,2}$/i', $macAddress)) {
                $macAddress = '';
            }

            $interfaces[] = new NetworkInterface(
                name: $interfaceName,
                type: NetworkInterfaceType::fromInterfaceName($interfaceName),
                macAddress: $macAddress,
                stats: new NetworkInterfaceStats(
                    bytesReceived: $bytesReceived,
                    bytesSent: $bytesSent,
                    packetsReceived: $packetsReceived,
                    packetsSent: $packetsSent,
                    receiveErrors: $receiveErrors,
                    transmitErrors: $transmitErrors,
                    receiveDrops: 0, // Not available in netstat -ib
                    transmitDrops: 0, // Not available in netstat -ib
                ),
                isUp: true, // Assume up if in netstat output
                mtu: $mtu,
            );
        }

        return Result::success($interfaces);
    }
}
