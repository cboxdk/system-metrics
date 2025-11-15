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
        $result = $runner->execute('echo "line1"; echo "line2"');

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
        $result = $runner->execute('echo "Special: $PATH"');

        expect($result->isSuccess())->toBeTrue();
        // Output might vary, just check it executed
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

    it('can execute commands with pipes', function () {
        $runner = new ProcessRunner;
        $result = $runner->execute('echo "test" | cat');

        expect($result->isSuccess())->toBeTrue();
        expect(trim($result->getValue()))->toBe('test');
    });

    it('handles commands with numeric output', function () {
        $runner = new ProcessRunner;
        $result = $runner->execute('echo "12345"');

        expect($result->isSuccess())->toBeTrue();
        expect(trim($result->getValue()))->toBe('12345');
    });
});
