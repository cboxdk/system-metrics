<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Support\Parser;

use Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\ParseException;

/**
 * Parses Linux /proc/meminfo file for memory metrics.
 */
final class LinuxMeminfoParser
{
    /**
     * Parse /proc/meminfo content into MemorySnapshot.
     *
     * @return Result<MemorySnapshot>
     */
    public function parse(string $content): Result
    {
        $values = $this->extractValues($content);

        $total = $values['MemTotal'] ?? null;
        $free = $values['MemFree'] ?? null;
        $available = $values['MemAvailable'] ?? $free; // Fallback to free if available not present
        $buffers = $values['Buffers'] ?? 0;
        $cached = $values['Cached'] ?? 0;
        $swapTotal = $values['SwapTotal'] ?? 0;
        $swapFree = $values['SwapFree'] ?? 0;

        if ($total === null || $free === null) {
            /** @var Result<MemorySnapshot> */
            return Result::failure(
                ParseException::forFile('/proc/meminfo', 'Missing required fields')
            );
        }

        // Convert from KB to bytes
        $total *= 1024;
        $free *= 1024;
        $available *= 1024;
        $buffers *= 1024;
        $cached *= 1024;
        $swapTotal *= 1024;
        $swapFree *= 1024;

        // Use MemAvailable for more accurate used memory calculation
        // MemAvailable is a kernel-provided estimate of memory available for starting new applications
        $used = $total - $available;
        $swapUsed = $swapTotal - $swapFree;

        return Result::success(new MemorySnapshot(
            totalBytes: $total,
            freeBytes: $free,
            availableBytes: $available,
            usedBytes: max(0, $used),
            buffersBytes: $buffers,
            cachedBytes: $cached,
            swapTotalBytes: $swapTotal,
            swapFreeBytes: $swapFree,
            swapUsedBytes: max(0, $swapUsed),
        ));
    }

    /**
     * Extract key-value pairs from /proc/meminfo content.
     *
     * @return array<string, int>
     */
    private function extractValues(string $content): array
    {
        $values = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            // Format: "MemTotal:       16384000 kB"
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $matches)) {
                $key = $matches[1];
                $value = (int) $matches[2];
                $values[$key] = $value;
            }
        }

        return $values;
    }
}
