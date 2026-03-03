<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\LoadAverage;

use Cbox\SystemMetrics\Contracts\LoadAverageSource;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Tries multiple load average sources in priority order.
 *
 * This source attempts each provided source sequentially until one succeeds.
 * Useful for graceful degradation when preferred APIs are unavailable
 * (e.g. FFI disabled in PHP-FPM environments).
 */
final class FallbackLoadAverageSource implements LoadAverageSource
{
    /**
     * @param  LoadAverageSource[]  $sources  Sources to try in priority order
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

        /** @var Result<\Cbox\SystemMetrics\DTO\Metrics\LoadAverageSnapshot> */
        return Result::failure(
            new SystemMetricsException(
                'All load average sources failed: '.implode('; ', $errors)
            )
        );
    }
}
