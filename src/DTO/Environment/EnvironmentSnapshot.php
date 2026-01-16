<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Environment;

/**
 * Complete snapshot of the system environment.
 */
final readonly class EnvironmentSnapshot
{
    public function __construct(
        public OperatingSystem $os,
        public Kernel $kernel,
        public Architecture $architecture,
        public Virtualization $virtualization,
        public Containerization $containerization,
        public Cgroup $cgroup,
    ) {}
}
