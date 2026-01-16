<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Process;

use Cbox\SystemMetrics\Contracts\FileReaderInterface;
use Cbox\SystemMetrics\Contracts\ProcessMetricsSource;
use Cbox\SystemMetrics\DTO\Metrics\Process\ProcessGroupSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Support\FileReader;
use Cbox\SystemMetrics\Support\Parser\LinuxProcPidStatParser;
use DateTimeImmutable;

/**
 * Reads process metrics from Linux /proc/{pid}/ filesystem.
 */
final class LinuxProcProcessMetricsSource implements ProcessMetricsSource
{
    public function __construct(
        private readonly FileReaderInterface $fileReader = new FileReader,
        private readonly LinuxProcPidStatParser $parser = new LinuxProcPidStatParser,
    ) {}

    public function read(int $pid): Result
    {
        $result = $this->fileReader->read("/proc/{$pid}/stat");

        if ($result->isFailure()) {
            $error = $result->getError();
            assert($error !== null);

            /** @var Result<\Cbox\SystemMetrics\DTO\Metrics\Process\ProcessSnapshot> */
            return Result::failure($error);
        }

        return $this->parser->parse($result->getValue(), $pid);
    }

    public function readProcessGroup(int $rootPid): Result
    {
        // Read root process
        $rootResult = $this->read($rootPid);
        if ($rootResult->isFailure()) {
            $error = $rootResult->getError();
            assert($error !== null);

            /** @var Result<\Cbox\SystemMetrics\DTO\Metrics\Process\ProcessGroupSnapshot> */
            return Result::failure($error);
        }

        $root = $rootResult->getValue();
        $children = [];

        // Find all child PIDs recursively
        $childPids = $this->findChildPids($rootPid);

        // Read each child process (best-effort, skip if process exits)
        foreach ($childPids as $childPid) {
            $childResult = $this->read($childPid);
            if ($childResult->isSuccess()) {
                $children[] = $childResult->getValue();
            }
            // Silently skip failed reads (process may have exited)
        }

        return Result::success(new ProcessGroupSnapshot(
            rootPid: $rootPid,
            root: $root,
            children: $children,
            timestamp: new DateTimeImmutable
        ));
    }

    /**
     * Find all child PIDs recursively.
     *
     * Optimized O(N) algorithm: scans /proc once to build parent→children map,
     * then traverses recursively without re-scanning.
     *
     * @return int[]
     */
    private function findChildPids(int $parentPid): array
    {
        // Build parent→children map in a single O(N) scan
        $parentToChildren = $this->buildProcessTree();

        // Recursively collect all descendants
        return $this->collectDescendants($parentPid, $parentToChildren);
    }

    /**
     * Build a map of parent PID to array of child PIDs.
     *
     * @return array<int, int[]>
     */
    private function buildProcessTree(): array
    {
        $parentToChildren = [];

        // Read /proc directory to find all processes
        $procDirs = @glob('/proc/[0-9]*', GLOB_ONLYDIR);
        if ($procDirs === false) {
            return [];
        }

        foreach ($procDirs as $procDir) {
            $pid = (int) basename($procDir);

            // Read /proc/{pid}/stat to get parent PID
            $statResult = $this->fileReader->read("{$procDir}/stat");
            if ($statResult->isFailure()) {
                continue;  // Process may have exited
            }

            $content = $statResult->getValue();

            // Extract PPID (field 4) from stat file
            $closingParen = strrpos($content, ')');
            if ($closingParen === false) {
                continue;
            }

            $afterName = substr($content, $closingParen + 2);
            $fields = preg_split('/\s+/', $afterName);

            if ($fields === false || count($fields) < 2) {
                continue;
            }

            $ppid = (int) $fields[1];  // Field 4

            // Add this PID to its parent's children list
            if (! isset($parentToChildren[$ppid])) {
                $parentToChildren[$ppid] = [];
            }
            $parentToChildren[$ppid][] = $pid;
        }

        return $parentToChildren;
    }

    /**
     * Recursively collect all descendant PIDs from the process tree.
     *
     * @param  array<int, int[]>  $parentToChildren
     * @return int[]
     */
    private function collectDescendants(int $parentPid, array $parentToChildren): array
    {
        $descendants = [];

        // Get direct children
        $children = $parentToChildren[$parentPid] ?? [];

        foreach ($children as $childPid) {
            $descendants[] = $childPid;

            // Recursively collect grandchildren
            $grandchildren = $this->collectDescendants($childPid, $parentToChildren);
            $descendants = array_merge($descendants, $grandchildren);
        }

        return $descendants;
    }
}
