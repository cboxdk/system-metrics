<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Exceptions;

/**
 * Thrown when unable to parse system output or file content.
 */
final class ParseException extends SystemMetricsException
{
    public static function forFile(string $path, string $reason = ''): self
    {
        $message = "Failed to parse file: {$path}";
        if ($reason !== '') {
            $message .= " ({$reason})";
        }

        return new self($message);
    }

    public static function forCommand(string $command, string $reason = ''): self
    {
        $message = "Failed to parse output from command: {$command}";
        if ($reason !== '') {
            $message .= " ({$reason})";
        }

        return new self($message);
    }
}
