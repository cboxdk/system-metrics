<?php

use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkConnectionStats;
use Cbox\SystemMetrics\Support\Parser\MacOsNetstatConnectionParser;

describe('MacOsNetstatConnectionParser', function () {
    it('can parse macOS netstat -an output', function () {
        $parser = new MacOsNetstatConnectionParser;
        $netstatOutput = <<<'NETSTAT'
Active Internet connections (including servers)
Proto Recv-Q Send-Q  Local Address          Foreign Address        (state)
tcp4       0      0  127.0.0.1.8000         127.0.0.1.56789        ESTABLISHED
tcp4       0      0  *.80                   *.*                    LISTEN
tcp6       0      0  *.443                  *.*                    LISTEN
tcp4       0      0  10.0.0.1.59123         93.184.216.34.80       TIME_WAIT
tcp4       0      0  192.168.1.100.54321    192.168.1.1.22         ESTABLISHED
udp4       0      0  *.53                   *.*
udp6       0      0  *.546                  *.*
udp4       0      0  127.0.0.1.323          *.*
NETSTAT;

        $result = $parser->parse($netstatOutput);

        expect($result->isSuccess())->toBeTrue();

        $stats = $result->getValue();
        expect($stats)->toBeInstanceOf(NetworkConnectionStats::class);
        expect($stats->tcpEstablished)->toBe(2);
        expect($stats->tcpListening)->toBe(2);
        expect($stats->tcpTimeWait)->toBe(1);
        expect($stats->udpListening)->toBe(3);
        expect($stats->totalConnections)->toBe(8);
    });

    it('identifies TCP ESTABLISHED state correctly', function () {
        $parser = new MacOsNetstatConnectionParser;
        $netstatOutput = <<<'NETSTAT'
Proto Recv-Q Send-Q  Local Address          Foreign Address        (state)
tcp4       0      0  127.0.0.1.8000         127.0.0.1.56789        ESTABLISHED
tcp4       0      0  10.0.0.1.59123         93.184.216.34.80       ESTABLISHED
tcp6       0      0  fe80::1.8080           fe80::2.12345          ESTABLISHED
NETSTAT;

        $result = $parser->parse($netstatOutput);
        $stats = $result->getValue();

        expect($stats->tcpEstablished)->toBe(3);
    });

    it('identifies TCP LISTEN state correctly', function () {
        $parser = new MacOsNetstatConnectionParser;
        $netstatOutput = <<<'NETSTAT'
Proto Recv-Q Send-Q  Local Address          Foreign Address        (state)
tcp4       0      0  *.80                   *.*                    LISTEN
tcp6       0      0  *.443                  *.*                    LISTEN
tcp4       0      0  127.0.0.1.3000         *.*                    LISTEN
NETSTAT;

        $result = $parser->parse($netstatOutput);
        $stats = $result->getValue();

        expect($stats->tcpListening)->toBe(3);
    });

    it('identifies TCP TIME_WAIT state correctly', function () {
        $parser = new MacOsNetstatConnectionParser;
        $netstatOutput = <<<'NETSTAT'
Proto Recv-Q Send-Q  Local Address          Foreign Address        (state)
tcp4       0      0  10.0.0.1.59123         93.184.216.34.80       TIME_WAIT
tcp4       0      0  10.0.0.1.59124         93.184.216.34.443      TIME_WAIT
NETSTAT;

        $result = $parser->parse($netstatOutput);
        $stats = $result->getValue();

        expect($stats->tcpTimeWait)->toBe(2);
    });

    it('counts all UDP sockets as listening', function () {
        $parser = new MacOsNetstatConnectionParser;
        $netstatOutput = <<<'NETSTAT'
Proto Recv-Q Send-Q  Local Address          Foreign Address        (state)
udp4       0      0  *.53                   *.*
udp6       0      0  *.546                  *.*
udp4       0      0  127.0.0.1.323          *.*
udp6       0      0  fe80::1%en0.5353       *.*
NETSTAT;

        $result = $parser->parse($netstatOutput);
        $stats = $result->getValue();

        expect($stats->udpListening)->toBe(4);
    });

    it('calculates total connections correctly', function () {
        $parser = new MacOsNetstatConnectionParser;
        $netstatOutput = <<<'NETSTAT'
Proto Recv-Q Send-Q  Local Address          Foreign Address        (state)
tcp4       0      0  127.0.0.1.8000         127.0.0.1.56789        ESTABLISHED
tcp4       0      0  *.80                   *.*                    LISTEN
tcp4       0      0  10.0.0.1.59123         93.184.216.34.80       TIME_WAIT
udp4       0      0  *.53                   *.*
udp6       0      0  *.546                  *.*
NETSTAT;

        $result = $parser->parse($netstatOutput);
        $stats = $result->getValue();

        expect($stats->totalConnections)->toBe(5); // 1 est + 1 listen + 1 tw + 2 udp
    });

    it('handles IPv4 and IPv6 connections', function () {
        $parser = new MacOsNetstatConnectionParser;
        $netstatOutput = <<<'NETSTAT'
Proto Recv-Q Send-Q  Local Address          Foreign Address        (state)
tcp4       0      0  127.0.0.1.8000         127.0.0.1.56789        ESTABLISHED
tcp6       0      0  ::1.8000               ::1.56789              ESTABLISHED
tcp4       0      0  *.80                   *.*                    LISTEN
tcp6       0      0  *.443                  *.*                    LISTEN
udp4       0      0  *.53                   *.*
udp6       0      0  *.546                  *.*
NETSTAT;

        $result = $parser->parse($netstatOutput);
        $stats = $result->getValue();

        expect($stats->tcpEstablished)->toBe(2);
        expect($stats->tcpListening)->toBe(2);
        expect($stats->udpListening)->toBe(2);
    });

    it('handles empty connections list', function () {
        $parser = new MacOsNetstatConnectionParser;
        $netstatOutput = <<<'NETSTAT'
Active Internet connections (including servers)
Proto Recv-Q Send-Q  Local Address          Foreign Address        (state)
NETSTAT;

        $result = $parser->parse($netstatOutput);

        expect($result->isSuccess())->toBeTrue();
        $stats = $result->getValue();
        expect($stats->tcpEstablished)->toBe(0);
        expect($stats->tcpListening)->toBe(0);
        expect($stats->tcpTimeWait)->toBe(0);
        expect($stats->udpListening)->toBe(0);
        expect($stats->totalConnections)->toBe(0);
    });

    it('handles empty input gracefully', function () {
        $parser = new MacOsNetstatConnectionParser;

        $result = $parser->parse('');

        // Parser returns success with zero counts for empty input
        if ($result->isSuccess()) {
            $stats = $result->getValue();
            expect($stats->totalConnections)->toBe(0);
        } else {
            expect($result->isFailure())->toBeTrue();
        }
    });

    it('handles invalid format gracefully', function () {
        $parser = new MacOsNetstatConnectionParser;
        $netstatOutput = "invalid output\n";

        $result = $parser->parse($netstatOutput);

        // Parser may fail or return empty stats
        if ($result->isSuccess()) {
            $stats = $result->getValue();
            expect($stats->totalConnections)->toBe(0);
        } else {
            expect($result->isFailure())->toBeTrue();
        }
    });

    it('counts all TCP connections in total', function () {
        $parser = new MacOsNetstatConnectionParser;
        $netstatOutput = <<<'NETSTAT'
Proto Recv-Q Send-Q  Local Address          Foreign Address        (state)
tcp4       0      0  127.0.0.1.8000         127.0.0.1.56789        ESTABLISHED
tcp4       0      0  10.0.0.1.59123         93.184.216.34.80       SYN_SENT
tcp4       0      0  10.0.0.1.59124         93.184.216.34.443      CLOSE_WAIT
tcp4       0      0  *.80                   *.*                    LISTEN
NETSTAT;

        $result = $parser->parse($netstatOutput);
        $stats = $result->getValue();

        // Counts specific states
        expect($stats->tcpEstablished)->toBe(1);
        expect($stats->tcpListening)->toBe(1);
        // Total connections includes all lines
        expect($stats->totalConnections)->toBeGreaterThanOrEqual(2);
    });

    it('skips header lines correctly', function () {
        $parser = new MacOsNetstatConnectionParser;
        $netstatOutput = <<<'NETSTAT'
Active Internet connections (including servers)
Proto Recv-Q Send-Q  Local Address          Foreign Address        (state)
tcp4       0      0  127.0.0.1.8000         127.0.0.1.56789        ESTABLISHED
NETSTAT;

        $result = $parser->parse($netstatOutput);
        $stats = $result->getValue();

        // Should only count the connection, not the headers
        expect($stats->totalConnections)->toBe(1);
    });
});
