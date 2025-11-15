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
        $result = $reader->read('/non/existent/file.txt');

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
});
