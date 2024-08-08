<?php

namespace PulseFrame\Facades;

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
  public static function Capture()
  {
    return new self($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES, $_SERVER);
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
}
