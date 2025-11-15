<?php

declare(strict_types=1);

use PHPeek\SystemMetrics\Exceptions\ParseException;
use PHPeek\SystemMetrics\Support\Parser\LinuxProcLoadavgParser;

it('can parse valid /proc/loadavg format', function () {
    $parser = new LinuxProcLoadavgParser;
    $content = '0.52 0.58 0.59 2/750 1234';

    $result = $parser->parse($content);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(0.52);
    expect($load->fiveMinutes)->toBe(0.58);
    expect($load->fifteenMinutes)->toBe(0.59);
});

it('handles high load average values', function () {
    $parser = new LinuxProcLoadavgParser;
    $content = '8.45 12.30 16.75 15/750 5678';

    $result = $parser->parse($content);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(8.45);
    expect($load->fiveMinutes)->toBe(12.30);
    expect($load->fifteenMinutes)->toBe(16.75);
});

it('handles zero load values', function () {
    $parser = new LinuxProcLoadavgParser;
    $content = '0.00 0.00 0.00 1/234 9999';

    $result = $parser->parse($content);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(0.0);
    expect($load->fiveMinutes)->toBe(0.0);
    expect($load->fifteenMinutes)->toBe(0.0);
});

it('handles extra whitespace between fields', function () {
    $parser = new LinuxProcLoadavgParser;
    $content = "0.52   0.58\t0.59  2/750   1234";

    $result = $parser->parse($content);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(0.52);
    expect($load->fiveMinutes)->toBe(0.58);
    expect($load->fifteenMinutes)->toBe(0.59);
});

it('handles leading and trailing whitespace', function () {
    $parser = new LinuxProcLoadavgParser;
    $content = "  0.52 0.58 0.59 2/750 1234  \n";

    $result = $parser->parse($content);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(0.52);
    expect($load->fiveMinutes)->toBe(0.58);
    expect($load->fifteenMinutes)->toBe(0.59);
});

it('handles more than minimum required fields', function () {
    $parser = new LinuxProcLoadavgParser;
    $content = '0.52 0.58 0.59 2/750 1234 extra fields';

    $result = $parser->parse($content);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(0.52);
    expect($load->fiveMinutes)->toBe(0.58);
    expect($load->fifteenMinutes)->toBe(0.59);
});

it('fails on empty content', function () {
    $parser = new LinuxProcLoadavgParser;

    $result = $parser->parse('');

    expect($result->isFailure())->toBeTrue();
    $error = $result->getError();
    expect($error)->toBeInstanceOf(ParseException::class);
    expect($error->getMessage())->toContain('Empty content');
});

it('fails on whitespace-only content', function () {
    $parser = new LinuxProcLoadavgParser;

    $result = $parser->parse("   \t\n  ");

    expect($result->isFailure())->toBeTrue();
    $error = $result->getError();
    expect($error)->toBeInstanceOf(ParseException::class);
    expect($error->getMessage())->toContain('Empty content');
});

it('fails on insufficient fields', function () {
    $parser = new LinuxProcLoadavgParser;
    $content = '0.52 0.58';

    $result = $parser->parse($content);

    expect($result->isFailure())->toBeTrue();
    $error = $result->getError();
    expect($error)->toBeInstanceOf(ParseException::class);
    expect($error->getMessage())->toContain('Insufficient fields');
});

it('fails on single field', function () {
    $parser = new LinuxProcLoadavgParser;
    $content = '0.52';

    $result = $parser->parse($content);

    expect($result->isFailure())->toBeTrue();
    $error = $result->getError();
    expect($error)->toBeInstanceOf(ParseException::class);
});

it('handles integer load values', function () {
    $parser = new LinuxProcLoadavgParser;
    $content = '2 3 4 2/750 1234';

    $result = $parser->parse($content);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(2.0);
    expect($load->fiveMinutes)->toBe(3.0);
    expect($load->fifteenMinutes)->toBe(4.0);
});

it('handles very high precision values', function () {
    $parser = new LinuxProcLoadavgParser;
    $content = '0.123456 0.789012 0.345678 2/750 1234';

    $result = $parser->parse($content);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(0.123456);
    expect($load->fiveMinutes)->toBe(0.789012);
    expect($load->fifteenMinutes)->toBe(0.345678);
});
