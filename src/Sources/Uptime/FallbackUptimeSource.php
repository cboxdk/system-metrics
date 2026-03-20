<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Sources\Uptime;

use Cbox\SystemMetrics\Contracts\UptimeSource;
use Cbox\SystemMetrics\DTO\Metrics\UptimeSnapshot;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Tries multiple uptime sources in priority order.
 *
 * This source attempts each provided source sequentially until one succeeds.
 * Useful for graceful degradation when preferred APIs are unavailable
 * (e.g. FFI disabled in PHP-FPM environments).
 */
final class FallbackUptimeSource implements UptimeSource
{
    /**
     * @param  UptimeSource[]  $sources  Sources to try in priority order
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

        /** @var Result<UptimeSnapshot> */
        return Result::failure(
            new SystemMetricsException(
                'All uptime sources failed: '.implode('; ', $errors)
            )
        );
    }
}
