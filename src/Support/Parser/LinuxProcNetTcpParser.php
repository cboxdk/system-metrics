<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support\Parser;

use PHPeek\SystemMetrics\DTO\Metrics\Network\NetworkConnectionStats;
use PHPeek\SystemMetrics\DTO\Result;

/**
 * Parse /proc/net/tcp and /proc/net/udp for connection statistics.
 */
final class LinuxProcNetTcpParser
{
    /**
     * Parse /proc/net/tcp and /proc/net/udp to get connection statistics.
     *
     * TCP states (column 4 in /proc/net/tcp):
     * 01 = ESTABLISHED
     * 0A = LISTEN
     * 06 = TIME_WAIT
     *
     * @param  string  $tcpContent  Content from /proc/net/tcp
     * @param  string  $udpContent  Content from /proc/net/udp
     * @return Result<NetworkConnectionStats>
     */
    public function parse(string $tcpContent, string $udpContent): Result
    {
        $tcpEstablished = 0;
        $tcpListening = 0;
        $tcpTimeWait = 0;
        $udpListening = 0;

        // Parse TCP connections
        $tcpLines = explode("\n", trim($tcpContent));
        // Skip header line
        array_shift($tcpLines);

        foreach ($tcpLines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $fields = preg_split('/\s+/', $line);
            if ($fields === false || count($fields) < 4) {
                continue;
            }

            // State is in field index 3 (0-indexed)
            $state = $fields[3];

            match ($state) {
                '01' => $tcpEstablished++,
                '0A' => $tcpListening++,
                '06' => $tcpTimeWait++,
                default => null,
            };
        }

        // Parse UDP connections (UDP doesn't have states like TCP, just count listening)
        $udpLines = explode("\n", trim($udpContent));
        // Skip header line
        array_shift($udpLines);

        foreach ($udpLines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $fields = preg_split('/\s+/', $line);
            if ($fields === false || count($fields) < 4) {
                continue;
            }

            // UDP state 07 = CLOSE (listening)
            $state = $fields[3];
            if ($state === '07') {
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
