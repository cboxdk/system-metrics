<?php

declare(strict_types=1);

namespace Cbox\SystemMetrics\Support;

use Cbox\SystemMetrics\Contracts\ProcessRunnerInterface;
use Cbox\SystemMetrics\DTO\Result;
use Cbox\SystemMetrics\Exceptions\InsufficientPermissionsException;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Executes system commands with proper error handling.
 *
 * Security Model:
 * - All commands are hardcoded in source code (no user input)
 * - Command validation ensures only whitelisted commands can execute
 * - Uses escapeshellcmd() to prevent command injection
 * - Read-only operations only (no write/modify commands)
 *
 * Path Resolution:
 * - Commands are resolved to absolute paths to ensure they work
 *   in restricted environments (e.g. PHP-FPM with limited PATH)
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
        'lsof',    // File descriptor counting
        'nproc',   // CPU core count (Linux)
        'getconf', // System configuration (page size, etc.)

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
     * Absolute paths for system commands by platform.
     *
     * PHP-FPM processes often have a restricted PATH that does not include
     * /usr/sbin or other directories where system commands live. Using
     * absolute paths ensures commands work regardless of the PATH.
     *
     * @var array<string, array<string, string>>
     */
    private const COMMAND_PATHS = [
        'Darwin' => [
            'sysctl' => '/usr/sbin/sysctl',
            'vm_stat' => '/usr/bin/vm_stat',
            'sw_vers' => '/usr/bin/sw_vers',
            'df' => '/bin/df',
            'iostat' => '/usr/sbin/iostat',
            'netstat' => '/usr/sbin/netstat',
            'ps' => '/bin/ps',
            'pgrep' => '/usr/bin/pgrep',
            'top' => '/usr/bin/top',
            'lsof' => '/usr/sbin/lsof',
            'getconf' => '/usr/bin/getconf',
            'cat' => '/bin/cat',
            'which' => '/usr/bin/which',
        ],
        'Linux' => [
            'sysctl' => '/usr/sbin/sysctl',
            'df' => '/bin/df',
            'netstat' => '/usr/bin/netstat',
            'ps' => '/bin/ps',
            'pgrep' => '/usr/bin/pgrep',
            'top' => '/usr/bin/top',
            'lsof' => '/usr/bin/lsof',
            'nproc' => '/usr/bin/nproc',
            'getconf' => '/usr/bin/getconf',
            'cat' => '/bin/cat',
            'which' => '/usr/bin/which',
        ],
    ];

    /**
     * Cache of resolved command paths.
     *
     * @var array<string, string>
     */
    private static array $resolvedPaths = [];

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

        // Resolve command to absolute path for restricted environments
        $resolvedCommand = $this->resolveCommandPath($command);

        // Use escapeshellcmd to prevent command injection
        // This is defense-in-depth since all commands are hardcoded
        $safeCommand = escapeshellcmd($resolvedCommand);

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
     * Cache of command availability checks.
     *
     * @var array<string, bool>
     */
    private static array $commandAvailabilityCache = [];

    /**
     * Check if a command is available on the system.
     */
    public function commandExists(string $command): bool
    {
        if (isset(self::$commandAvailabilityCache[$command])) {
            return self::$commandAvailabilityCache[$command];
        }

        $which = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        // Resolve which/where to absolute path for restricted PATH environments
        $resolvedWhich = $this->resolveCommandPath($which);
        $output = [];
        $resultCode = 0;

        $safeWhichCommand = escapeshellcmd("{$resolvedWhich} {$command}");

        @exec("{$safeWhichCommand} 2>&1", $output, $resultCode);

        return self::$commandAvailabilityCache[$command] = ($resultCode === 0);
    }

    /**
     * Resolve a command to its absolute path.
     *
     * In PHP-FPM environments (e.g. Laravel Herd, Nginx), the PATH is often
     * restricted and doesn't include /usr/sbin where commands like sysctl live.
     * This method resolves commands to absolute paths using a known mapping.
     */
    private function resolveCommandPath(string $command): string
    {
        // Extract the binary name (first word of the command)
        $parts = explode(' ', $command, 2);
        $binary = $parts[0];
        $arguments = $parts[1] ?? '';

        // Check cache first
        if (isset(self::$resolvedPaths[$binary])) {
            $resolved = self::$resolvedPaths[$binary];

            return $arguments !== '' ? "{$resolved} {$arguments}" : $resolved;
        }

        // If already an absolute path, use as-is
        if (str_starts_with($binary, '/')) {
            self::$resolvedPaths[$binary] = $binary;

            return $command;
        }

        // Look up known absolute path for current platform
        $platform = PHP_OS_FAMILY;
        $knownPaths = self::COMMAND_PATHS[$platform] ?? [];

        if (isset($knownPaths[$binary]) && file_exists($knownPaths[$binary])) {
            self::$resolvedPaths[$binary] = $knownPaths[$binary];
            $resolved = $knownPaths[$binary];

            return $arguments !== '' ? "{$resolved} {$arguments}" : $resolved;
        }

        // Fall back to unresolved command (rely on PATH)
        self::$resolvedPaths[$binary] = $binary;

        return $command;
    }

    /**
     * Check if a command is whitelisted for execution.
     *
     * Commands must start with one of the allowed command prefixes
     * to prevent arbitrary command execution. Validation happens
     * BEFORE path resolution to check against the original command.
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

    /**
     * Reset the resolved path cache (for testing).
     */
    public static function resetPathCache(): void
    {
        self::$resolvedPaths = [];
    }
}
