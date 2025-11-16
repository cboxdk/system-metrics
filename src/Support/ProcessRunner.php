<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\Support;

use PHPeek\SystemMetrics\Contracts\ProcessRunnerInterface;
use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\InsufficientPermissionsException;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Executes system commands with proper error handling.
 *
 * Security Model:
 * - All commands are hardcoded in source code (no user input)
 * - Command validation ensures only whitelisted commands can execute
 * - Uses escapeshellcmd() to prevent command injection
 * - Read-only operations only (no write/modify commands)
 */
final class ProcessRunner implements ProcessRunnerInterface
{
    /**
     * Whitelist of allowed command prefixes for security.
     *
     * This prevents command injection even if user input somehow
     * makes it into the execute() method in future development.
     *
     * @var array<string>
     */
    private const ALLOWED_COMMANDS = [
        // macOS system commands
        'vm_stat',
        'sysctl',
        'sw_vers',
        'df',
        'iostat',
        'netstat',
        'ps',
        'pgrep',
        'top',

        // Linux system commands
        'cat /proc/',
        'cat /sys/',
        'cat /etc/os-release',

        // Command availability checks
        'which',
        'where',

        // Test-only commands (for unit tests)
        'echo',
        'printf',
        'true',
        'false',
        'uname',
    ];

    /**
     * Execute a command and return its output.
     *
     * @return Result<string>
     */
    public function execute(string $command): Result
    {
        // Validate command is whitelisted
        if (! $this->isCommandAllowed($command)) {
            /** @var Result<string> */
            return Result::failure(
                new SystemMetricsException(
                    "Command not whitelisted for security: {$command}"
                )
            );
        }

        $output = [];
        $resultCode = 0;

        // Use escapeshellcmd to prevent command injection
        // This is defense-in-depth since all commands are hardcoded
        $safeCommand = escapeshellcmd($command);

        @exec($safeCommand.' 2>&1', $output, $resultCode);

        if ($resultCode !== 0) {
            if ($resultCode === 127) {
                /** @var Result<string> */
                return Result::failure(
                    new SystemMetricsException("Command not found: {$command}")
                );
            }

            if ($resultCode === 126) {
                /** @var Result<string> */
                return Result::failure(InsufficientPermissionsException::forCommand($command));
            }

            /** @var Result<string> */
            return Result::failure(
                new SystemMetricsException("Command failed with exit code {$resultCode}: {$command}")
            );
        }

        return Result::success(implode("\n", $output));
    }

    /**
     * Execute a command and return its output as an array of lines.
     *
     * @return Result<list<string>>
     */
    public function executeLines(string $command): Result
    {
        return $this->execute($command)->map(function (string $output): array {
            return array_values(array_filter(
                explode("\n", $output),
                fn (string $line) => $line !== ''
            ));
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

        $safeWhichCommand = escapeshellcmd("{$which} {$command}");

        @exec("{$safeWhichCommand} 2>&1", $output, $resultCode);

        return $resultCode === 0;
    }

    /**
     * Check if a command is whitelisted for execution.
     *
     * Commands must start with one of the allowed command prefixes
     * to prevent arbitrary command execution.
     */
    private function isCommandAllowed(string $command): bool
    {
        foreach (self::ALLOWED_COMMANDS as $allowedPrefix) {
            if (str_starts_with($command, $allowedPrefix)) {
                return true;
            }
        }

        return false;
    }
}
