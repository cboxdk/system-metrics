<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Environment;

/**
 * Represents the operating system family.
 */
enum OsFamily: string
{
    case Linux = 'linux';
    case MacOs = 'macos';
    case Windows = 'windows';
    case Unknown = 'unknown';
}
