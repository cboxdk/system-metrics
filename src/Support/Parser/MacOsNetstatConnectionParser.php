<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support\Parser;

use PHPeek\SystemMetrics\DTO\Metrics\Network\NetworkConnectionStats;
use PHPeek\SystemMetrics\DTO\Result;

/**
 * Parse macOS netstat -an output for connection statistics.
 */
final class MacOsNetstatConnectionParser
{
    /**
     * Parse netstat -an output.
     *
     * Expected format:
     * Active Internet connections (including servers)
     * Proto Recv-Q Send-Q  Local Address          Foreign Address        (state)
     * tcp4       0      0  192.168.1.100.51234    93.184.216.34.80       ESTABLISHED
     * tcp4       0      0  *.22                   *.*                    LISTEN
     * tcp4       0      0  192.168.1.100.51233    93.184.216.34.80       TIME_WAIT
     * udp4       0      0  *.68                   *.*
     *
     * @return Result<NetworkConnectionStats>
     */
    public function parse(string $output): Result
    {
        $lines = explode("\n", trim($output));

        $tcpEstablished = 0;
        $tcpListening = 0;
        $tcpTimeWait = 0;
        $udpListening = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, 'Active') || str_starts_with($line, 'Proto')) {
                continue;
            }

            $fields = preg_split('/\s+/', $line);

            if ($fields === false || count($fields) < 5) {
                continue;
            }

            $proto = $fields[0];
            $state = $fields[5] ?? '';

            if (str_starts_with($proto, 'tcp')) {
                match ($state) {
                    'ESTABLISHED' => $tcpEstablished++,
                    'LISTEN' => $tcpListening++,
                    'TIME_WAIT' => $tcpTimeWait++,
                    default => null,
                };
            } elseif (str_starts_with($proto, 'udp')) {
                // UDP doesn't have explicit states, count all as listening
                $udpListening++;
            }
        }

        $totalConnections = $tcpEstablished + $tcpListening + $tcpTimeWait + $udpListening;

        return Result::success(new NetworkConnectionStats(
            tcpEstablished: $tcpEstablished,
            tcpListening: $tcpListening,
            tcpTimeWait: $tcpTimeWait,
            udpListening: $udpListening,
            totalConnections: $totalConnections,
        ));
    }
}
