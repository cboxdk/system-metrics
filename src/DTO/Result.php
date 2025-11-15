<?php

declare(strict_types=1);

namespace PHPeek\SystemMetrics\DTO;

use PHPeek\SystemMetrics\Exceptions\SystemMetricsException;

/**
 * Represents the result of an operation that may succeed or fail.
 *
 * @template T
 */
final readonly class Result
{
    /**
     * @param  T|null  $value
     */
    private function __construct(
        private mixed $value,
        private ?SystemMetricsException $error,
        private bool $success,
    ) {}

    /**
     * Create a successful result.
     *
     * @template U
     *
     * @param  U  $value
     * @return Result<U>
     */
    public static function success(mixed $value): self
    {
        return new self($value, null, true);
    }

    /**
     * Create a failed result.
     *
     * @template U
     *
     * @return Result<U>
     */
    public static function failure(SystemMetricsException $error): self
    {
        /** @var Result<U> */
        return new self(null, $error, false);
    }

    /**
     * Check if the operation was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if the operation failed.
     */
    public function isFailure(): bool
    {
        return ! $this->success;
    }

    /**
     * Get the value if successful, otherwise throw the error.
     *
     * @return T
     *
     * @throws SystemMetricsException
     */
    public function getValue(): mixed
    {
        if ($this->isFailure()) {
            throw $this->error;
        }

        return $this->value;
    }

    /**
     * Get the value if successful, otherwise return the default value.
     *
     * @param  T  $default
     * @return T
     */
    public function getValueOr(mixed $default): mixed
    {
        return $this->isSuccess() ? $this->value : $default;
    }

    /**
     * Get the error if failed, otherwise null.
     */
    public function getError(): ?SystemMetricsException
    {
        return $this->error;
    }

    /**
     * Map the value if successful using the provided callback.
     *
     * @template U
     *
     * @param  callable(T): U  $mapper
     * @return Result<U>
     */
    public function map(callable $mapper): self
    {
        if ($this->isFailure()) {
            return self::failure($this->error);
        }

        return self::success($mapper($this->value));
    }

    /**
     * Execute a callback if the result is successful.
     *
     * @param  callable(T): void  $callback
     * @return $this
     */
    public function onSuccess(callable $callback): self
    {
        if ($this->isSuccess()) {
            $callback($this->value);
        }

        return $this;
    }

    /**
     * Execute a callback if the result is a failure.
     *
     * @param  callable(SystemMetricsException): void  $callback
     * @return $this
     */
    public function onFailure(callable $callback): self
    {
        if ($this->isFailure()) {
            $callback($this->error);
        }

        return $this;
    }
}
