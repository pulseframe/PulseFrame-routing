<?php

namespace PulseFrame\Exceptions;

class NotFoundException extends \RuntimeException
{
  protected $statusCode;

  public function __construct(string $message, int $code = 404, \Throwable $previous = null)
  {
    $this->statusCode = $code;
    parent::__construct($message, $code, $previous);
  }
}
