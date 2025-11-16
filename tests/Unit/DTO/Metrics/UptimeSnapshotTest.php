<?php

use PHPeek\SystemMetrics\DTO\Metrics\UptimeSnapshot;

describe('UptimeSnapshot', function () {
    it('can be instantiated with all values', function () {
        $bootTime = new DateTimeImmutable('2025-11-07 14:52:42');
        $timestamp = new DateTimeImmutable('2025-11-16 09:40:30');
        $totalSeconds = $timestamp->getTimestamp() - $bootTime->getTimestamp();

        $uptime = new UptimeSnapshot(
            totalSeconds: $totalSeconds,
            bootTime: $bootTime,
            timestamp: $timestamp,
        );

        expect($uptime->totalSeconds)->toBe($totalSeconds);
        expect($uptime->bootTime)->toEqual($bootTime);
        expect($uptime->timestamp)->toEqual($timestamp);
    });

    it('calculates days correctly', function () {
        $uptime = new UptimeSnapshot(
            totalSeconds: 7 * 86400 + 3 * 3600 + 45 * 60, // 7 days, 3 hours, 45 minutes
            bootTime: new DateTimeImmutable,
            timestamp: new DateTimeImmutable,
        );

        expect($uptime->days())->toBe(7);
    });

    it('calculates remaining hours correctly', function () {
        $uptime = new UptimeSnapshot(
            totalSeconds: 7 * 86400 + 3 * 3600 + 45 * 60, // 7 days, 3 hours, 45 minutes
            bootTime: new DateTimeImmutable,
            timestamp: new DateTimeImmutable,
        );

        expect($uptime->hours())->toBe(3);
    });

    it('calculates remaining minutes correctly', function () {
        $uptime = new UptimeSnapshot(
            totalSeconds: 7 * 86400 + 3 * 3600 + 45 * 60, // 7 days, 3 hours, 45 minutes
            bootTime: new DateTimeImmutable,
            timestamp: new DateTimeImmutable,
        );

        expect($uptime->minutes())->toBe(45);
    });

    it('calculates total hours correctly', function () {
        $uptime = new UptimeSnapshot(
            totalSeconds: 24 * 3600, // 24 hours
            bootTime: new DateTimeImmutable,
            timestamp: new DateTimeImmutable,
        );

        expect($uptime->totalHours())->toBe(24.0);
    });

    it('calculates total minutes correctly', function () {
        $uptime = new UptimeSnapshot(
            totalSeconds: 3600, // 1 hour
            bootTime: new DateTimeImmutable,
            timestamp: new DateTimeImmutable,
        );

        expect($uptime->totalMinutes())->toBe(60.0);
    });

    it('formats human readable string with days, hours, and minutes', function () {
        $uptime = new UptimeSnapshot(
            totalSeconds: 5 * 86400 + 3 * 3600 + 42 * 60,
            bootTime: new DateTimeImmutable,
            timestamp: new DateTimeImmutable,
        );

        expect($uptime->humanReadable())->toBe('5 days, 3 hours, 42 minutes');
    });

    it('formats human readable string without days', function () {
        $uptime = new UptimeSnapshot(
            totalSeconds: 3 * 3600 + 42 * 60,
            bootTime: new DateTimeImmutable,
            timestamp: new DateTimeImmutable,
        );

        expect($uptime->humanReadable())->toBe('3 hours, 42 minutes');
    });

    it('formats human readable string without hours', function () {
        $uptime = new UptimeSnapshot(
            totalSeconds: 42 * 60,
            bootTime: new DateTimeImmutable,
            timestamp: new DateTimeImmutable,
        );

        expect($uptime->humanReadable())->toBe('42 minutes');
    });

    it('uses singular forms for single units', function () {
        $uptime = new UptimeSnapshot(
            totalSeconds: 1 * 86400 + 1 * 3600 + 1 * 60,
            bootTime: new DateTimeImmutable,
            timestamp: new DateTimeImmutable,
        );

        expect($uptime->humanReadable())->toBe('1 day, 1 hour, 1 minute');
    });

    it('shows zero minutes when uptime is zero', function () {
        $uptime = new UptimeSnapshot(
            totalSeconds: 0,
            bootTime: new DateTimeImmutable,
            timestamp: new DateTimeImmutable,
        );

        expect($uptime->humanReadable())->toBe('0 minutes');
    });

    it('handles very long uptimes correctly', function () {
        $uptime = new UptimeSnapshot(
            totalSeconds: 365 * 86400, // 1 year
            bootTime: new DateTimeImmutable,
            timestamp: new DateTimeImmutable,
        );

        expect($uptime->days())->toBe(365);
        expect($uptime->totalHours())->toBe(8760.0);
    });

    it('is immutable', function () {
        $uptime = new UptimeSnapshot(
            totalSeconds: 1000,
            bootTime: new DateTimeImmutable,
            timestamp: new DateTimeImmutable,
        );

        $reflection = new ReflectionClass($uptime);
        expect($reflection->isReadOnly())->toBeTrue();
    });
});
