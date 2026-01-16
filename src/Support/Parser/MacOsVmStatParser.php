<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Support\Parser;

use Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\ParseException;

/**
 * Parses macOS vm_stat output for memory metrics.
 */
final class MacOsVmStatParser
{
    /**
     * Parse vm_stat output and sysctl hw.memsize into MemorySnapshot.
     *
     * @return Result<MemorySnapshot>
     */
    public function parse(string $vmStatOutput, string $hwMemsize, int $pageSize): Result
    {
        $values = $this->extractValues($vmStatOutput);

        $free = $values['Pages free'] ?? null;
        $active = $values['Pages active'] ?? null;
        $inactive = $values['Pages inactive'] ?? null;
        $speculative = $values['Pages speculative'] ?? 0;
        $wired = $values['Pages wired down'] ?? 0;
        $purgeable = $values['Pages purgeable'] ?? 0;
        $fileBackedPages = $values['File-backed pages'] ?? 0;
        $compressed = $values['Pages occupied by compressor'] ?? 0;
        $swapIns = $values['Swapins'] ?? 0;
        $swapOuts = $values['Swapouts'] ?? 0;

        if ($free === null || $active === null) {
            /** @var Result<MemorySnapshot> */
            return Result::failure(
                ParseException::forCommand('vm_stat', 'Missing required fields')
            );
        }

        $totalBytes = (int) $hwMemsize;
        $freeBytes = $free * $pageSize;
        $activeBytes = $active * $pageSize;
        $inactiveBytes = ($inactive ?? 0) * $pageSize;
        $speculativeBytes = $speculative * $pageSize;
        $wiredBytes = $wired * $pageSize;
        $compressedBytes = $compressed * $pageSize;
        $cachedBytes = $fileBackedPages * $pageSize;

        // macOS doesn't have the same concept as Linux "available" memory
        // Approximate as free + inactive + speculative
        $availableBytes = $freeBytes + $inactiveBytes + $speculativeBytes;

        // Used is active + wired + compressed
        $usedBytes = $activeBytes + $wiredBytes + $compressedBytes;

        // macOS swap is dynamic; approximate based on swapins/swapouts
        $swapUsedBytes = max(0, $swapOuts - $swapIns) * $pageSize;

        return Result::success(new MemorySnapshot(
            totalBytes: $totalBytes,
            freeBytes: $freeBytes,
            availableBytes: (int) $availableBytes,
            usedBytes: (int) $usedBytes,
            buffersBytes: 0, // Not applicable on macOS
            cachedBytes: (int) $cachedBytes,
            swapTotalBytes: 0, // Dynamic on macOS, not easily determined
            swapFreeBytes: 0,
            swapUsedBytes: (int) $swapUsedBytes,
        ));
    }

    /**
     * Extract key-value pairs from vm_stat output.
     *
     * @return array<string, int>
     */
    private function extractValues(string $content): array
    {
        $values = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            // Format: "Pages free:                               123456."
            if (preg_match('/^([^:]+):\s+(\d+)\.?/', $line, $matches)) {
                $key = trim($matches[1]);
                $value = (int) $matches[2];
                $values[$key] = $value;
            }
        }

        return $values;
    }
}
