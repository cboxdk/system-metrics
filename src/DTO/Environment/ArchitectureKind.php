<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Environment;

/**
 * Represents the CPU architecture type.
 */
enum ArchitectureKind: string
{
    case X86_64 = 'x86_64';
    case Arm64 = 'arm64';
    case Other = 'other';
}
