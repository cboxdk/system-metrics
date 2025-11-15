<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support;

use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\InsufficientPermissionsException;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Executes system commands with proper error handling.
 */
final class ProcessRunner
{
    /**
     * Execute a command and return its output.
     *
     * @return Result<string>
     */
    public function execute(string $command): Result
    {
        $output = [];
        $resultCode = 0;

        @exec($command.' 2>&1', $output, $resultCode);

        if ($resultCode !== 0) {
            if ($resultCode === 127) {
                return Result::failure(
                    new SystemMetricsException("Command not found: {$command}")
                );
            }

            if ($resultCode === 126) {
                return Result::failure(InsufficientPermissionsException::forCommand($command));
            }

            return Result::failure(
                new SystemMetricsException("Command failed with exit code {$resultCode}: {$command}")
            );
        }

        return Result::success(implode("\n", $output));
    }

    /**
     * Execute a command and return its output as an array of lines.
     *
     * @return Result<array<string>>
     */
    public function executeLines(string $command): Result
    {
        return $this->execute($command)->map(function (string $output): array {
            return array_filter(
                explode("\n", $output),
                fn (string $line) => $line !== ''
            );
        });
    }

    /**
     * Check if a command is available on the system.
     */
    public function commandExists(string $command): bool
    {
        $which = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        $output = [];
        $resultCode = 0;

        @exec("{$which} {$command} 2>&1", $output, $resultCode);

        return $resultCode === 0;
    }
}
