<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Environment;

/**
 * Represents the container technology type.
 */
enum ContainerType: string
{
    case None = 'none';
    case Docker = 'docker';
    case Containerd = 'containerd';
    case Crio = 'crio';
    case Kubernetes = 'kubernetes';
    case Other = 'other';
}
