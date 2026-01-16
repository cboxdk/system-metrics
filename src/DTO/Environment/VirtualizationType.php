<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Environment;

/**
 * Represents the virtualization type.
 */
enum VirtualizationType: string
{
    case BareMetal = 'bare_metal';
    case VirtualMachine = 'virtual_machine';
    case Unknown = 'unknown';
}
