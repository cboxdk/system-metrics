<?php

use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkConnectionStats;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterface;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterfaceStats;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterfaceType;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot;

describe('NetworkSnapshot', function () {
    it('can be instantiated with interfaces and connections', function () {
        $stats1 = new NetworkInterfaceStats(
            bytesReceived: 1024,
            bytesSent: 512,
            packetsReceived: 100,
            packetsSent: 50,
            receiveErrors: 0,
            transmitErrors: 0,
            receiveDrops: 0,
            transmitDrops: 0,
        );

        $interface1 = new NetworkInterface(
            name: 'eth0',
            type: NetworkInterfaceType::ETHERNET,
            macAddress: 'ab:cd:ef:12:34:56',
            stats: $stats1,
            isUp: true,
            mtu: 1500,
        );

        $connections = new NetworkConnectionStats(
            tcpEstablished: 100,
            tcpListening: 50,
            tcpTimeWait: 25,
            udpListening: 30,
            totalConnections: 205,
        );

        $snapshot = new NetworkSnapshot(
            interfaces: [$interface1],
            connections: $connections,
        );

        expect($snapshot->interfaces)->toHaveCount(1);
        expect($snapshot->connections)->toBe($connections);
    });

    it('calculates total bytes received across all interfaces', function () {
        $stats1 = new NetworkInterfaceStats(
            bytesReceived: 1024,
            bytesSent: 0,
            packetsReceived: 0,
            packetsSent: 0,
            receiveErrors: 0,
            transmitErrors: 0,
            receiveDrops: 0,
            transmitDrops: 0,
        );

        $stats2 = new NetworkInterfaceStats(
            bytesReceived: 2048,
            bytesSent: 0,
            packetsReceived: 0,
            packetsSent: 0,
            receiveErrors: 0,
            transmitErrors: 0,
            receiveDrops: 0,
            transmitDrops: 0,
        );

        $interface1 = new NetworkInterface(
            name: 'eth0',
            type: NetworkInterfaceType::ETHERNET,
            macAddress: '',
            stats: $stats1,
            isUp: true,
            mtu: 1500,
        );

        $interface2 = new NetworkInterface(
            name: 'wlan0',
            type: NetworkInterfaceType::WIFI,
            macAddress: '',
            stats: $stats2,
            isUp: true,
            mtu: 1500,
        );

        $snapshot = new NetworkSnapshot(
            interfaces: [$interface1, $interface2],
            connections: null,
        );

        expect($snapshot->totalBytesReceived())->toBe(3072);
    });

    it('calculates total bytes sent across all interfaces', function () {
        $stats1 = new NetworkInterfaceStats(
            bytesReceived: 0,
            bytesSent: 512,
            packetsReceived: 0,
            packetsSent: 0,
            receiveErrors: 0,
            transmitErrors: 0,
            receiveDrops: 0,
            transmitDrops: 0,
        );

        $stats2 = new NetworkInterfaceStats(
            bytesReceived: 0,
            bytesSent: 1024,
            packetsReceived: 0,
            packetsSent: 0,
            receiveErrors: 0,
            transmitErrors: 0,
            receiveDrops: 0,
            transmitDrops: 0,
        );

        $interface1 = new NetworkInterface(
            name: 'eth0',
            type: NetworkInterfaceType::ETHERNET,
            macAddress: '',
            stats: $stats1,
            isUp: true,
            mtu: 1500,
        );

        $interface2 = new NetworkInterface(
            name: 'wlan0',
            type: NetworkInterfaceType::WIFI,
            macAddress: '',
            stats: $stats2,
            isUp: true,
            mtu: 1500,
        );

        $snapshot = new NetworkSnapshot(
            interfaces: [$interface1, $interface2],
            connections: null,
        );

        expect($snapshot->totalBytesSent())->toBe(1536);
    });

    it('calculates total packets received across all interfaces', function () {
        $stats1 = new NetworkInterfaceStats(
            bytesReceived: 0,
            bytesSent: 0,
            packetsReceived: 100,
            packetsSent: 0,
            receiveErrors: 0,
            transmitErrors: 0,
            receiveDrops: 0,
            transmitDrops: 0,
        );

        $stats2 = new NetworkInterfaceStats(
            bytesReceived: 0,
            bytesSent: 0,
            packetsReceived: 200,
            packetsSent: 0,
            receiveErrors: 0,
            transmitErrors: 0,
            receiveDrops: 0,
            transmitDrops: 0,
        );

        $interface1 = new NetworkInterface(
            name: 'eth0',
            type: NetworkInterfaceType::ETHERNET,
            macAddress: '',
            stats: $stats1,
            isUp: true,
            mtu: 1500,
        );

        $interface2 = new NetworkInterface(
            name: 'wlan0',
            type: NetworkInterfaceType::WIFI,
            macAddress: '',
            stats: $stats2,
            isUp: true,
            mtu: 1500,
        );

        $snapshot = new NetworkSnapshot(
            interfaces: [$interface1, $interface2],
            connections: null,
        );

        expect($snapshot->totalPacketsReceived())->toBe(300);
    });

    it('calculates total packets sent across all interfaces', function () {
        $stats1 = new NetworkInterfaceStats(
            bytesReceived: 0,
            bytesSent: 0,
            packetsReceived: 0,
            packetsSent: 50,
            receiveErrors: 0,
            transmitErrors: 0,
            receiveDrops: 0,
            transmitDrops: 0,
        );

        $stats2 = new NetworkInterfaceStats(
            bytesReceived: 0,
            bytesSent: 0,
            packetsReceived: 0,
            packetsSent: 100,
            receiveErrors: 0,
            transmitErrors: 0,
            receiveDrops: 0,
            transmitDrops: 0,
        );

        $interface1 = new NetworkInterface(
            name: 'eth0',
            type: NetworkInterfaceType::ETHERNET,
            macAddress: '',
            stats: $stats1,
            isUp: true,
            mtu: 1500,
        );

        $interface2 = new NetworkInterface(
            name: 'wlan0',
            type: NetworkInterfaceType::WIFI,
            macAddress: '',
            stats: $stats2,
            isUp: true,
            mtu: 1500,
        );

        $snapshot = new NetworkSnapshot(
            interfaces: [$interface1, $interface2],
            connections: null,
        );

        expect($snapshot->totalPacketsSent())->toBe(150);
    });

    it('handles empty interfaces array', function () {
        $snapshot = new NetworkSnapshot(
            interfaces: [],
            connections: null,
        );

        expect($snapshot->totalBytesReceived())->toBe(0);
        expect($snapshot->totalBytesSent())->toBe(0);
        expect($snapshot->totalPacketsReceived())->toBe(0);
        expect($snapshot->totalPacketsSent())->toBe(0);
    });

    it('can have null connections', function () {
        $snapshot = new NetworkSnapshot(
            interfaces: [],
            connections: null,
        );

        expect($snapshot->connections)->toBeNull();
    });
});
