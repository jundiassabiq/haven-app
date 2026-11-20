<?php
declare(strict_types=1);

/** Thrown anywhere to stop the request and render an error page with this status. */
final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message, $status);
    }
}
