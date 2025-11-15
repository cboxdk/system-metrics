<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Sources\Memory;

use PHPeek\SystemMetrics\Contracts\MemoryMetricsSource;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Support\Parser\MacOsVmStatParser;
use PHPeek\SystemMetrics\Support\ProcessRunner;

/**
 * Reads memory metrics from macOS vm_stat and sysctl.
 */
final class MacOsVmStatMemoryMetricsSource implements MemoryMetricsSource
{
    public function __construct(
        private readonly ProcessRunner $processRunner = new ProcessRunner,
        private readonly MacOsVmStatParser $parser = new MacOsVmStatParser,
    ) {}

    public function read(): Result
    {
        // Get vm_stat output
        $vmStatResult = $this->processRunner->execute('vm_stat');
        if ($vmStatResult->isFailure()) {
            $error = $vmStatResult->getError();
            assert($error !== null);

            return Result::failure($error);
        }

        // Get total physical memory
        $hwMemsizeResult = $this->processRunner->execute('sysctl -n hw.memsize');
        if ($hwMemsizeResult->isFailure()) {
            $error = $hwMemsizeResult->getError();
            assert($error !== null);

            return Result::failure($error);
        }

        // Get page size
        $pageSizeResult = $this->processRunner->execute('sysctl -n vm.pagesize');
        if ($pageSizeResult->isFailure()) {
            $error = $pageSizeResult->getError();
            assert($error !== null);

            return Result::failure($error);
        }

        $pageSize = (int) trim($pageSizeResult->getValue());
        if ($pageSize === 0) {
            $pageSize = 4096; // Default fallback
        }

        return $this->parser->parse(
            vmStatOutput: $vmStatResult->getValue(),
            hwMemsize: trim($hwMemsizeResult->getValue()),
            pageSize: $pageSize
        );
    }
}
