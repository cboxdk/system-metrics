<?php

use PHPeek\SystemMetrics\DTO\Result;
use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;

describe('Result', function () {
    it('can create a successful result', function () {
        $result = Result::success('test value');

        expect($result->isSuccess())->toBeTrue();
        expect($result->isFailure())->toBeFalse();
        expect($result->getValue())->toBe('test value');
    });

    it('can create a failure result', function () {
        $error = new SystemMetricsException('test error');
        $result = Result::failure($error);

        expect($result->isSuccess())->toBeFalse();
        expect($result->isFailure())->toBeTrue();
        expect($result->getError())->toBe($error);
    });

    it('throws exception when getting value from failure', function () {
        $error = new SystemMetricsException('test error');
        $result = Result::failure($error);

        expect(fn () => $result->getValue())->toThrow(SystemMetricsException::class);
    });

    it('returns null when getting error from success', function () {
        $result = Result::success('test');

        expect($result->getError())->toBeNull();
    });

    it('returns value when using getValueOr on success', function () {
        $result = Result::success('actual value');

        expect($result->getValueOr('default'))->toBe('actual value');
    });

    it('returns default when using getValueOr on failure', function () {
        $error = new SystemMetricsException('test error');
        $result = Result::failure($error);

        expect($result->getValueOr('default'))->toBe('default');
    });

    it('can map successful result', function () {
        $result = Result::success(10);
        $mapped = $result->map(fn ($value) => $value * 2);

        expect($mapped->isSuccess())->toBeTrue();
        expect($mapped->getValue())->toBe(20);
    });

    it('does not map failure result', function () {
        $error = new SystemMetricsException('test error');
        $result = Result::failure($error);
        $mapped = $result->map(fn ($value) => $value * 2);

        expect($mapped->isFailure())->toBeTrue();
        expect($mapped->getError())->toBe($error);
    });

    it('can map to different type', function () {
        $result = Result::success(10);
        $mapped = $result->map(fn ($value) => "Value: $value");

        expect($mapped->isSuccess())->toBeTrue();
        expect($mapped->getValue())->toBe('Value: 10');
    });

    it('calls onSuccess callback for successful result', function () {
        $result = Result::success('test');
        $called = false;

        $result->onSuccess(function ($value) use (&$called) {
            $called = true;
            expect($value)->toBe('test');
        });

        expect($called)->toBeTrue();
    });

    it('does not call onSuccess callback for failure result', function () {
        $error = new SystemMetricsException('test error');
        $result = Result::failure($error);
        $called = false;

        $result->onSuccess(function () use (&$called) {
            $called = true;
        });

        expect($called)->toBeFalse();
    });

    it('calls onFailure callback for failure result', function () {
        $error = new SystemMetricsException('test error');
        $result = Result::failure($error);
        $called = false;

        $result->onFailure(function ($err) use (&$called, $error) {
            $called = true;
            expect($err)->toBe($error);
        });

        expect($called)->toBeTrue();
    });

    it('does not call onFailure callback for success result', function () {
        $result = Result::success('test');
        $called = false;

        $result->onFailure(function () use (&$called) {
            $called = true;
        });

        expect($called)->toBeFalse();
    });

    it('can chain callbacks', function () {
        $result = Result::success('test');
        $successCalled = false;
        $failureCalled = false;

        $result
            ->onSuccess(function () use (&$successCalled) {
                $successCalled = true;
            })
            ->onFailure(function () use (&$failureCalled) {
                $failureCalled = true;
            });

        expect($successCalled)->toBeTrue();
        expect($failureCalled)->toBeFalse();
    });

    it('can handle complex objects as values', function () {
        $object = (object) ['name' => 'test', 'value' => 123];
        $result = Result::success($object);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBe($object);
        expect($result->getValue()->name)->toBe('test');
    });

    it('can handle null as a value', function () {
        $result = Result::success(null);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getValue())->toBeNull();
    });

    it('preserves error message through map', function () {
        $error = new SystemMetricsException('original error');
        $result = Result::failure($error);
        $mapped = $result->map(fn ($v) => $v * 2);

        expect($mapped->getError()->getMessage())->toBe('original error');
    });
});
