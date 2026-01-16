<?php

use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterfaceStats;

describe('NetworkInterfaceStats', function () {
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

        expect($stats->bytesReceived)->toBe(1024 * 1024);
        expect($stats->bytesSent)->toBe(512 * 1024);
        expect($stats->packetsReceived)->toBe(1000);
        expect($stats->packetsSent)->toBe(500);
        expect($stats->receiveErrors)->toBe(10);
        expect($stats->transmitErrors)->toBe(5);
        expect($stats->receiveDrops)->toBe(2);
        expect($stats->transmitDrops)->toBe(1);
    });

    it('calculates total bytes correctly', function () {
        $stats = new NetworkInterfaceStats(
            bytesReceived: 1024,
            bytesSent: 512,
            packetsReceived: 0,
            packetsSent: 0,
            receiveErrors: 0,
            transmitErrors: 0,
            receiveDrops: 0,
            transmitDrops: 0,
        );

        expect($stats->totalBytes())->toBe(1536);
    });

    it('calculates total packets correctly', function () {
        $stats = new NetworkInterfaceStats(
            bytesReceived: 0,
            bytesSent: 0,
            packetsReceived: 1000,
            packetsSent: 500,
            receiveErrors: 0,
            transmitErrors: 0,
            receiveDrops: 0,
            transmitDrops: 0,
        );

        expect($stats->totalPackets())->toBe(1500);
    });

    it('calculates total errors correctly', function () {
        $stats = new NetworkInterfaceStats(
            bytesReceived: 0,
            bytesSent: 0,
            packetsReceived: 0,
            packetsSent: 0,
            receiveErrors: 10,
            transmitErrors: 5,
            receiveDrops: 0,
            transmitDrops: 0,
        );

        expect($stats->totalErrors())->toBe(15);
    });

    it('calculates total drops correctly', function () {
        $stats = new NetworkInterfaceStats(
            bytesReceived: 0,
            bytesSent: 0,
            packetsReceived: 0,
            packetsSent: 0,
            receiveErrors: 0,
            transmitErrors: 0,
            receiveDrops: 2,
            transmitDrops: 1,
        );

        expect($stats->totalDrops())->toBe(3);
    });

    it('handles zero values', function () {
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

        expect($stats->totalBytes())->toBe(0);
        expect($stats->totalPackets())->toBe(0);
        expect($stats->totalErrors())->toBe(0);
        expect($stats->totalDrops())->toBe(0);
    });
});
