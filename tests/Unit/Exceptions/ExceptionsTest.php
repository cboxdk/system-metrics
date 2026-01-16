<?php

use Cbox\SystemMetrics\Exceptions\FileNotFoundException;
use Cbox\SystemMetrics\Exceptions\InsufficientPermissionsException;
use Cbox\SystemMetrics\Exceptions\ParseException;
use Cbox\SystemMetrics\Exceptions\SystemMetricsException;
use Cbox\SystemMetrics\Exceptions\UnsupportedOperatingSystemException;

describe('SystemMetricsException', function () {
    it('can be thrown with custom message', function () {
        $exception = new SystemMetricsException('Test error');

        expect($exception)->toBeInstanceOf(SystemMetricsException::class);
        expect($exception->getMessage())->toBe('Test error');
    });
});

describe('FileNotFoundException', function () {
    it('creates exception for missing file', function () {
        $exception = FileNotFoundException::forPath('/path/to/missing/file');

        expect($exception)->toBeInstanceOf(FileNotFoundException::class);
        expect($exception->getMessage())->toContain('/path/to/missing/file');
        expect($exception->getMessage())->toContain('File not found');
    });

    it('is a SystemMetricsException', function () {
        $exception = FileNotFoundException::forPath('/test');

        expect($exception)->toBeInstanceOf(SystemMetricsException::class);
    });
});

describe('InsufficientPermissionsException', function () {
    it('creates exception for file permission error', function () {
        $exception = InsufficientPermissionsException::forFile('/etc/shadow');

        expect($exception)->toBeInstanceOf(InsufficientPermissionsException::class);
        expect($exception->getMessage())->toContain('/etc/shadow');
        expect($exception->getMessage())->toContain('Insufficient permissions to read');
    });

    it('creates exception for command permission error', function () {
        $exception = InsufficientPermissionsException::forCommand('sudo systemctl');

        expect($exception)->toBeInstanceOf(InsufficientPermissionsException::class);
        expect($exception->getMessage())->toContain('sudo systemctl');
        expect($exception->getMessage())->toContain('Insufficient permissions to execute');
    });

    it('is a SystemMetricsException', function () {
        $exception = InsufficientPermissionsException::forFile('/test');

        expect($exception)->toBeInstanceOf(SystemMetricsException::class);
    });
});

describe('UnsupportedOperatingSystemException', function () {
    it('creates exception for unsupported OS', function () {
        $exception = UnsupportedOperatingSystemException::forOs('FreeBSD');

        expect($exception)->toBeInstanceOf(UnsupportedOperatingSystemException::class);
        expect($exception->getMessage())->toContain('FreeBSD');
        expect($exception->getMessage())->toContain('Unsupported operating system');
    });

    it('is a SystemMetricsException', function () {
        $exception = UnsupportedOperatingSystemException::forOs('Unknown');

        expect($exception)->toBeInstanceOf(SystemMetricsException::class);
    });
});

describe('ParseException', function () {
    it('creates exception for file parse error', function () {
        $exception = ParseException::forFile('/proc/stat', 'Invalid format');

        expect($exception)->toBeInstanceOf(ParseException::class);
        expect($exception->getMessage())->toContain('/proc/stat');
        expect($exception->getMessage())->toContain('Invalid format');
        expect($exception->getMessage())->toContain('Failed to parse file');
    });

    it('creates exception for command parse error', function () {
        $exception = ParseException::forCommand('vm_stat', 'Unexpected output');

        expect($exception)->toBeInstanceOf(ParseException::class);
        expect($exception->getMessage())->toContain('vm_stat');
        expect($exception->getMessage())->toContain('Unexpected output');
        expect($exception->getMessage())->toContain('Failed to parse output from command');
    });

    it('creates exception without reason', function () {
        $exception = ParseException::forFile('/proc/meminfo');

        expect($exception)->toBeInstanceOf(ParseException::class);
        expect($exception->getMessage())->toContain('/proc/meminfo');
    });

    it('is a SystemMetricsException', function () {
        $exception = ParseException::forFile('/test');

        expect($exception)->toBeInstanceOf(SystemMetricsException::class);
    });
});
