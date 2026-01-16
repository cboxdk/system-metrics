<?php

declare(strict_types=1);

use Cbox\SystemMetrics\Exceptions\ParseException;
use Cbox\SystemMetrics\Support\Parser\LinuxProcPidStatParser;
use Cbox\SystemMetrics\Support\SystemInfo;

it('can parse /proc/{pid}/stat content', function () {
    $parser = new LinuxProcPidStatParser;

    // Realistic /proc/1234/stat content
    $content = '1234 (php-fpm) S 1 1234 1234 0 -1 4194560 1000 0 0 0 150 75 0 0 20 0 4 0 123456 104857600 2560 18446744073709551615 0 0 0 0 0 0 0 0 0 0 0 0 17 0 0 0 0 0 0';

    $result = $parser->parse($content, 1234);

    expect($result->isSuccess())->toBeTrue();

    $snapshot = $result->getValue();
    expect($snapshot->pid)->toBe(1234);
    expect($snapshot->parentPid)->toBe(1);
    expect($snapshot->resources->cpuTimes->user)->toBe(150);
    expect($snapshot->resources->cpuTimes->system)->toBe(75);
    expect($snapshot->resources->threadCount)->toBe(4);
    expect($snapshot->resources->memoryVmsBytes)->toBe(104857600);
    expect($snapshot->resources->memoryRssBytes)->toBe(2560 * SystemInfo::getPageSize()); // 2560 pages
});

it('handles process names with spaces', function () {
    $parser = new LinuxProcPidStatParser;

    $content = '5678 (My Process Name) R 100 5678 5678 0 -1 4194560 500 0 0 0 50 25 0 0 20 0 2 0 654321 52428800 1280 18446744073709551615 0 0 0 0 0 0 0 0 0 0 0 0 17 0 0 0 0 0 0';

    $result = $parser->parse($content, 5678);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getValue()->pid)->toBe(5678);
});

it('handles process names with parentheses', function () {
    $parser = new LinuxProcPidStatParser;

    $content = '9999 (node (worker)) S 1 9999 9999 0 -1 4194560 200 0 0 0 30 15 0 0 20 0 1 0 789012 31457280 768 18446744073709551615 0 0 0 0 0 0 0 0 0 0 0 0 17 0 0 0 0 0 0';

    $result = $parser->parse($content, 9999);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getValue()->pid)->toBe(9999);
});

it('fails on empty content', function () {
    $parser = new LinuxProcPidStatParser;

    $result = $parser->parse('', 1234);

    expect($result->isFailure())->toBeTrue();
    expect($result->getError())->toBeInstanceOf(ParseException::class);
});

it('fails on missing closing parenthesis', function () {
    $parser = new LinuxProcPidStatParser;

    $content = '1234 (php-fpm S 1 1234 1234 0 -1 4194560 1000 0 0 0 150 75 0 0 20 0 4 0 123456 104857600 2560';

    $result = $parser->parse($content, 1234);

    expect($result->isFailure())->toBeTrue();
    expect($result->getError())->toBeInstanceOf(ParseException::class);
    expect($result->getError()->getMessage())->toContain('missing closing parenthesis');
});

it('fails on insufficient fields', function () {
    $parser = new LinuxProcPidStatParser;

    // Only a few fields after process name
    $content = '1234 (php-fpm) S 1 1234';

    $result = $parser->parse($content, 1234);

    expect($result->isFailure())->toBeTrue();
    expect($result->getError())->toBeInstanceOf(ParseException::class);
    expect($result->getError()->getMessage())->toContain('Insufficient fields');
});

it('converts RSS from pages to bytes correctly', function () {
    $parser = new LinuxProcPidStatParser;

    // rss field (field 24) = 5000 pages
    $content = '1234 (test) S 1 1234 1234 0 -1 4194560 1000 0 0 0 100 50 0 0 20 0 2 0 123456 104857600 5000 18446744073709551615 0 0 0 0 0 0 0 0 0 0 0 0 17 0 0 0 0 0 0';

    $result = $parser->parse($content, 1234);

    expect($result->isSuccess())->toBeTrue();
    // 5000 pages * actual page size
    expect($result->getValue()->resources->memoryRssBytes)->toBe(5000 * SystemInfo::getPageSize());
});

it('handles zero CPU times', function () {
    $parser = new LinuxProcPidStatParser;

    // utime=0, stime=0
    $content = '1234 (idle) S 1 1234 1234 0 -1 4194560 0 0 0 0 0 0 0 0 20 0 1 0 123456 10485760 256 18446744073709551615 0 0 0 0 0 0 0 0 0 0 0 0 17 0 0 0 0 0 0';

    $result = $parser->parse($content, 1234);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getValue()->resources->cpuTimes->user)->toBe(0);
    expect($result->getValue()->resources->cpuTimes->system)->toBe(0);
    expect($result->getValue()->resources->cpuTimes->total())->toBe(0);
});

it('handles single-threaded process', function () {
    $parser = new LinuxProcPidStatParser;

    // num_threads=1 (field 20)
    $content = '1234 (single) S 1 1234 1234 0 -1 4194560 100 0 0 0 50 25 0 0 20 0 1 0 123456 20971520 512 18446744073709551615 0 0 0 0 0 0 0 0 0 0 0 0 17 0 0 0 0 0 0';

    $result = $parser->parse($content, 1234);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getValue()->resources->threadCount)->toBe(1);
});

it('handles multi-threaded process', function () {
    $parser = new LinuxProcPidStatParser;

    // num_threads=16 (field 20)
    $content = '1234 (multithreaded) S 1 1234 1234 0 -1 4194560 5000 0 0 0 500 250 0 0 20 0 16 0 123456 209715200 5120 18446744073709551615 0 0 0 0 0 0 0 0 0 0 0 0 17 0 0 0 0 0 0';

    $result = $parser->parse($content, 1234);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getValue()->resources->threadCount)->toBe(16);
});

it('handles large memory values', function () {
    $parser = new LinuxProcPidStatParser;

    // vsize=2GB, rss=122880 pages
    $content = '1234 (memory-hog) S 1 1234 1234 0 -1 4194560 50000 0 0 0 1000 500 0 0 20 0 8 0 123456 2147483648 122880 18446744073709551615 0 0 0 0 0 0 0 0 0 0 0 0 17 0 0 0 0 0 0';

    $result = $parser->parse($content, 1234);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getValue()->resources->memoryVmsBytes)->toBe(2147483648);
    expect($result->getValue()->resources->memoryRssBytes)->toBe(122880 * SystemInfo::getPageSize());
});

it('sets openFileDescriptors to zero', function () {
    $parser = new LinuxProcPidStatParser;

    $content = '1234 (test) S 1 1234 1234 0 -1 4194560 100 0 0 0 50 25 0 0 20 0 2 0 123456 104857600 2560 18446744073709551615 0 0 0 0 0 0 0 0 0 0 0 0 17 0 0 0 0 0 0';

    $result = $parser->parse($content, 1234);

    expect($result->isSuccess())->toBeTrue();
    // Parser doesn't read /proc/{pid}/fd, so this is always 0
    expect($result->getValue()->resources->openFileDescriptors)->toBe(0);
});

it('returns snapshot with timestamp', function () {
    $parser = new LinuxProcPidStatParser;

    $content = '1234 (test) S 1 1234 1234 0 -1 4194560 100 0 0 0 50 25 0 0 20 0 2 0 123456 104857600 2560 18446744073709551615 0 0 0 0 0 0 0 0 0 0 0 0 17 0 0 0 0 0 0';

    $result = $parser->parse($content, 1234);

    expect($result->isSuccess())->toBeTrue();
    expect($result->getValue()->timestamp)->toBeInstanceOf(DateTimeImmutable::class);
});
