<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Process;

use Cbox\SystemMetrics\Contracts\ProcessMetricsSource;
use Cbox\SystemMetrics\Contracts\ProcessRunnerInterface;
use Cbox\SystemMetrics\DTO\Metrics\Process\ProcessGroupSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Support\Parser\MacOsPsParser;
use Cbox\SystemMetrics\Support\ProcessRunner;
use DateTimeImmutable;

/**
 * Reads process metrics from macOS using ps command.
 */
final class MacOsPsProcessMetricsSource implements ProcessMetricsSource
{
    public function __construct(
        private readonly ProcessRunnerInterface $processRunner = new ProcessRunner,
        private readonly ?MacOsPsParser $parser = null,
    ) {}

    private function getParser(): MacOsPsParser
    {
        return $this->parser ?? new MacOsPsParser($this->processRunner);
    }

    public function read(int $pid): Result
    {
        // Use ps to get process metrics
        $result = $this->processRunner->execute("ps -p {$pid} -o pid,ppid,rss,vsz,time");

        if ($result->isFailure()) {
            $error = $result->getError();
            assert($error !== null);

            /** @var Result<\Cbox\SystemMetrics\DTO\Metrics\Process\ProcessSnapshot> */
            return Result::failure($error);
        }

        return $this->getParser()->parse($result->getValue(), $pid);
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
     * Find all child PIDs recursively using pgrep.
     *
     * @return int[]
     */
    private function findChildPids(int $parentPid): array
    {
        $children = [];

        // Use pgrep to find direct children
        $result = $this->processRunner->execute("pgrep -P {$parentPid}");

        if ($result->isFailure()) {
            return [];  // No children or command failed
        }

        $output = trim($result->getValue());
        if ($output === '') {
            return [];  // No children
        }

        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $pid = (int) trim($line);
            if ($pid > 0) {
                $children[] = $pid;

                // Recursively find grandchildren
                $grandchildren = $this->findChildPids($pid);
                $children = array_merge($children, $grandchildren);
            }
        }

        return $children;
    }
}
