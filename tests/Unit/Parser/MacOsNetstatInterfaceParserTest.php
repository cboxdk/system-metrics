<?php

use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterface;
use Cbox\SystemMetrics\DTO\Metrics\Network\NetworkInterfaceType;
use Cbox\SystemMetrics\Support\Parser\MacOsNetstatInterfaceParser;

describe('MacOsNetstatInterfaceParser', function () {
    it('can parse macOS netstat -ib output', function () {
        $parser = new MacOsNetstatInterfaceParser;
        $netstatOutput = <<<'NETSTAT'
Name  Mtu   Network       Address            Ipkts Ierrs     Ibytes Opkts Oerrs     Obytes  Coll
lo0   16384 <Link#1>      00:00:00:00:00:00 1024000     0  104857600 1024000     0  104857600     0
en0   1500  <Link#4>      ab:cd:ef:12:34:56 10240000    10 1073741824 5120000     5  536870912     0
NETSTAT;

        $result = $parser->parse($netstatOutput);

        expect($result->isSuccess())->toBeTrue();

        $interfaces = $result->getValue();
        expect($interfaces)->toHaveCount(2);

        $lo0 = $interfaces[0];
        expect($lo0)->toBeInstanceOf(NetworkInterface::class);
        expect($lo0->name)->toBe('lo0');
        expect($lo0->type)->toBe(NetworkInterfaceType::LOOPBACK);
        expect($lo0->macAddress)->toBe('00:00:00:00:00:00'); // Loopback MAC address
        expect($lo0->mtu)->toBe(16384);
        expect($lo0->stats->packetsReceived)->toBe(1024000);
        expect($lo0->stats->receiveErrors)->toBe(0);
        expect($lo0->stats->bytesReceived)->toBe(104857600);
        expect($lo0->stats->packetsSent)->toBe(1024000);
        expect($lo0->stats->transmitErrors)->toBe(0);
        expect($lo0->stats->bytesSent)->toBe(104857600);

        $en0 = $interfaces[1];
        expect($en0->name)->toBe('en0');
        expect($en0->type)->toBe(NetworkInterfaceType::ETHERNET);
        expect($en0->macAddress)->toBe('ab:cd:ef:12:34:56');
        expect($en0->mtu)->toBe(1500);
        expect($en0->stats->receiveErrors)->toBe(10);
        expect($en0->stats->transmitErrors)->toBe(5);
    });

    it('detects interface types from names', function () {
        $parser = new MacOsNetstatInterfaceParser;
        $netstatOutput = <<<'NETSTAT'
Name  Mtu   Network       Address            Ipkts Ierrs     Ibytes Opkts Oerrs     Obytes  Coll
lo0   16384 <Link#1>      00:00:00:00:00:00   1000     0    1000000 1000     0    1000000     0
en0   1500  <Link#2>      ab:cd:ef:12:34:56   2000     0    2000000 2000     0    2000000     0
en1   1500  <Link#3>      12:34:56:ab:cd:ef   3000     0    3000000 3000     0    3000000     0
NETSTAT;

        $result = $parser->parse($netstatOutput);
        $interfaces = $result->getValue();

        expect($interfaces[0]->type)->toBe(NetworkInterfaceType::LOOPBACK);
        expect($interfaces[1]->type)->toBe(NetworkInterfaceType::ETHERNET);
        expect($interfaces[2]->type)->toBe(NetworkInterfaceType::ETHERNET);
    });

    it('validates MAC address format', function () {
        $parser = new MacOsNetstatInterfaceParser;
        $netstatOutput = <<<'NETSTAT'
Name  Mtu   Network       Address            Ipkts Ierrs     Ibytes Opkts Oerrs     Obytes  Coll
en0   1500  <Link#4>      ab:cd:ef:12:34:56 10000     0   10000000 5000     0    5000000     0
en1   1500  <Link#5>      invalid-mac       20000     0   20000000 10000    0   10000000     0
NETSTAT;

        $result = $parser->parse($netstatOutput);
        $interfaces = $result->getValue();

        expect($interfaces[0]->macAddress)->toBe('ab:cd:ef:12:34:56');
        expect($interfaces[1]->macAddress)->toBe(''); // Invalid MAC, set to empty
    });

    it('sets interface as up by default', function () {
        $parser = new MacOsNetstatInterfaceParser;
        $netstatOutput = <<<'NETSTAT'
Name  Mtu   Network       Address            Ipkts Ierrs     Ibytes Opkts Oerrs     Obytes  Coll
en0   1500  <Link#4>      ab:cd:ef:12:34:56 10000     0   10000000 5000     0    5000000     0
NETSTAT;

        $result = $parser->parse($netstatOutput);
        $interfaces = $result->getValue();

        expect($interfaces[0]->isUp)->toBe(true);
    });

    it('sets drops to zero', function () {
        $parser = new MacOsNetstatInterfaceParser;
        $netstatOutput = <<<'NETSTAT'
Name  Mtu   Network       Address            Ipkts Ierrs     Ibytes Opkts Oerrs     Obytes  Coll
en0   1500  <Link#4>      ab:cd:ef:12:34:56 10000     0   10000000 5000     0    5000000     0
NETSTAT;

        $result = $parser->parse($netstatOutput);
        $interfaces = $result->getValue();

        // netstat -ib doesn't provide drop counts
        expect($interfaces[0]->stats->receiveDrops)->toBe(0);
        expect($interfaces[0]->stats->transmitDrops)->toBe(0);
    });

    it('handles loopback without MAC address', function () {
        $parser = new MacOsNetstatInterfaceParser;
        $netstatOutput = <<<'NETSTAT'
Name  Mtu   Network       Address            Ipkts Ierrs     Ibytes Opkts Oerrs     Obytes  Coll
lo0   16384 <Link#1>      00:00:00:00:00:00 1024000     0  104857600 1024000     0  104857600     0
NETSTAT;

        $result = $parser->parse($netstatOutput);
        $interfaces = $result->getValue();

        // Loopback has all-zeros MAC address
        expect($interfaces[0]->macAddress)->toBe('00:00:00:00:00:00');
    });

    it('handles output with only header line', function () {
        $parser = new MacOsNetstatInterfaceParser;
        $netstatOutput = <<<'NETSTAT'
Name  Mtu   Network       Address            Ipkts Ierrs     Ibytes Opkts Oerrs     Obytes  Coll
NETSTAT;

        $result = $parser->parse($netstatOutput);

        // Parser fails when output is too short
        expect($result->isFailure())->toBeTrue();
    });

    it('fails on empty input', function () {
        $parser = new MacOsNetstatInterfaceParser;

        $result = $parser->parse('');

        expect($result->isFailure())->toBeTrue();
    });

    it('fails on invalid format', function () {
        $parser = new MacOsNetstatInterfaceParser;
        $netstatOutput = "invalid output\n";

        $result = $parser->parse($netstatOutput);

        expect($result->isFailure())->toBeTrue();
    });

    it('handles interfaces with high traffic correctly', function () {
        $parser = new MacOsNetstatInterfaceParser;
        $netstatOutput = <<<'NETSTAT'
Name  Mtu   Network       Address            Ipkts Ierrs     Ibytes Opkts Oerrs     Obytes  Coll
en0   1500  <Link#4>      ab:cd:ef:12:34:56 1073741824   100 1099511627776 536870912    50  549755813888     0
NETSTAT;

        $result = $parser->parse($netstatOutput);
        $interfaces = $result->getValue();

        expect($interfaces[0]->stats->packetsReceived)->toBe(1073741824);
        expect($interfaces[0]->stats->bytesReceived)->toBe(1099511627776);
    });

    it('skips header line correctly', function () {
        $parser = new MacOsNetstatInterfaceParser;
        $netstatOutput = <<<'NETSTAT'
Name  Mtu   Network       Address            Ipkts Ierrs     Ibytes Opkts Oerrs     Obytes  Coll
en0   1500  <Link#4>      ab:cd:ef:12:34:56 10000     0   10000000 5000     0    5000000     0
NETSTAT;

        $result = $parser->parse($netstatOutput);
        $interfaces = $result->getValue();

        // Should only count the interface, not the header
        expect($interfaces)->toHaveCount(1);
    });
});
