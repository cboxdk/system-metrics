<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Contracts;

use Cbox\SystemMetrics\DTO\Result;

/**
 * Interface for reading file contents.
 */
interface FileReaderInterface
{
    /**
     * Read the contents of a file.
     *
     * @return Result<string>
     */
    public function read(string $path): Result;

    /**
     * Check if a file exists and is readable.
     */
    public function exists(string $path): bool;
}
