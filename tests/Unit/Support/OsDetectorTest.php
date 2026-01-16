<?php

use Cbox\SystemMetrics\Support\OsDetector;

describe('OsDetector', function () {
    it('can detect OS family', function () {
        $family = OsDetector::getFamily();

        expect($family)->toBeString();
        expect($family)->toBeIn(['Darwin', 'Linux', 'Windows', 'BSD', 'Solaris', 'Unknown']);
    });

    it('can detect if running on Linux', function () {
        $isLinux = OsDetector::isLinux();

        expect($isLinux)->toBeBool();

        if (PHP_OS_FAMILY === 'Linux') {
            expect($isLinux)->toBeTrue();
        }
    });

    it('can detect if running on macOS', function () {
        $isMacOs = OsDetector::isMacOs();

        expect($isMacOs)->toBeBool();

        if (PHP_OS_FAMILY === 'Darwin') {
            expect($isMacOs)->toBeTrue();
        }
    });

    it('can detect if running on Windows', function () {
        $isWindows = OsDetector::isWindows();

        expect($isWindows)->toBeBool();

        if (PHP_OS_FAMILY === 'Windows') {
            expect($isWindows)->toBeTrue();
        }
    });

    it('returns consistent OS family', function () {
        $family1 = OsDetector::getFamily();
        $family2 = OsDetector::getFamily();

        expect($family1)->toBe($family2);
    });

    it('has mutually exclusive OS checks', function () {
        $isLinux = OsDetector::isLinux();
        $isMacOs = OsDetector::isMacOs();
        $isWindows = OsDetector::isWindows();

        // At most one should be true
        $trueCount = (int) $isLinux + (int) $isMacOs + (int) $isWindows;
        expect($trueCount)->toBeLessThanOrEqual(1);
    });

    it('correctly identifies current OS', function () {
        $phpOsFamily = PHP_OS_FAMILY;

        if ($phpOsFamily === 'Linux') {
            expect(OsDetector::isLinux())->toBeTrue();
            expect(OsDetector::isMacOs())->toBeFalse();
            expect(OsDetector::isWindows())->toBeFalse();
        } elseif ($phpOsFamily === 'Darwin') {
            expect(OsDetector::isLinux())->toBeFalse();
            expect(OsDetector::isMacOs())->toBeTrue();
            expect(OsDetector::isWindows())->toBeFalse();
        } elseif ($phpOsFamily === 'Windows') {
            expect(OsDetector::isLinux())->toBeFalse();
            expect(OsDetector::isMacOs())->toBeFalse();
            expect(OsDetector::isWindows())->toBeTrue();
        }
    });

    it('maps PHP_OS_FAMILY correctly', function () {
        $family = OsDetector::getFamily();
        $phpOsFamily = PHP_OS_FAMILY;

        if (in_array($phpOsFamily, ['Linux', 'Darwin', 'Windows', 'BSD', 'Solaris'])) {
            expect($family)->toBe($phpOsFamily);
        } else {
            expect($family)->toBe('Unknown');
        }
    });
});
