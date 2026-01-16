<?php

use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterface;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterfaceStats;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterfaceType;

describe('NetworkInterface', function () {
    it('can be instantiated with all values', function () {
        $stats = new NetworkInterfaceStats(
            bytesReceived: 1024 * 1024,
            bytesSent: 512 * 1024,
            packetsReceived: 1000,
            packetsSent: 500,
            receiveErrors: 10,
            transmitErrors: 5,
            receiveDrops: 2,
            transmitDrops: 1,
        );

        $interface = new NetworkInterface(
            name: 'eth0',
            type: NetworkInterfaceType::ETHERNET,
            macAddress: 'ab:cd:ef:12:34:56',
            stats: $stats,
            isUp: true,
            mtu: 1500,
        );

        expect($interface->name)->toBe('eth0');
        expect($interface->type)->toBe(NetworkInterfaceType::ETHERNET);
        expect($interface->macAddress)->toBe('ab:cd:ef:12:34:56');
        expect($interface->stats)->toBe($stats);
        expect($interface->isUp)->toBe(true);
        expect($interface->mtu)->toBe(1500);
    });

    it('can represent a loopback interface', function () {
        $stats = new NetworkInterfaceStats(
            bytesReceived: 0,
            bytesSent: 0,
            packetsReceived: 0,
            packetsSent: 0,
            receiveErrors: 0,
            transmitErrors: 0,
            receiveDrops: 0,
            transmitDrops: 0,
        );

        $interface = new NetworkInterface(
            name: 'lo0',
            type: NetworkInterfaceType::LOOPBACK,
            macAddress: '',
            stats: $stats,
            isUp: true,
            mtu: 16384,
        );

        expect($interface->type)->toBe(NetworkInterfaceType::LOOPBACK);
        expect($interface->macAddress)->toBe('');
    });

    it('can represent an interface that is down', function () {
        $stats = new NetworkInterfaceStats(
            bytesReceived: 0,
            bytesSent: 0,
            packetsReceived: 0,
            packetsSent: 0,
            receiveErrors: 0,
            transmitErrors: 0,
            receiveDrops: 0,
            transmitDrops: 0,
        );

        $interface = new NetworkInterface(
            name: 'eth1',
            type: NetworkInterfaceType::ETHERNET,
            macAddress: '',
            stats: $stats,
            isUp: false,
            mtu: 1500,
        );

        expect($interface->isUp)->toBe(false);
    });
});
