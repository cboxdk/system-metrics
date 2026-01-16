<?php

declare(strict_types=1);

use Cbox\SystemMetrics\Actions\ReadNetworkMetricsAction;
use Cbox\SystemMetrics\Contracts\NetworkMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkConnectionStats;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterface;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterfaceStats;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterfaceType;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\UnsupportedOperatingSystemException;

describe('ReadNetworkMetricsAction', function () {
    it('uses default source when none provided', function () {
        $action = new ReadNetworkMetricsAction;
        $result = $action->execute();

        expect($result)->toBeInstanceOf(Result::class);
    });

    it('can execute with custom source', function () {
        $mockSource = new class implements NetworkMetricsSource
        {
            public function read(): Result
            {
                return Result::success(
                    new NetworkSnapshot(
                        interfaces: [
                            new NetworkInterface(
                                name: 'eth0',
                                type: NetworkInterfaceType::ETHERNET,
                                macAddress: 'ab:cd:ef:12:34:56',
                                stats: new NetworkInterfaceStats(
                                    bytesReceived: 1_048_576,
                                    bytesSent: 524_288,
                                    packetsReceived: 1024,
                                    packetsSent: 512,
                                    receiveErrors: 0,
                                    transmitErrors: 0,
                                    receiveDrops: 0,
                                    transmitDrops: 0,
                                ),
                                isUp: true,
                                mtu: 1500,
                            ),
                        ],
                        connections: new NetworkConnectionStats(
                            tcpEstablished: 10,
                            tcpListening: 5,
                            tcpTimeWait: 2,
                            udpListening: 3,
                            totalConnections: 20,
                        )
                    )
                );
            }
        };

        $action = new ReadNetworkMetricsAction($mockSource);
        $result = $action->execute();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot)->toBeInstanceOf(NetworkSnapshot::class);
        expect($snapshot->interfaces)->toHaveCount(1);
        expect($snapshot->interfaces[0]->name)->toBe('eth0');
        expect($snapshot->connections)->toBeInstanceOf(NetworkConnectionStats::class);
        expect($snapshot->connections->tcpEstablished)->toBe(10);
    });

    it('propagates source errors', function () {
        $mockSource = new class implements NetworkMetricsSource
        {
            public function read(): Result
            {
                return Result::failure(
                    UnsupportedOperatingSystemException::forOs('Windows')
                );
            }
        };

        $action = new ReadNetworkMetricsAction($mockSource);
        $result = $action->execute();

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(UnsupportedOperatingSystemException::class);
    });

    it('handles empty interfaces list', function () {
        $mockSource = new class implements NetworkMetricsSource
        {
            public function read(): Result
            {
                return Result::success(
                    new NetworkSnapshot(
                        interfaces: [],
                        connections: null
                    )
                );
            }
        };

        $action = new ReadNetworkMetricsAction($mockSource);
        $result = $action->execute();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot->interfaces)->toBeEmpty();
        expect($snapshot->connections)->toBeNull();
    });

    it('handles multiple interfaces with various types', function () {
        $mockSource = new class implements NetworkMetricsSource
        {
            public function read(): Result
            {
                return Result::success(
                    new NetworkSnapshot(
                        interfaces: [
                            new NetworkInterface(
                                name: 'lo',
                                type: NetworkInterfaceType::LOOPBACK,
                                macAddress: '00:00:00:00:00:00',
                                stats: new NetworkInterfaceStats(
                                    bytesReceived: 1_048_576,
                                    bytesSent: 1_048_576,
                                    packetsReceived: 1024,
                                    packetsSent: 1024,
                                    receiveErrors: 0,
                                    transmitErrors: 0,
                                    receiveDrops: 0,
                                    transmitDrops: 0,
                                ),
                                isUp: true,
                                mtu: 65536,
                            ),
                            new NetworkInterface(
                                name: 'eth0',
                                type: NetworkInterfaceType::ETHERNET,
                                macAddress: 'ab:cd:ef:12:34:56',
                                stats: new NetworkInterfaceStats(
                                    bytesReceived: 10_485_760,
                                    bytesSent: 5_242_880,
                                    packetsReceived: 10240,
                                    packetsSent: 5120,
                                    receiveErrors: 10,
                                    transmitErrors: 5,
                                    receiveDrops: 5,
                                    transmitDrops: 2,
                                ),
                                isUp: true,
                                mtu: 1500,
                            ),
                            new NetworkInterface(
                                name: 'wlan0',
                                type: NetworkInterfaceType::WIFI,
                                macAddress: '12:34:56:78:9a:bc',
                                stats: new NetworkInterfaceStats(
                                    bytesReceived: 2_097_152,
                                    bytesSent: 1_048_576,
                                    packetsReceived: 2048,
                                    packetsSent: 1024,
                                    receiveErrors: 0,
                                    transmitErrors: 0,
                                    receiveDrops: 0,
                                    transmitDrops: 0,
                                ),
                                isUp: true,
                                mtu: 1500,
                            ),
                        ],
                        connections: new NetworkConnectionStats(
                            tcpEstablished: 25,
                            tcpListening: 10,
                            tcpTimeWait: 5,
                            udpListening: 8,
                            totalConnections: 48,
                        )
                    )
                );
            }
        };

        $action = new ReadNetworkMetricsAction($mockSource);
        $result = $action->execute();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot->interfaces)->toHaveCount(3);
        expect($snapshot->interfaces[0]->type)->toBe(NetworkInterfaceType::LOOPBACK);
        expect($snapshot->interfaces[1]->type)->toBe(NetworkInterfaceType::ETHERNET);
        expect($snapshot->interfaces[2]->type)->toBe(NetworkInterfaceType::WIFI);
        expect($snapshot->connections->totalConnections)->toBe(48);
    });

    it('handles null connection stats', function () {
        $mockSource = new class implements NetworkMetricsSource
        {
            public function read(): Result
            {
                return Result::success(
                    new NetworkSnapshot(
                        interfaces: [
                            new NetworkInterface(
                                name: 'eth0',
                                type: NetworkInterfaceType::ETHERNET,
                                macAddress: 'ab:cd:ef:12:34:56',
                                stats: new NetworkInterfaceStats(
                                    bytesReceived: 1_048_576,
                                    bytesSent: 524_288,
                                    packetsReceived: 1024,
                                    packetsSent: 512,
                                    receiveErrors: 0,
                                    transmitErrors: 0,
                                    receiveDrops: 0,
                                    transmitDrops: 0,
                                ),
                                isUp: true,
                                mtu: 1500,
                            ),
                        ],
                        connections: null
                    )
                );
            }
        };

        $action = new ReadNetworkMetricsAction($mockSource);
        $result = $action->execute();

        expect($result->isSuccess())->toBeTrue();
        $snapshot = $result->getValue();
        expect($snapshot->interfaces)->toHaveCount(1);
        expect($snapshot->connections)->toBeNull();
    });
});
