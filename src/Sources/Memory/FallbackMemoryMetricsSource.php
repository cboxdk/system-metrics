<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Memory;

use Cbox\SystemMetrics\Contracts\MemoryMetricsSource;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Fallback memory metrics source that tries multiple sources in order.
 *
 * This source implements a fallback strategy where it tries each source
 * in priority order, returning the first successful result.
 */
final class FallbackMemoryMetricsSource implements MemoryMetricsSource
{
    /**
     * @param  MemoryMetricsSource[]  $sources  Sources to try in priority order
     */
    public function __construct(
        private readonly array $sources,
    ) {}

    public function read(): Result
    {
        $errors = [];

        foreach ($this->sources as $index => $source) {
            $result = $source->read();

            if ($result->isSuccess()) {
                return $result;
            }

            $error = $result->getError();
            assert($error !== null);
            $errors[] = sprintf(
                'Source %d (%s): %s',
                $index,
                $source::class,
                $error->getMessage()
            );
        }

        /** @var Result<\Cbox\SystemMetrics\DTO\Metrics\Memory\MemorySnapshot> */
        return Result::failure(
            new SystemMetricsException(
                'All memory metrics sources failed: '.implode('; ', $errors)
            )
        );
    }
}
