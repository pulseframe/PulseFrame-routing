<?php

namespace PulseFrame\Facades;

use PulseFrame\Http\Handlers\RouteHandler;
use PulseFrame\Facades\Route;

/**
 * Represents an HTTP response.
 */
class Response
{
  /** @var string The response content. */
  protected $content;

  /** @var int The HTTP status code. */
  protected $statusCode;

  /** @var array HTTP headers. */
  protected $headers;

  // HTTP status code constants
  const HTTP_OK = 200;
  const HTTP_NOT_FOUND = 404;
  const HTTP_METHOD_NOT_ALLOWED = 405;
  const HTTP_FORBIDDEN = 403;
  const HTTP_UNAUTHORIZED = 401;
  const HTTP_BAD_REQUEST = 400;
  const HTTP_INTERNAL_SERVER_ERROR = 500;

  /**
   * Constructor.
   *
   * @param string $content The response content.
   * @param int $statusCode The HTTP status code.
   * @param array $headers HTTP headers.
   */
  public function __construct($content = '', int $statusCode = self::HTTP_OK, array $headers = [])
  {
    if (is_array($content)) {
      $this->content = json_encode($content);
    } else {
      $this->content = $content;
    }
    $this->statusCode = $statusCode;
    $this->headers = $headers;
  }

  /**
   * Set the response content.
   *
   * @param string $content The response content.
   */
  public function setContent(string $content): void
  {
    $this->content = $content;
  }

  /**
   * Get the response content.
   *
   * @return string The response content.
   */
  public function getContent(): string
  {
    return $this->content;
  }

  /**
   * Set the HTTP status code.
   *
   * @param int $statusCode The HTTP status code.
   */
  public function setStatusCode(int $statusCode): void
  {
    $this->statusCode = $statusCode;
  }

  /**
   * Get the HTTP status code.
   *
   * @return int The HTTP status code.
   */
  public function getStatusCode(): int
  {
    return $this->statusCode;
  }

  /**
   * Set the HTTP headers.
   *
   * @param array $headers HTTP headers.
   */
  public function setHeaders(array $headers): void
  {
    $this->headers = $headers;
  }

  /**
   * Get the HTTP headers.
   *
   * @return array HTTP headers.
   */
  public function getHeaders(): array
  {
    return $this->headers;
  }

  /**
   * Send the HTTP response.
   */
  public function send(): void
  {
    http_response_code($this->statusCode);
    foreach ($this->headers as $key => $value) {
      header("$key: $value");
    }

    echo $this->content;
  }

  /**
   * Create a JSON response.
   *
   * @param mixed $status The status of the JSON response.
   * @param mixed $message The message of the JSON response.
   * @param mixed|null $code The optional code of the JSON response.
   * @return string JSON-encoded response.
   */
  public static function JSON($status, $message, $code = null)
  {
    return json_encode(['status' => $status, 'message' => $message, 'code' => $code]);
  }

  /**
   * Redirect to a named route.
   *
   * @param string $routeName The name of the route.
   * @param array $parameters The parameters for the route.
   * @param int $status The HTTP status code for the redirect.
   * @param array $headers HTTP headers for the redirect.
   * @throws \InvalidArgumentException When the route is not found.
   */
  public static function Redirect($routeName, $parameters = [], $status = 302, $headers = [])
  {
    if (strpos($routeName, '/') === 0) {
      $url = $routeName;
    } else {
      $router = Route::getRouterHandlerInstance();
      $route = self::findRouteByName($router, $routeName);

      if ($route) {
        $url = self::generateUrl($route['url'], $parameters);
      } else {
        throw new \InvalidArgumentException("Route '{$routeName}' not found.");
      }
    }

    self::performRedirect($url, $status, $headers);
  }

  /**
   * Find a route by its name.
   *
   * @param mixed $router The router instance.
   * @param string $routeName The name of the route.
   * @return mixed|null The route if found; null otherwise.
   */
  protected static function findRouteByName($router, $routeName)
  {
    $routeNames = RouteHandler::$routeNames;

    foreach ($routeNames as $route) {
      if ($route['name'] === $routeName) {
        return $route;
      }
    }

    return null;
  }

  /**
   * Perform an HTTP redirect.
   *
   * @param string $url The URL to redirect to.
   * @param int $status The HTTP status code for the redirect.
   * @param array $headers HTTP headers for the redirect.
   */
  protected static function performRedirect($url, $status = 302, $headers = [])
  {
    http_response_code($status);

    foreach ($headers as $key => $value) {
      header("{$key}: {$value}");
    }

    header("Location: {$url}");
    exit;
  }

  /**
   * Generate a URL with parameters.
   *
   * @param string $routeUrl The URL of the route.
   * @param array $parameters The parameters for the URL.
   * @return string The generated URL.
   */
  protected static function generateUrl($routeUrl, $parameters = [])
  {
    foreach ($parameters as $key => $value) {
      $routeUrl = str_replace('{' . $key . '}', $value, $routeUrl);
    }

    return $routeUrl;
  }
}
