<?php

namespace ImpressiveWeb\YandexDisk\Exception;

use Exception;
use Throwable;

class BadRequestException extends Exception
{
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
