<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Support\Parser;

use Cbox\SystemMetrics\Contracts\ProcessRunnerInterface;
use Cbox\SystemMetrics\DTO\Metrics\Cpu\CpuTimes;
use Cbox\SystemMetrics\DTO\Metrics\Process\ProcessResourceUsage;
use Cbox\SystemMetrics\DTO\Metrics\Process\ProcessSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\ParseException;
use Cbox\SystemMetrics\Support\ProcessRunner;
use DateTimeImmutable;

/**
 * Parses macOS ps command output for process metrics.
 *
 * Expected format: ps -p {pid} -o pid,ppid,rss,vsz,time
 * Output: PID  PPID    RSS      VSZ      TIME
 *         123  1       1234     5678     00:01:23
 */
final class MacOsPsParser
{
    public function __construct(
        private readonly ProcessRunnerInterface $processRunner = new ProcessRunner,
    ) {}

    /**
     * Parse ps command output into ProcessSnapshot.
     *
     * @return Result<ProcessSnapshot>
     */
    public function parse(string $output, int $expectedPid): Result
    {
        $lines = array_filter(
            explode("\n", trim($output)),
            fn (string $line) => $line !== ''
        );

        if (count($lines) < 2) {
            /** @var Result<ProcessSnapshot> */
            return Result::failure(
                ParseException::forCommand('ps', 'Insufficient output lines')
            );
        }

        // Skip header line, parse data line
        $dataLine = trim($lines[1]);
        $fields = preg_split('/\s+/', $dataLine);

        if ($fields === false || count($fields) < 5) {
            /** @var Result<ProcessSnapshot> */
            return Result::failure(
                ParseException::forCommand('ps', 'Invalid format: insufficient fields')
            );
        }

        $pid = (int) $fields[0];
        $ppid = (int) $fields[1];
        $rss = (int) $fields[2]; // In kilobytes
        $vsz = (int) $fields[3]; // In kilobytes
        $timeStr = $fields[4]; // Format: HH:MM:SS or MM:SS.CC

        // Convert RSS and VSZ from kilobytes to bytes
        $rssBytes = $rss * 1024;
        $vszBytes = $vsz * 1024;

        // Parse time string into seconds
        $cpuSeconds = $this->parseTimeString($timeStr);

        // Convert seconds to ticks (USER_HZ = 100)
        $cpuTicks = (int) ($cpuSeconds * 100);

        // ps combines user + system time, so we put it all in user
        $cpuTimes = new CpuTimes(
            user: $cpuTicks,
            nice: 0,
            system: 0,
            idle: 0,
            iowait: 0,
            irq: 0,
            softirq: 0,
            steal: 0
        );

        // Count open file descriptors using lsof
        $openFds = $this->countFileDescriptors($pid);

        $resources = new ProcessResourceUsage(
            cpuTimes: $cpuTimes,
            memoryRssBytes: $rssBytes,
            memoryVmsBytes: $vszBytes,
            threadCount: 1,  // ps doesn't provide thread count easily
            openFileDescriptors: $openFds
        );

        return Result::success(new ProcessSnapshot(
            pid: $pid,
            parentPid: $ppid,
            resources: $resources,
            timestamp: new DateTimeImmutable
        ));
    }

    /**
     * Count open file descriptors for a process.
     *
     * The Linux source counts entries in /proc/{pid}/fd, so this has to
     * mean the same thing or the field is not comparable across platforms.
     *
     * The obvious `lsof -p PID` does NOT mean that. Its default output is
     * every open FILE, which includes the working directory, the
     * executable and each loaded shared library (cwd, txt) and every
     * memory-mapped region. A plain PHP process with 5 descriptors reports
     * 59 lines that way, and the number tracks how many dylibs are loaded
     * rather than how many files are open.
     *
     * `-F f` asks for the field format instead, one `f<number>` line per
     * actual descriptor, which is the thing being counted.
     *
     * @return int Number of open file descriptors, or 0 if unable to determine
     */
    private function countFileDescriptors(int $pid): int
    {
        if ($pid === getmypid()) {
            $own = $this->countOwnFileDescriptors();

            if ($own !== null) {
                return $own;
            }
        }

        // -F f: field output, one `f<fd>` line per descriptor
        // -n: no hostname resolution (faster)
        // -P: no port name resolution (faster)
        $result = $this->processRunner->execute("lsof -p {$pid} -n -P -F f");

        if ($result->isFailure()) {
            return 0;
        }

        $output = $result->getValue();
        if ($output === '') {
            return 0;
        }

        $descriptors = 0;

        foreach (explode("\n", trim($output)) as $line) {
            // Field output also carries p<pid> and other records; only the
            // numeric f-records are descriptors.
            if (preg_match('/^f\d+$/', trim($line)) === 1) {
                $descriptors++;
            }
        }

        return $descriptors;
    }

    /**
     * The calling process's own descriptors, read rather than shelled out.
     *
     * /dev/fd is the current process's descriptor table on macOS — the same
     * answer lsof gives for this pid, for about a five-hundredth of the
     * cost (0.05 ms against 25 ms), which matters because a per-request
     * profiler samples its own process on every request.
     *
     * Null when the directory cannot be read, so the caller falls back.
     */
    private function countOwnFileDescriptors(): ?int
    {
        if (! is_dir('/dev/fd') || ! is_readable('/dev/fd')) {
            return null;
        }

        $entries = @scandir('/dev/fd');

        if ($entries === false) {
            return null;
        }

        return count(array_diff($entries, ['.', '..']));
    }

    /**
     * Parse time string from ps output into seconds.
     *
     * Formats supported:
     * - DD-HH:MM:SS (long-lived processes, e.g., "1-12:34:56")
     * - HH:MM:SS (e.g., "12:34:56")
     * - MM:SS.CC (e.g., "34:56.78")
     */
    private function parseTimeString(string $time): float
    {
        // Remove centiseconds if present (e.g., "00:01:23.45" -> "00:01:23")
        $centiseconds = 0.0;
        if (str_contains($time, '.')) {
            $parts = explode('.', $time);
            $time = $parts[0];
            $centiseconds = isset($parts[1]) ? (float) $parts[1] / 100 : 0.0;
        }

        // Check for DD-HH:MM:SS format (days-hours:minutes:seconds)
        $days = 0;
        if (str_contains($time, '-')) {
            $dayParts = explode('-', $time, 2);
            $days = (int) $dayParts[0];
            $time = $dayParts[1];
        }

        $components = explode(':', $time);
        $count = count($components);

        $seconds = 0.0;

        if ($count === 3) {
            // HH:MM:SS format
            $seconds = ((int) $components[0] * 3600) + ((int) $components[1] * 60) + (int) $components[2];
        } elseif ($count === 2) {
            // MM:SS format
            $seconds = ((int) $components[0] * 60) + (int) $components[1];
        }

        // Add days converted to seconds
        $seconds += $days * 86400;

        return $seconds + $centiseconds;
    }
}
