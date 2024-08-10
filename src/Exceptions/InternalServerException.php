<?php

namespace PulseFrame\Exceptions;

class InternalServerException extends \RuntimeException
{
  protected $statusCode;

  public function __construct(string $message, int $code = 500, \Throwable $previous = null)
  {
    $this->statusCode = $code;
    parent::__construct($message, $code, $previous);
  }

  public function getStatusCode(): int
  {
    return $this->statusCode;
  }
}
