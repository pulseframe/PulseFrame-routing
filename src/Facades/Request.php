<?php

namespace PulseFrame\Facades;

use PulseFrame\Exceptions\ValidationException;

/**
 * Class Request
 * 
 * @category facades
 * @name Request
 * 
 * This class provides methods for retrieving request information such as query parameters and domain.
 */
class Request extends \Symfony\Component\HttpFoundation\Request
{
  /**
   * Retrieve the client's IP address from the current request.
   *
   * @return string|null The IP address of the client if available; null otherwise.
   */
  public static function ip()
  {
    if (isset($_SERVER['HTTP_CLIENT_IP']) || isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED']) || isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
      $ip = $_SERVER['HTTP_X_FORWARDED'] ?? $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_FORWARDED_FOR']) || isset($_SERVER['HTTP_FORWARDED'])) {
      $ip = $_SERVER['HTTP_FORWARDED_FOR'] ?? $_SERVER['HTTP_FORWARDED'];
    } else {
      $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    }

    return $ip;
  }

  /**
   * Retrieve the query parameter value from the current request.
   *
   * @param string $key The key of the query parameter to retrieve.
   * @return mixed|null The value of the query parameter if found; null otherwise.
   */
  public static function Query($key)
  {
    return isset($_GET[$key]) ? $_GET[$key] : null;
  }

  /**
   * Retrieve the domain (host) of the current request.
   *
   * @return string|null The domain (host) of the current request if available; null otherwise.
   */
  public static function Domain()
  {
    return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;
  }

  /**
   * Validate the request data against provided rules.
   *
   * @param array $rules Array of field names and validation rules.
   * @param bool $returnData Bool for returning data.
   * @param bool $output Bool output any validation errors.
   * @throws ValidationException If validation fails.
   */
  public static function validate(array $rules, bool $returnData = true, bool $output = true)
  {
    $data = $_POST;
    $errors = [];
    $validatedData = [];

    foreach ($rules as $field => $ruleset) {
      $fieldRules = explode('|', $ruleset);
      $value = isset($data[$field]) ? $data[$field] : null;

      if (in_array('required', $fieldRules) && empty($value)) {
        $errors[$field] = "$field is required.";
        continue;
      }

      foreach ($fieldRules as $rule) {
        if ($rule === 'required') continue;
        switch ($rule) {
          case 'string':
            if ($value !== null && !is_string($value)) {
              $errors[$field] = "$field must be a string.";
            }
            break;
          case 'int':
            if ($value !== null && filter_var($value, FILTER_VALIDATE_INT) === false) {
              $errors[$field] = "$field must be an integer.";
            }
            break;
          case 'email':
            if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
              $errors[$field] = "$field must be a valid email address.";
            }
            break;
          case 'timestamp':
            if ($value !== null && !strtotime($value)) {
              $errors[$field] = "$field must be a valid timestamp.";
            }
            break;
        }
      }

      if (empty($errors[$field])) {
        $validatedData[$field] = $value;
      }
    }

    if ($output && !empty($errors)) {
      throw new ValidationException($errors);
    }

    if ($returnData) {
      return $validatedData;
    }
  }

  public static function Capture()
  {
    return new self($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES, $_SERVER);
  }
}
