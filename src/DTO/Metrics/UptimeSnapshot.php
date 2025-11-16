<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO\Metrics;

use DateTimeImmutable;

/**
 * System uptime snapshot.
 */
final readonly class UptimeSnapshot
{
    public function __construct(
        public int $totalSeconds,
        public DateTimeImmutable $bootTime,
        public DateTimeImmutable $timestamp,
    ) {}

    /**
     * Get complete days of uptime.
     */
    public function days(): int
    {
        return (int) floor($this->totalSeconds / 86400);
    }

    /**
     * Get remaining hours after full days.
     */
    public function hours(): int
    {
        return (int) floor(($this->totalSeconds % 86400) / 3600);
    }

    /**
     * Get remaining minutes after full hours.
     */
    public function minutes(): int
    {
        return (int) floor(($this->totalSeconds % 3600) / 60);
    }

    /**
     * Get total uptime in hours (decimal).
     */
    public function totalHours(): float
    {
        return $this->totalSeconds / 3600;
    }

    /**
     * Get total uptime in minutes (decimal).
     */
    public function totalMinutes(): float
    {
        return $this->totalSeconds / 60;
    }

    /**
     * Get human-readable uptime string.
     */
    public function humanReadable(): string
    {
        $days = $this->days();
        $hours = $this->hours();
        $minutes = $this->minutes();

        $parts = [];

        if ($days > 0) {
            $parts[] = $days.' '.($days === 1 ? 'day' : 'days');
        }

        if ($hours > 0) {
            $parts[] = $hours.' '.($hours === 1 ? 'hour' : 'hours');
        }

        if ($minutes > 0 || empty($parts)) {
            $parts[] = $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
        }

        return implode(', ', $parts);
    }
}
