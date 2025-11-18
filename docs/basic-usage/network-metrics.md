# Network Metrics

Get network interface statistics and connection information.

## Overview

```php
use PHPeek\SystemMetrics\SystemMetrics;

$network = SystemMetrics::network()->getValue();
```

## Network Interfaces

```php
foreach ($network->interfaces as $interface) {
    echo "Interface: {$interface->name}\n";
    echo "Type: {$interface->type->value}\n";
    echo "MAC Address: {$interface->macAddress}\n";
    echo "Status: " . ($interface->isUp ? 'UP' : 'DOWN') . "\n";
    echo "MTU: {$interface->mtu}\n";

    // Traffic statistics (cumulative counters)
    $stats = $interface->stats;
    echo "Received: " . round($stats->bytesReceived / 1024**2, 2) . " MB ({$stats->packetsReceived} packets)\n";
    echo "Sent: " . round($stats->bytesSent / 1024**2, 2) . " MB ({$stats->packetsSent} packets)\n";
    echo "Errors: RX {$stats->receiveErrors}, TX {$stats->transmitErrors}\n";
    echo "Drops: RX {$stats->receiveDrops}, TX {$stats->transmitDrops}\n";
    echo "Total: " . round($stats->totalBytes() / 1024**2, 2) . " MB\n\n";
}
```

**Note:** Network counters are cumulative since boot. To get bandwidth (MB/s), take two snapshots and calculate the delta.

## Connection Statistics

```php
if ($network->connections !== null) {
    $conn = $network->connections;
    echo "TCP Established: {$conn->tcpEstablished}\n";
    echo "TCP Listening: {$conn->tcpListening}\n";
    echo "TCP Time Wait: {$conn->tcpTimeWait}\n";
    echo "UDP Listening: {$conn->udpListening}\n";
    echo "Total Connections: {$conn->totalConnections}\n";
}
```

## Aggregate Statistics

```php
echo "Total Received: " . round($network->totalBytesReceived() / 1024**3, 2) . " GB\n";
echo "Total Sent: " . round($network->totalBytesSent() / 1024**3, 2) . " GB\n";
```

## Interface Types

- `ethernet`: Wired Ethernet (eth*, en*)
- `wifi`: Wireless (wlan*, wi*)
- `loopback`: Loopback (lo, lo0)
- `bridge`, `vlan`, `vpn`, `cellular`, `bluetooth`, `other`

## Use Cases

### Bandwidth Monitoring

```php
$snap1 = SystemMetrics::network()->getValue();
sleep(1);
$snap2 = SystemMetrics::network()->getValue();

foreach ($snap2->interfaces as $i => $iface) {
    $prevIface = $snap1->interfaces[$i];
    $bytesDelta = $iface->stats->totalBytes() - $prevIface->stats->totalBytes();
    $mbps = ($bytesDelta * 8) / 1024 / 1024; // Convert to Mbps
    echo "{$iface->name}: " . round($mbps, 2) . " Mbps\n";
}
```

## Related Documentation

- [System Overview](system-overview.md)
