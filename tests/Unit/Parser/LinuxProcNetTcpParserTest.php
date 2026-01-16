<?php

use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkConnectionStats;
use Cbox\SystemMetrics\Support\Parser\LinuxProcNetTcpParser;

describe('LinuxProcNetTcpParser', function () {
    it('can parse /proc/net/tcp and /proc/net/udp output', function () {
        $parser = new LinuxProcNetTcpParser;
        $tcpContent = <<<'TCP'
  sl  local_address rem_address   st tx_queue rx_queue tr tm->when retrnsmt   uid  timeout inode
   0: 00000000:0016 00000000:0000 0A 00000000:00000000 00:00000000 00000000     0        0 12345 1 0000000000000000 100 0 0 10 0
   1: 0100007F:1F40 0100007F:8B48 01 00000000:00000000 00:00000000 00000000  1000        0 23456 1 0000000000000000 20 4 1 10 -1
   2: 0100007F:8B48 0100007F:1F40 01 00000000:00000000 00:00000000 00000000  1000        0 34567 1 0000000000000000 20 4 0 10 -1
   3: 0A000001:1F90 0A000002:0050 06 00000000:00000000 00:00000000 00000000  1000        0 45678 1 0000000000000000 20 4 0 10 -1
TCP;
        $udpContent = <<<'UDP'
  sl  local_address rem_address   st tx_queue rx_queue tr tm->when retrnsmt   uid  timeout inode ref pointer drops
   0: 00000000:0035 00000000:0000 07 00000000:00000000 00:00000000 00000000     0        0 12345 2 0000000000000000 0
   1: 00000000:0043 00000000:0000 07 00000000:00000000 00:00000000 00000000     0        0 23456 2 0000000000000000 0
   2: 0100007F:0323 00000000:0000 07 00000000:00000000 00:00000000 00000000     0        0 34567 2 0000000000000000 0
UDP;

        $result = $parser->parse($tcpContent, $udpContent);

        expect($result->isSuccess())->toBeTrue();

        $stats = $result->getValue();
        expect($stats)->toBeInstanceOf(NetworkConnectionStats::class);
        expect($stats->tcpListening)->toBe(1); // State 0A
        expect($stats->tcpEstablished)->toBe(2); // State 01
        expect($stats->tcpTimeWait)->toBe(1); // State 06
        expect($stats->udpListening)->toBe(3);
        expect($stats->totalConnections)->toBe(7);
    });

    it('identifies TCP ESTABLISHED state correctly', function () {
        $parser = new LinuxProcNetTcpParser;
        $tcpContent = <<<'TCP'
  sl  local_address rem_address   st tx_queue rx_queue tr tm->when retrnsmt   uid  timeout inode
   0: 0100007F:1F40 0100007F:8B48 01 00000000:00000000 00:00000000 00000000  1000        0 12345 1 0000000000000000 20 4 1 10 -1
   1: 0A000001:1F90 0A000002:0050 01 00000000:00000000 00:00000000 00000000  1000        0 23456 1 0000000000000000 20 4 0 10 -1
TCP;
        $udpContent = <<<'UDP'
  sl  local_address rem_address   st tx_queue rx_queue tr tm->when retrnsmt   uid  timeout inode ref pointer drops
UDP;

        $result = $parser->parse($tcpContent, $udpContent);
        $stats = $result->getValue();

        expect($stats->tcpEstablished)->toBe(2);
    });

    it('identifies TCP LISTEN state correctly', function () {
        $parser = new LinuxProcNetTcpParser;
        $tcpContent = <<<'TCP'
  sl  local_address rem_address   st tx_queue rx_queue tr tm->when retrnsmt   uid  timeout inode
   0: 00000000:0016 00000000:0000 0A 00000000:00000000 00:00000000 00000000     0        0 12345 1 0000000000000000 100 0 0 10 0
   1: 00000000:0050 00000000:0000 0A 00000000:00000000 00:00000000 00000000     0        0 23456 1 0000000000000000 100 0 0 10 0
TCP;
        $udpContent = <<<'UDP'
  sl  local_address rem_address   st tx_queue rx_queue tr tm->when retrnsmt   uid  timeout inode ref pointer drops
UDP;

        $result = $parser->parse($tcpContent, $udpContent);
        $stats = $result->getValue();

        expect($stats->tcpListening)->toBe(2);
    });

    it('identifies TCP TIME_WAIT state correctly', function () {
        $parser = new LinuxProcNetTcpParser;
        $tcpContent = <<<'TCP'
  sl  local_address rem_address   st tx_queue rx_queue tr tm->when retrnsmt   uid  timeout inode
   0: 0A000001:1F90 0A000002:0050 06 00000000:00000000 00:00000000 00000000  1000        0 12345 1 0000000000000000 20 4 0 10 -1
   1: 0A000003:1F91 0A000004:0051 06 00000000:00000000 00:00000000 00000000  1000        0 23456 1 0000000000000000 20 4 0 10 -1
TCP;
        $udpContent = <<<'UDP'
  sl  local_address rem_address   st tx_queue rx_queue tr tm->when retrnsmt   uid  timeout inode ref pointer drops
UDP;

        $result = $parser->parse($tcpContent, $udpContent);
        $stats = $result->getValue();

        expect($stats->tcpTimeWait)->toBe(2);
    });

    it('calculates total connections correctly', function () {
        $parser = new LinuxProcNetTcpParser;
        $tcpContent = <<<'TCP'
  sl  local_address rem_address   st tx_queue rx_queue tr tm->when retrnsmt   uid  timeout inode
   0: 00000000:0016 00000000:0000 0A 00000000:00000000 00:00000000 00000000     0        0 12345 1 0000000000000000 100 0 0 10 0
   1: 0100007F:1F40 0100007F:8B48 01 00000000:00000000 00:00000000 00000000  1000        0 23456 1 0000000000000000 20 4 1 10 -1
   2: 0A000001:1F90 0A000002:0050 06 00000000:00000000 00:00000000 00000000  1000        0 34567 1 0000000000000000 20 4 0 10 -1
TCP;
        $udpContent = <<<'UDP'
  sl  local_address rem_address   st tx_queue rx_queue tr tm->when retrnsmt   uid  timeout inode ref pointer drops
   0: 00000000:0035 00000000:0000 07 00000000:00000000 00:00000000 00000000     0        0 12345 2 0000000000000000 0
   1: 00000000:0043 00000000:0000 07 00000000:00000000 00:00000000 00000000     0        0 23456 2 0000000000000000 0
UDP;

        $result = $parser->parse($tcpContent, $udpContent);
        $stats = $result->getValue();

        expect($stats->totalConnections)->toBe(5); // 1 listen + 1 established + 1 time_wait + 2 udp
    });

    it('handles empty TCP and UDP output', function () {
        $parser = new LinuxProcNetTcpParser;
        $tcpContent = <<<'TCP'
  sl  local_address rem_address   st tx_queue rx_queue tr tm->when retrnsmt   uid  timeout inode
TCP;
        $udpContent = <<<'UDP'
  sl  local_address rem_address   st tx_queue rx_queue tr tm->when retrnsmt   uid  timeout inode ref pointer drops
UDP;

        $result = $parser->parse($tcpContent, $udpContent);

        expect($result->isSuccess())->toBeTrue();
        $stats = $result->getValue();
        expect($stats->tcpListening)->toBe(0);
        expect($stats->tcpEstablished)->toBe(0);
        expect($stats->tcpTimeWait)->toBe(0);
        expect($stats->udpListening)->toBe(0);
    });

    it('skips header line correctly', function () {
        $parser = new LinuxProcNetTcpParser;
        $tcpContent = <<<'TCP'
  sl  local_address rem_address   st tx_queue rx_queue tr tm->when retrnsmt   uid  timeout inode
   0: 0100007F:1F40 0100007F:8B48 01 00000000:00000000 00:00000000 00000000  1000        0 12345 1 0000000000000000 20 4 1 10 -1
TCP;
        $udpContent = <<<'UDP'
  sl  local_address rem_address   st tx_queue rx_queue tr tm->when retrnsmt   uid  timeout inode ref pointer drops
UDP;

        $result = $parser->parse($tcpContent, $udpContent);
        $stats = $result->getValue();

        // Should only count the connection, not the header
        expect($stats->tcpEstablished)->toBe(1);
    });
});
