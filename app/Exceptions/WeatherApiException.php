<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class WeatherApiException extends RuntimeException
{
    public const CONNECTION = 'connection';

    public const HTTP_ERROR = 'http_error';

    public const RATE_LIMIT = 'rate_limit';

    public const INVALID_RESPONSE = 'invalid_response';

    public function __construct(
        public readonly string $category,
        string $message,
        public readonly ?int $statusCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }
}
