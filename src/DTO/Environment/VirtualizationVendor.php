<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Environment;

/**
 * Represents virtualization vendor/hypervisor type.
 */
enum VirtualizationVendor: string
{
    case KVM = 'KVM';
    case QEMU = 'QEMU';
    case VMware = 'VMware';
    case VirtualBox = 'VirtualBox';
    case Xen = 'Xen';
    case HyperV = 'Hyper-V';
    case Bochs = 'Bochs';
    case Parallels = 'Parallels';
    case AWS = 'AWS';
    case GoogleCloud = 'Google Cloud';
    case DigitalOcean = 'DigitalOcean';
    case Rosetta2 = 'Rosetta 2';
    case Unknown = 'unknown';
}
