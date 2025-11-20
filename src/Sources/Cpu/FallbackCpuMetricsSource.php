<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Cpu;

use PHPeek\SystemMetrics\Contracts\CpuMetricsSource;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Tries multiple CPU metrics sources in priority order.
 *
 * This source attempts each provided source sequentially until one succeeds.
 * Useful for graceful degradation when preferred APIs are unavailable.
 */
final class FallbackCpuMetricsSource implements CpuMetricsSource
{
    /**
     * @param  array<CpuMetricsSource>  $sources  Sources to try in order
     */
    public function __construct(
        private readonly array $sources
    ) {}

    public function read(): Result
    {
        $errors = [];

        foreach ($this->sources as $index => $source) {
            $result = $source->read();

            if ($result->isSuccess()) {
                return $result;
            }

            // Collect error for debugging
            $error = $result->getError();
            assert($error !== null);
            $errors[] = sprintf(
                'Source %d (%s): %s',
                $index,
                $source::class,
                $error->getMessage()
            );
        }

        // All sources failed
        /** @var Result<\PHPeek\SystemMetrics\DTO\Metrics\Cpu\CpuSnapshot> */
        return Result::failure(
            new SystemMetricsException(
                'All CPU metrics sources failed: '.implode('; ', $errors)
            )
        );
    }
}
