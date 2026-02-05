<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class PlentySystemException extends Exception
{
    public const string TYPE_TIMEOUT = 'timeout';

    public const string TYPE_CONNECTION = 'connection';

    public const string TYPE_AUTHENTICATION = 'authentication';

    public const string TYPE_SERVER_ERROR = 'server_error';

    public const string TYPE_UNKNOWN = 'unknown';

    public string $errorType {
        get {
            return $this->errorType;
        }
    }

    public function __construct(
        string $message,
        string $errorType = self::TYPE_UNKNOWN,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorType = $errorType;
    }

    public function isRetryable(): bool
    {
        return in_array($this->errorType, [
            self::TYPE_TIMEOUT,
            self::TYPE_CONNECTION,
            self::TYPE_SERVER_ERROR,
        ], true);
    }

    public function getUserMessage(): string
    {
        return match ($this->errorType) {
            self::TYPE_TIMEOUT => 'The request to PlentyMarkets timed out. Please try again.',
            self::TYPE_CONNECTION => 'Could not connect to PlentyMarkets. Please check your internet connection and try again.',
            self::TYPE_AUTHENTICATION => 'Authentication with PlentyMarkets failed. Please check your credentials.',
            self::TYPE_SERVER_ERROR => 'PlentyMarkets is experiencing issues. Please try again later.',
            default => 'An unexpected error occurred while fetching data. Please try again.',
        };
    }

    public static function timeout(string $message, ?Throwable $previous = null): self
    {
        return new self($message, self::TYPE_TIMEOUT, 408, $previous);
    }

    public static function connection(string $message, ?Throwable $previous = null): self
    {
        return new self($message, self::TYPE_CONNECTION, 503, $previous);
    }

    public static function authentication(string $message, ?Throwable $previous = null): self
    {
        return new self($message, self::TYPE_AUTHENTICATION, 401, $previous);
    }

    public static function serverError(string $message, ?Throwable $previous = null): self
    {
        return new self($message, self::TYPE_SERVER_ERROR, 500, $previous);
    }
}
