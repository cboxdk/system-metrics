<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Metrics\Container;

/**
 * Cgroup version detected on the system.
 */
enum CgroupVersion: string
{
    /**
     * Cgroup v1 (legacy) - separate controllers in /sys/fs/cgroup/{controller}
     */
    case V1 = 'v1';

    /**
     * Cgroup v2 (unified) - single hierarchy in /sys/fs/cgroup
     */
    case V2 = 'v2';

    /**
     * No cgroup support detected (non-Linux or cgroup not mounted)
     */
    case NONE = 'none';
}
