<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Environment;

/**
 * Represents the cgroup version.
 */
enum CgroupVersion: string
{
    case V1 = 'v1';
    case V2 = 'v2';
    case None = 'none';
    case Unknown = 'unknown';
}
