<?php

use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkConnectionStats;

describe('NetworkConnectionStats', function () {
    it('can be instantiated with all values', function () {
        $stats = new NetworkConnectionStats(
            tcpEstablished: 100,
            tcpListening: 50,
            tcpTimeWait: 25,
            udpListening: 30,
            totalConnections: 205,
        );

        expect($stats->tcpEstablished)->toBe(100);
        expect($stats->tcpListening)->toBe(50);
        expect($stats->tcpTimeWait)->toBe(25);
        expect($stats->udpListening)->toBe(30);
        expect($stats->totalConnections)->toBe(205);
    });

    it('handles zero values', function () {
        $stats = new NetworkConnectionStats(
            tcpEstablished: 0,
            tcpListening: 0,
            tcpTimeWait: 0,
            udpListening: 0,
            totalConnections: 0,
        );

        expect($stats->tcpEstablished)->toBe(0);
        expect($stats->tcpListening)->toBe(0);
        expect($stats->tcpTimeWait)->toBe(0);
        expect($stats->udpListening)->toBe(0);
        expect($stats->totalConnections)->toBe(0);
    });
});
