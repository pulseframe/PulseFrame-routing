<?php

namespace PulseFrame\Exceptions;

class ValidationException extends \Exception
{
  protected $errors;

  public function __construct(array $errors, $message = "Validation failed", $code = 0, \Exception $previous = null)
  {
    $this->errors = $errors;
    $message = $this->formatErrors();
    parent::__construct($message, $code, $previous);
  }

  protected function formatErrors()
  {
    $errorMessages = [];
    foreach ($this->errors as $field => $error) {
      $errorMessages[] = "Validation failed for field '{$field}', error: {$error}";
    }
    return implode('; ', $errorMessages);
  }

  public function getErrors()
  {
    return $this->errors;
  }
}
