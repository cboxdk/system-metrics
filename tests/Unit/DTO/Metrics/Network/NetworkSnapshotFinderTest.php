<?php

use PHPeek\SystemMetrics\DTO\Metrics\Network\NetworkInterface;
use PHPeek\SystemMetrics\DTO\Metrics\Network\NetworkInterfaceStats;
use PHPeek\SystemMetrics\DTO\Metrics\Network\NetworkInterfaceType;
use PHPeek\SystemMetrics\DTO\Metrics\Network\NetworkSnapshot;

describe('NetworkSnapshot Finder Methods', function () {
    beforeEach(function () {
        $this->interfaces = [
            new NetworkInterface(
                name: 'eth0',
                type: NetworkInterfaceType::ETHERNET,
                macAddress: '00:11:22:33:44:55',
                isUp: true,
                mtu: 1500,
                stats: new NetworkInterfaceStats(
                    bytesReceived: 1000000,
                    bytesSent: 500000,
                    packetsReceived: 10000,
                    packetsSent: 5000,
                    receiveErrors: 0,
                    transmitErrors: 0,
                    receiveDrops: 0,
                    transmitDrops: 0,
                ),
            ),
            new NetworkInterface(
                name: 'wlan0',
                type: NetworkInterfaceType::WIFI,
                macAddress: 'AA:BB:CC:DD:EE:FF',
                isUp: true,
                mtu: 1500,
                stats: new NetworkInterfaceStats(
                    bytesReceived: 2000000,
                    bytesSent: 1000000,
                    packetsReceived: 20000,
                    packetsSent: 10000,
                    receiveErrors: 5,
                    transmitErrors: 2,
                    receiveDrops: 1,
                    transmitDrops: 0,
                ),
            ),
            new NetworkInterface(
                name: 'lo',
                type: NetworkInterfaceType::LOOPBACK,
                macAddress: '',
                isUp: true,
                mtu: 65536,
                stats: new NetworkInterfaceStats(
                    bytesReceived: 500000,
                    bytesSent: 500000,
                    packetsReceived: 5000,
                    packetsSent: 5000,
                    receiveErrors: 0,
                    transmitErrors: 0,
                    receiveDrops: 0,
                    transmitDrops: 0,
                ),
            ),
            new NetworkInterface(
                name: 'eth1',
                type: NetworkInterfaceType::ETHERNET,
                macAddress: '11:22:33:44:55:66',
                isUp: false,
                mtu: 1500,
                stats: new NetworkInterfaceStats(
                    bytesReceived: 0,
                    bytesSent: 0,
                    packetsReceived: 0,
                    packetsSent: 0,
                    receiveErrors: 0,
                    transmitErrors: 0,
                    receiveDrops: 0,
                    transmitDrops: 0,
                ),
            ),
        ];

        $this->snapshot = new NetworkSnapshot(
            interfaces: $this->interfaces,
            connections: null,
        );
    });

    describe('findInterface', function () {
        it('finds interface by exact name', function () {
            $interface = $this->snapshot->findInterface('eth0');

            expect($interface)->not->toBeNull();
            expect($interface->name)->toBe('eth0');
            expect($interface->type)->toBe(NetworkInterfaceType::ETHERNET);
        });

        it('finds wireless interface', function () {
            $interface = $this->snapshot->findInterface('wlan0');

            expect($interface)->not->toBeNull();
            expect($interface->type)->toBe(NetworkInterfaceType::WIFI);
        });

        it('returns null for non-existent interface', function () {
            $interface = $this->snapshot->findInterface('eth999');

            expect($interface)->toBeNull();
        });
    });

    describe('findByType', function () {
        it('finds all ethernet interfaces', function () {
            $interfaces = $this->snapshot->findByType(NetworkInterfaceType::ETHERNET);

            expect($interfaces)->toHaveCount(2);
            expect($interfaces[0]->name)->toBe('eth0');
            expect($interfaces[1]->name)->toBe('eth1');
        });

        it('finds single wifi interface', function () {
            $interfaces = $this->snapshot->findByType(NetworkInterfaceType::WIFI);

            expect($interfaces)->toHaveCount(1);
            expect($interfaces[0]->name)->toBe('wlan0');
        });

        it('finds loopback interface', function () {
            $interfaces = $this->snapshot->findByType(NetworkInterfaceType::LOOPBACK);

            expect($interfaces)->toHaveCount(1);
            expect($interfaces[0]->name)->toBe('lo');
        });

        it('returns empty array for type with no interfaces', function () {
            $interfaces = $this->snapshot->findByType(NetworkInterfaceType::VPN);

            expect($interfaces)->toBeEmpty();
        });

        it('returns re-indexed array', function () {
            $interfaces = $this->snapshot->findByType(NetworkInterfaceType::ETHERNET);

            expect(array_keys($interfaces))->toBe([0, 1]);
        });
    });

    describe('findActiveInterfaces', function () {
        it('finds all interfaces that are up', function () {
            $interfaces = $this->snapshot->findActiveInterfaces();

            expect($interfaces)->toHaveCount(3);
            foreach ($interfaces as $interface) {
                expect($interface->isUp)->toBeTrue();
            }
        });

        it('excludes interfaces that are down', function () {
            $interfaces = $this->snapshot->findActiveInterfaces();

            $names = array_map(fn ($i) => $i->name, $interfaces);
            expect($names)->not->toContain('eth1');
        });

        it('returns re-indexed array', function () {
            $interfaces = $this->snapshot->findActiveInterfaces();

            expect(array_keys($interfaces))->toBe([0, 1, 2]);
        });
    });

    describe('findByMacAddress', function () {
        it('finds interface by exact MAC address', function () {
            $interface = $this->snapshot->findByMacAddress('00:11:22:33:44:55');

            expect($interface)->not->toBeNull();
            expect($interface->name)->toBe('eth0');
        });

        it('finds interface with uppercase MAC', function () {
            $interface = $this->snapshot->findByMacAddress('AA:BB:CC:DD:EE:FF');

            expect($interface)->not->toBeNull();
            expect($interface->name)->toBe('wlan0');
        });

        it('returns null for non-existent MAC address', function () {
            $interface = $this->snapshot->findByMacAddress('FF:FF:FF:FF:FF:FF');

            expect($interface)->toBeNull();
        });

        it('handles empty MAC address (loopback)', function () {
            $interface = $this->snapshot->findByMacAddress('');

            expect($interface)->not->toBeNull();
            expect($interface->name)->toBe('lo');
            expect($interface->type)->toBe(NetworkInterfaceType::LOOPBACK);
        });
    });
});
