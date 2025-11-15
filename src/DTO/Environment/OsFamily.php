<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Environment;

/**
 * Represents the operating system family.
 */
enum OsFamily: string
{
    case Linux = 'linux';
    case MacOs = 'macos';
    case Unknown = 'unknown';
}
