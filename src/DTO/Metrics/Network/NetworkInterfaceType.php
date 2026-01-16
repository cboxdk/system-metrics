<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Metrics\Network;

/**
 * Network interface type enumeration.
 */
enum NetworkInterfaceType: string
{
    case ETHERNET = 'ethernet';
    case WIFI = 'wifi';
    case LOOPBACK = 'loopback';
    case BRIDGE = 'bridge';
    case VLAN = 'vlan';
    case TUN = 'tun';
    case TAP = 'tap';
    case VPN = 'vpn';
    case CELLULAR = 'cellular';
    case BLUETOOTH = 'bluetooth';
    case OTHER = 'other';

    /**
     * Create from interface name heuristics.
     */
    public static function fromInterfaceName(string $name): self
    {
        $normalized = strtolower(trim($name));

        return match (true) {
            str_starts_with($normalized, 'lo') => self::LOOPBACK,
            str_starts_with($normalized, 'eth') => self::ETHERNET,
            str_starts_with($normalized, 'en') => self::ETHERNET,
            str_starts_with($normalized, 'wlan') => self::WIFI,
            str_starts_with($normalized, 'wl') => self::WIFI,
            str_starts_with($normalized, 'wi') => self::WIFI,
            str_starts_with($normalized, 'br') => self::BRIDGE,
            str_starts_with($normalized, 'vlan') => self::VLAN,
            str_starts_with($normalized, 'tun') => self::TUN,
            str_starts_with($normalized, 'tap') => self::TAP,
            str_starts_with($normalized, 'vpn') => self::VPN,
            str_starts_with($normalized, 'ppp') => self::CELLULAR,
            str_starts_with($normalized, 'wwan') => self::CELLULAR,
            str_starts_with($normalized, 'bt') => self::BLUETOOTH,
            default => self::OTHER,
        };
    }
}
