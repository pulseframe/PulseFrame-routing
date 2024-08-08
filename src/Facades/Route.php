<?php

namespace PulseFrame\Facades;

use PulseFrame\Http\Router;
use PulseFrame\Http\Handlers\RouteHandler;

/**
 * Class Route
 *
 * @category facades
 *
 * This facade provides a simple interface for routing in the application. It forwards static method calls
 * to an instance of the route handler, allowing you to define routes using intuitive syntax.
 */
class Route
{
  /**
   * The instance of the router.
   *
   * @var Router|null
   */
  public static $instance = null;
  public static $router = null;

  /**
   * Handle static method calls.
   *
   * @param string $method The name of the method being called (get, post, etc.).
   * @param array $args The arguments passed to the method.
   * @return mixed The result of the router method call.
   */
  public static function __callStatic($method, $args)
  {
    if (!self::$router) {
      $routeHandler = new RouteHandler();
      self::$router = $routeHandler->getRouter();
      self::$instance = $routeHandler->getInstance();
    }

    if (method_exists(self::$instance, $method)) {
      return call_user_func_array([self::$instance, $method], $args);
    } else {
      $directory = __DIR__ . '/../Methods/Static/Routing/';
      $file = $directory . $method . '.php';

      if (file_exists($file)) {
        require_once $file;
        $className = '\\PulseFrame\\Methods\\Static\\Routing\\' . ucfirst($method);
        $instance = new $className(self::$instance);

        return call_user_func_array([$instance, 'handle'], $args);
      }

      throw new \BadMethodCallException("Method {$method} does not exist.");
    }
  }
}
