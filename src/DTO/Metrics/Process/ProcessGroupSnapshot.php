<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\DTO\Metrics\Process;

use DateTimeImmutable;

/**
 * Snapshot of a process group (parent + all children).
 */
final readonly class ProcessGroupSnapshot
{
    /**
     * @param  ProcessSnapshot[]  $children
     */
    public function __construct(
        public int $rootPid,
        public ProcessSnapshot $root,
        public array $children,
        public DateTimeImmutable $timestamp,
    ) {}

    /**
     * Total number of processes (root + children).
     */
    public function totalProcessCount(): int
    {
        return 1 + count($this->children);
    }

    /**
     * Aggregate memory usage across all processes.
     */
    public function aggregateMemoryRss(): int
    {
        $total = $this->root->resources->memoryRssBytes;

        foreach ($this->children as $child) {
            $total += $child->resources->memoryRssBytes;
        }

        return $total;
    }

    /**
     * Aggregate virtual memory across all processes.
     */
    public function aggregateMemoryVms(): int
    {
        $total = $this->root->resources->memoryVmsBytes;

        foreach ($this->children as $child) {
            $total += $child->resources->memoryVmsBytes;
        }

        return $total;
    }
}
