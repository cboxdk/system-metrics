<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Metrics;

/**
 * Source of system resource limits.
 */
enum LimitSource: string
{
    /**
     * Limits from host system (bare metal or VM).
     */
    case HOST = 'host';

    /**
     * Limits from cgroup v1 (Docker, Kubernetes older versions).
     */
    case CGROUP_V1 = 'cgroup_v1';

    /**
     * Limits from cgroup v2 (Modern Docker, Kubernetes).
     */
    case CGROUP_V2 = 'cgroup_v2';
}
