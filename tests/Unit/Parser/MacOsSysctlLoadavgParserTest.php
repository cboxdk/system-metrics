<?php

declare(strict_types=1);

use Cbox\SystemMetrics\Exceptions\ParseException;
use Cbox\SystemMetrics\Support\Parser\MacOsSysctlLoadavgParser;

it('can parse valid sysctl output with braces', function () {
    $parser = new MacOsSysctlLoadavgParser;
    $output = '{ 0.57 0.80 0.85 }';

    $result = $parser->parse($output);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(0.57);
    expect($load->fiveMinutes)->toBe(0.80);
    expect($load->fifteenMinutes)->toBe(0.85);
});

it('handles output without spaces inside braces', function () {
    $parser = new MacOsSysctlLoadavgParser;
    $output = '{0.57 0.80 0.85}';

    $result = $parser->parse($output);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(0.57);
    expect($load->fiveMinutes)->toBe(0.80);
    expect($load->fifteenMinutes)->toBe(0.85);
});

it('handles high load average values', function () {
    $parser = new MacOsSysctlLoadavgParser;
    $output = '{ 8.45 12.30 16.75 }';

    $result = $parser->parse($output);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(8.45);
    expect($load->fiveMinutes)->toBe(12.30);
    expect($load->fifteenMinutes)->toBe(16.75);
});

it('handles zero load values', function () {
    $parser = new MacOsSysctlLoadavgParser;
    $output = '{ 0.00 0.00 0.00 }';

    $result = $parser->parse($output);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(0.0);
    expect($load->fiveMinutes)->toBe(0.0);
    expect($load->fifteenMinutes)->toBe(0.0);
});

it('handles extra whitespace between fields', function () {
    $parser = new MacOsSysctlLoadavgParser;
    $output = "{  0.57   0.80\t0.85  }";

    $result = $parser->parse($output);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(0.57);
    expect($load->fiveMinutes)->toBe(0.80);
    expect($load->fifteenMinutes)->toBe(0.85);
});

it('handles leading and trailing whitespace', function () {
    $parser = new MacOsSysctlLoadavgParser;
    $output = "  { 0.57 0.80 0.85 }  \n";

    $result = $parser->parse($output);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(0.57);
    expect($load->fiveMinutes)->toBe(0.80);
    expect($load->fifteenMinutes)->toBe(0.85);
});

it('handles only opening brace', function () {
    $parser = new MacOsSysctlLoadavgParser;
    $output = '{ 0.57 0.80 0.85';

    $result = $parser->parse($output);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(0.57);
    expect($load->fiveMinutes)->toBe(0.80);
    expect($load->fifteenMinutes)->toBe(0.85);
});

it('handles only closing brace', function () {
    $parser = new MacOsSysctlLoadavgParser;
    $output = '0.57 0.80 0.85 }';

    $result = $parser->parse($output);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(0.57);
    expect($load->fiveMinutes)->toBe(0.80);
    expect($load->fifteenMinutes)->toBe(0.85);
});

it('handles output without braces', function () {
    $parser = new MacOsSysctlLoadavgParser;
    $output = '0.57 0.80 0.85';

    $result = $parser->parse($output);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(0.57);
    expect($load->fiveMinutes)->toBe(0.80);
    expect($load->fifteenMinutes)->toBe(0.85);
});

it('fails on empty output', function () {
    $parser = new MacOsSysctlLoadavgParser;

    $result = $parser->parse('');

    expect($result->isFailure())->toBeTrue();
    $error = $result->getError();
    expect($error)->toBeInstanceOf(ParseException::class);
    expect($error->getMessage())->toContain('Empty output');
});

it('fails on whitespace-only output', function () {
    $parser = new MacOsSysctlLoadavgParser;

    $result = $parser->parse("   \t\n  ");

    expect($result->isFailure())->toBeTrue();
    $error = $result->getError();
    expect($error)->toBeInstanceOf(ParseException::class);
    expect($error->getMessage())->toContain('Empty output');
});

it('fails on braces-only output', function () {
    $parser = new MacOsSysctlLoadavgParser;

    $result = $parser->parse('{ }');

    expect($result->isFailure())->toBeTrue();
    $error = $result->getError();
    expect($error)->toBeInstanceOf(ParseException::class);
    expect($error->getMessage())->toContain('Insufficient fields');
});

it('fails on insufficient fields', function () {
    $parser = new MacOsSysctlLoadavgParser;
    $output = '{ 0.57 0.80 }';

    $result = $parser->parse($output);

    expect($result->isFailure())->toBeTrue();
    $error = $result->getError();
    expect($error)->toBeInstanceOf(ParseException::class);
    expect($error->getMessage())->toContain('Insufficient fields');
});

it('fails on single field', function () {
    $parser = new MacOsSysctlLoadavgParser;
    $output = '{ 0.57 }';

    $result = $parser->parse($output);

    expect($result->isFailure())->toBeTrue();
    $error = $result->getError();
    expect($error)->toBeInstanceOf(ParseException::class);
});

it('handles integer load values', function () {
    $parser = new MacOsSysctlLoadavgParser;
    $output = '{ 2 3 4 }';

    $result = $parser->parse($output);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(2.0);
    expect($load->fiveMinutes)->toBe(3.0);
    expect($load->fifteenMinutes)->toBe(4.0);
});

it('handles very high precision values', function () {
    $parser = new MacOsSysctlLoadavgParser;
    $output = '{ 0.123456 0.789012 0.345678 }';

    $result = $parser->parse($output);

    expect($result->isSuccess())->toBeTrue();
    $load = $result->getValue();
    expect($load->oneMinute)->toBe(0.123456);
    expect($load->fiveMinutes)->toBe(0.789012);
    expect($load->fifteenMinutes)->toBe(0.345678);
});
