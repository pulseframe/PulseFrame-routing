<?php

namespace PulseFrame\Exceptions;

class BadRequestException extends \RuntimeException
{
  protected $statusCode;

  public function __construct(string $message, int $code = 400, \Throwable $previous = null)
  {
    $this->statusCode = $code;
    parent::__construct($message, $code, $previous);
  }

  public function getStatusCode(): int
  {
    return $this->statusCode;
  }
}
