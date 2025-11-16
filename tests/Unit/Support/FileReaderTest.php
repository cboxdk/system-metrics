<?php

use PHPeek\SystemMetrics\Exceptions\FileNotFoundException;
use PHPeek\SystemMetrics\Support\FileReader;

describe('FileReader', function () {
    it('can read an existing file', function () {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test content');

        try {
            $reader = new FileReader;
            $result = $reader->read($tempFile);

            expect($result->isSuccess())->toBeTrue();
            expect($result->getValue())->toBe('test content');
        } finally {
            unlink($tempFile);
        }
    });

    it('returns failure for non-existent file', function () {
        $reader = new FileReader;
        // Use a whitelisted but non-existent path
        $result = $reader->read('/proc/nonexistentfile12345');

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(FileNotFoundException::class);
    });

    it('handles empty files', function () {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, '');

        try {
            $reader = new FileReader;
            $result = $reader->read($tempFile);

            expect($result->isSuccess())->toBeTrue();
            expect($result->getValue())->toBe('');
        } finally {
            unlink($tempFile);
        }
    });

    it('handles multiline content', function () {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $content = "line 1\nline 2\nline 3";
        file_put_contents($tempFile, $content);

        try {
            $reader = new FileReader;
            $result = $reader->read($tempFile);

            expect($result->isSuccess())->toBeTrue();
            expect($result->getValue())->toBe($content);
        } finally {
            unlink($tempFile);
        }
    });

    it('handles large files', function () {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $content = str_repeat('x', 10000);
        file_put_contents($tempFile, $content);

        try {
            $reader = new FileReader;
            $result = $reader->read($tempFile);

            expect($result->isSuccess())->toBeTrue();
            expect(strlen($result->getValue()))->toBe(10000);
        } finally {
            unlink($tempFile);
        }
    });

    it('handles special characters', function () {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        $content = "Special: æøå ñ € \n\t\r";
        file_put_contents($tempFile, $content);

        try {
            $reader = new FileReader;
            $result = $reader->read($tempFile);

            expect($result->isSuccess())->toBeTrue();
            expect($result->getValue())->toBe($content);
        } finally {
            unlink($tempFile);
        }
    });

    it('handles paths with spaces', function () {
        $tempFile = sys_get_temp_dir().'/test file with spaces.txt';
        file_put_contents($tempFile, 'content');

        try {
            $reader = new FileReader;
            $result = $reader->read($tempFile);

            expect($result->isSuccess())->toBeTrue();
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    });

    it('handles binary files', function () {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        // Create a simple binary content
        file_put_contents($tempFile, "\x00\x01\x02\xFF");

        try {
            $reader = new FileReader;
            $result = $reader->read($tempFile);

            expect($result->isSuccess())->toBeTrue();
            expect(strlen($result->getValue()))->toBe(4);
        } finally {
            unlink($tempFile);
        }
    });

    it('can read file as lines', function () {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, "line 1\nline 2\n\nline 3\n");

        try {
            $reader = new FileReader;
            $result = $reader->readLines($tempFile);

            expect($result->isSuccess())->toBeTrue();
            $lines = $result->getValue();
            expect($lines)->toBeArray();
            // array_filter preserves keys, so we get [0, 1, 3] not [0, 1, 2]
            expect(array_values($lines))->toBe(['line 1', 'line 2', 'line 3']);
        } finally {
            unlink($tempFile);
        }
    });

    it('readLines filters empty lines', function () {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, "line 1\n\n\nline 2\n");

        try {
            $reader = new FileReader;
            $result = $reader->readLines($tempFile);

            expect($result->isSuccess())->toBeTrue();
            $lines = $result->getValue();
            expect($lines)->toHaveCount(2);
        } finally {
            unlink($tempFile);
        }
    });

    it('readLines propagates read failures', function () {
        $reader = new FileReader;
        // Use a whitelisted but non-existent path
        $result = $reader->readLines('/proc/nonexistentfile12345');

        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBeInstanceOf(FileNotFoundException::class);
    });

    it('exists returns true for existing readable file', function () {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'content');

        try {
            $reader = new FileReader;
            expect($reader->exists($tempFile))->toBeTrue();
        } finally {
            unlink($tempFile);
        }
    });

    it('exists returns false for non-existent file', function () {
        $reader = new FileReader;
        // Use a whitelisted but non-existent path
        expect($reader->exists('/proc/nonexistentfile12345'))->toBeFalse();
    });

    it('rejects paths not in whitelist', function () {
        $reader = new FileReader;
        $result = $reader->read('/etc/passwd');

        expect($result->isFailure())->toBeTrue();
        expect($result->getError()->getMessage())->toContain('not whitelisted');
    });

    it('prevents directory traversal attacks', function () {
        $reader = new FileReader;
        $result = $reader->read('/proc/../etc/passwd');

        expect($result->isFailure())->toBeTrue();
        // Should fail because realpath resolves to /etc/passwd which isn't whitelisted
    });
});
