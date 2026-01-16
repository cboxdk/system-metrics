<?php

use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterface;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterfaceType;
use Cbox\SystemMetrics\Support\Parser\LinuxProcNetDevParser;

describe('LinuxProcNetDevParser', function () {
    it('can parse /proc/net/dev output', function () {
        $parser = new LinuxProcNetDevParser;
        $netDevContent = <<<'NETDEV'
Inter-|   Receive                                                |  Transmit
 face |bytes    packets errs drop fifo frame compressed multicast|bytes    packets errs drop fifo colls carrier compressed
    lo: 1048576    1024    0    0    0     0          0         0  1048576    1024    0    0    0     0       0          0
  eth0: 10485760   10240   10    5    0     0          0         0  5242880    5120    5    2    0     0       0          0
 wlan0: 20971520   20480   20   10    0     0          0         0 10485760   10240   10    5    0     0       0          0
NETDEV;

        $result = $parser->parse($netDevContent);

        expect($result->isSuccess())->toBeTrue();

        $interfaces = $result->getValue();
        expect($interfaces)->toHaveCount(3);

        $lo = $interfaces[0];
        expect($lo)->toBeInstanceOf(NetworkInterface::class);
        expect($lo->name)->toBe('lo');
        expect($lo->type)->toBe(NetworkInterfaceType::LOOPBACK);
        expect($lo->stats->bytesReceived)->toBe(1048576);
        expect($lo->stats->packetsReceived)->toBe(1024);
        expect($lo->stats->receiveErrors)->toBe(0);
        expect($lo->stats->receiveDrops)->toBe(0);
        expect($lo->stats->bytesSent)->toBe(1048576);
        expect($lo->stats->packetsSent)->toBe(1024);
        expect($lo->stats->transmitErrors)->toBe(0);
        expect($lo->stats->transmitDrops)->toBe(0);

        $eth0 = $interfaces[1];
        expect($eth0->name)->toBe('eth0');
        expect($eth0->type)->toBe(NetworkInterfaceType::ETHERNET);
        expect($eth0->stats->bytesReceived)->toBe(10485760);
        expect($eth0->stats->receiveErrors)->toBe(10);
        expect($eth0->stats->receiveDrops)->toBe(5);
    });

    it('detects interface types from names', function () {
        $parser = new LinuxProcNetDevParser;
        $netDevContent = <<<'NETDEV'
Inter-|   Receive                                                |  Transmit
 face |bytes    packets errs drop fifo frame compressed multicast|bytes    packets errs drop fifo colls carrier compressed
    lo: 1000    10    0    0    0     0          0         0  1000    10    0    0    0     0       0          0
  eth0: 2000    20    0    0    0     0          0         0  2000    20    0    0    0     0       0          0
 wlan0: 3000    30    0    0    0     0          0         0  3000    30    0    0    0     0       0          0
  ens3: 4000    40    0    0    0     0          0         0  4000    40    0    0    0     0       0          0
 wlp2s0: 5000   50    0    0    0     0          0         0  5000    50    0    0    0     0       0          0
NETDEV;

        $result = $parser->parse($netDevContent);
        $interfaces = $result->getValue();

        expect($interfaces[0]->type)->toBe(NetworkInterfaceType::LOOPBACK);
        expect($interfaces[1]->type)->toBe(NetworkInterfaceType::ETHERNET);
        expect($interfaces[2]->type)->toBe(NetworkInterfaceType::WIFI);
        expect($interfaces[3]->type)->toBe(NetworkInterfaceType::ETHERNET);
        expect($interfaces[4]->type)->toBe(NetworkInterfaceType::WIFI);
    });

    it('sets interface as up by default', function () {
        $parser = new LinuxProcNetDevParser;
        $netDevContent = <<<'NETDEV'
Inter-|   Receive                                                |  Transmit
 face |bytes    packets errs drop fifo frame compressed multicast|bytes    packets errs drop fifo colls carrier compressed
  eth0: 10485760   10240   10    5    0     0          0         0  5242880    5120    5    2    0     0       0          0
NETDEV;

        $result = $parser->parse($netDevContent);
        $interfaces = $result->getValue();

        expect($interfaces[0]->isUp)->toBe(true);
    });

    it('sets MAC address to empty string', function () {
        $parser = new LinuxProcNetDevParser;
        $netDevContent = <<<'NETDEV'
Inter-|   Receive                                                |  Transmit
 face |bytes    packets errs drop fifo frame compressed multicast|bytes    packets errs drop fifo colls carrier compressed
  eth0: 10485760   10240   10    5    0     0          0         0  5242880    5120    5    2    0     0       0          0
NETDEV;

        $result = $parser->parse($netDevContent);
        $interfaces = $result->getValue();

        // /proc/net/dev doesn't provide MAC addresses
        expect($interfaces[0]->macAddress)->toBe('');
    });

    it('sets MTU to zero', function () {
        $parser = new LinuxProcNetDevParser;
        $netDevContent = <<<'NETDEV'
Inter-|   Receive                                                |  Transmit
 face |bytes    packets errs drop fifo frame compressed multicast|bytes    packets errs drop fifo colls carrier compressed
  eth0: 10485760   10240   10    5    0     0          0         0  5242880    5120    5    2    0     0       0          0
NETDEV;

        $result = $parser->parse($netDevContent);
        $interfaces = $result->getValue();

        // /proc/net/dev doesn't provide MTU
        expect($interfaces[0]->mtu)->toBe(0);
    });

    it('handles output with only header lines', function () {
        $parser = new LinuxProcNetDevParser;
        $netDevContent = <<<'NETDEV'
Inter-|   Receive                                                |  Transmit
 face |bytes    packets errs drop fifo frame compressed multicast|bytes    packets errs drop fifo colls carrier compressed
NETDEV;

        $result = $parser->parse($netDevContent);

        // Parser fails when output is too short (missing required header)
        expect($result->isFailure())->toBeTrue();
    });

    it('fails on empty input', function () {
        $parser = new LinuxProcNetDevParser;

        $result = $parser->parse('');

        expect($result->isFailure())->toBeTrue();
    });

    it('fails on invalid format', function () {
        $parser = new LinuxProcNetDevParser;
        $netDevContent = "invalid output\n";

        $result = $parser->parse($netDevContent);

        expect($result->isFailure())->toBeTrue();
    });

    it('handles interfaces with high traffic correctly', function () {
        $parser = new LinuxProcNetDevParser;
        $netDevContent = <<<'NETDEV'
Inter-|   Receive                                                |  Transmit
 face |bytes    packets errs drop fifo frame compressed multicast|bytes    packets errs drop fifo colls carrier compressed
  eth0: 1099511627776 1073741824  100   50    0     0          0         0  549755813888  536870912   50   25    0     0       0          0
NETDEV;

        $result = $parser->parse($netDevContent);
        $interfaces = $result->getValue();

        expect($interfaces[0]->stats->bytesReceived)->toBe(1099511627776);
        expect($interfaces[0]->stats->packetsReceived)->toBe(1073741824);
    });
});
